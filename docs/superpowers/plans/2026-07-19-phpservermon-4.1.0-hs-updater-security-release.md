# PHP Server Monitor 4.1.0-hs Updater, Security, and Release Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete invitation-only onboarding, diagnostics/error visibility, and a signed GitHub release updater, then integrate all 4.1.0-hs work, update documentation, publish `main`, and perform one production deployment without creating a GitHub release.

**Architecture:** Public account creation is possible only through a hashed, expiring, one-use invitation. Diagnostics aggregate sanitized runtime, cron, channel, and platform health. The updater accepts only HS GitHub releases whose detached RSA-SHA256 manifest signature and archive SHA-256 match a public key pinned in the repository; it stages files, enables maintenance mode, runs additive migrations, health-checks, rolls files back on failure, and deletes temporary artifacts. Release signing runs in GitHub Actions with a repository secret; the private key never enters source control or the VPS.

**Tech Stack:** PHP 8.5+, OpenSSL, ZipArchive, Symfony HttpClient/Filesystem, PDO MySQL, Twig, GitHub Actions, PHPUnit 12, PHPStan 2.1, Apache/systemd.

## Global Constraints

- Version and package names are exactly `4.1.0-hs`; non-HS versions are rejected by the updater.
- Do not create a `4.1.0-hs` GitHub release in this execution. Push the completed source and changelog to `main` only.
- Do not store or print database credentials, SMTP passwords, Telegram tokens, VAPID private keys, invitation tokens, release private keys, or the VPS password.
- Do not perform a persistent VPS backup; the updater may use a short-lived rollback directory and must remove it after success/failure.
- Database migrations are additive and idempotent. File rollback does not attempt destructive database rollback.
- Production receives exactly one integrated code upload after local tests and GitHub CI are green.
- Keep `config.php`, `logs/`, `icons/`, `public/server-images/`, and runtime secrets intact during deployment.

---

## File and Interface Map

**Existing integration points**

- `src/includes/psmconfig.inc.php`: version and HS GitHub release API URL.
- `src/includes/functions.inc.php`: legacy update-available query.
- `src/psm/Module/Server/Controller/UpdateController.php`: currently runs monitor checks; it remains status-update only.
- `src/psm/Module/Install/Controller/InstallController.php`, `src/psm/Util/Install/Installer.php`: migration foundation.
- `src/psm/Util/Error/RuntimeErrorHandler.php`: existing production-safe error capture.
- `.github/workflows/ci.yml`: existing test pipeline.
- `README.rst`, `CHANGELOG.md`, `CHANGELOG.rst`: user-facing documentation.

**New interfaces**

- `src/psm/Service/Invitation/InvitationService.php`: create, validate, consume tokens.
- `src/psm/Module/User/Controller/InvitationController.php`: admin create/revoke and public accept actions.
- `src/psm/Service/Diagnostics/HealthCheckService.php`: sanitized health report.
- `src/psm/Service/Diagnostics/JobRunRepository.php`: cron/update run status.
- `src/psm/Module/Config/Controller/DiagnosticsController.php`: admin diagnostics cards.
- `src/psm/Service/Update/GitHubReleaseClient.php`: GitHub release metadata/assets.
- `src/psm/Service/Update/ReleaseManifest.php`: strict manifest value object.
- `src/psm/Service/Update/ManifestVerifier.php`: pinned signature and SHA checks.
- `src/psm/Service/Update/SafeZipExtractor.php`: path/symlink-safe extraction.
- `src/psm/Service/Update/MigrationRunner.php`: shared web/CLI migration runner.
- `src/psm/Service/Update/ApplicationUpdater.php`: maintenance/stage/apply/health/rollback orchestration.
- `src/psm/Module/Config/Controller/SystemUpdateController.php`: update cards, confirmation, apply result.
- `bin/psm`: CLI `migrate`, `health`, and `version` commands.

### Task 1: Add invitation-only registration

**Files:**
- Modify: `src/psm/Util/Install/Installer.php`
- Create: `src/psm/Service/Invitation/Invitation.php`
- Create: `src/psm/Service/Invitation/InvitationRepository.php`
- Create: `src/psm/Service/Invitation/InvitationService.php`
- Create: `src/psm/Module/User/Controller/InvitationController.php`
- Modify: `src/psm/Module/User/UserModule.php`
- Create: `src/templates/default/module/user/invitation/list.tpl.html`
- Create: `src/templates/default/module/user/invitation/accept.tpl.html`
- Modify: `src/templates/default/module/user/login.tpl.html`
- Modify: `src/config/services.xml`
- Create: `tests/Unit/Invitation/InvitationServiceTest.php`
- Create: `tests/Unit/Invitation/InvitationControllerTest.php`

- [ ] **Step 1: Write failing invitation lifecycle/security tests**

Cover 32 random-byte token creation, SHA-256-only storage, optional email binding, admin-selected role, maximum 7-day expiry, one-time consumption inside the same user-creation transaction, revocation, expiry, already-used rejection, invalid token response without enumeration, CSRF on admin actions, and public registration remaining unavailable without a valid token.

- [ ] **Step 2: Run and confirm the invitation subsystem is absent**

Run: `vendor/bin/phpunit tests/Unit/Invitation`

Expected: missing class/controller failures.

- [ ] **Step 3: Add the additive table and service**

Add to fresh install and `upgrade410hs()`:

```sql
CREATE TABLE IF NOT EXISTS invitations (
  invitation_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  token_hash CHAR(64) NOT NULL,
  email VARCHAR(255) NULL,
  level TINYINT UNSIGNED NOT NULL DEFAULT 20,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  revoked_at DATETIME NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (invitation_id),
  UNIQUE KEY token_hash (token_hash)
) DEFAULT CHARSET=utf8mb4;
```

Return the plaintext invitation token exactly once from `InvitationService::create()` and render/copy it only in that response. `accept` validates token+email, reuses `UserValidator`, creates the account, marks the invitation used, and logs the user in only after transaction commit.

- [ ] **Step 4: Run invitation and authentication tests**

Run: `vendor/bin/phpunit tests/Unit/Invitation tests/Unit/Ui/AuthTemplateTest.php`

Expected: all focused tests pass and login has no unrestricted sign-up link.

- [ ] **Step 5: Commit**

```bash
git add src/psm/Util/Install/Installer.php src/psm/Service/Invitation src/psm/Module/User src/templates/default/module/user src/config/services.xml tests/Unit/Invitation
git commit -m "feat: add invitation-only account registration"
```

### Task 2: Add sanitized diagnostics and job health

**Files:**
- Modify: `src/psm/Util/Install/Installer.php`
- Create: `src/psm/Service/Diagnostics/JobRunRepository.php`
- Create: `src/psm/Service/Diagnostics/HealthCheck.php`
- Create: `src/psm/Service/Diagnostics/HealthCheckService.php`
- Create: `src/psm/Module/Config/Controller/DiagnosticsController.php`
- Modify: `src/psm/Module/Config/ConfigModule.php`
- Modify: `src/psm/Util/Error/RuntimeErrorHandler.php`
- Modify: `src/psm/Util/Server/UpdateManager.php`
- Create: `src/templates/default/module/config/diagnostics.tpl.html`
- Modify: `src/config/services.xml`
- Create: `tests/Unit/Diagnostics/HealthCheckServiceTest.php`
- Modify: `tests/Unit/Util/Error/RuntimeErrorHandlerTest.php`

- [ ] **Step 1: Write failing diagnostics/redaction tests**

Assert admin-only access; request IDs in logs and user-safe error pages; checks for PHP/version/extensions, DB, writable runtime directories, cron age, last check count/failures, pending/permanent notification deliveries, channel configuration state, service worker/HTTPS, and latest updater result. Seed strings resembling passwords/tokens/DSNs and assert none appear in output.

- [ ] **Step 2: Run and confirm health services are absent**

Run: `vendor/bin/phpunit tests/Unit/Diagnostics tests/Unit/Util/Error/RuntimeErrorHandlerTest.php`

Expected: missing diagnostics class failures.

- [ ] **Step 3: Add the job-run table and health report**

Add this idempotent table:

```sql
CREATE TABLE IF NOT EXISTS job_runs (
  job_run_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_name VARCHAR(40) NOT NULL,
  status ENUM('running','success','failed') NOT NULL,
  started_at DATETIME NOT NULL,
  finished_at DATETIME NULL,
  processed INT UNSIGNED NOT NULL DEFAULT 0,
  failed INT UNSIGNED NOT NULL DEFAULT 0,
  summary VARCHAR(255) NULL,
  PRIMARY KEY (job_run_id),
  KEY job_finished (job_name, finished_at)
) DEFAULT CHARSET=utf8mb4;
```

Record monitor cron/manual and application-update runs. Generate `bin2hex(random_bytes(8))` request IDs at bootstrap and include them in the error log/user error reference. `HealthCheckService` returns typed status `ok|warning|error`, public label/detail, and never raw configuration values, exception traces, remote bodies, or filesystem paths outside the app root.

- [ ] **Step 4: Run diagnostics tests and PHPStan**

Run: `vendor/bin/phpunit tests/Unit/Diagnostics tests/Unit/Util/Error/RuntimeErrorHandlerTest.php`

Run: `vendor/bin/phpstan analyse`

Expected: focused tests pass and PHPStan exits 0.

- [ ] **Step 5: Commit**

```bash
git add src/psm/Util/Install/Installer.php src/psm/Service/Diagnostics src/psm/Module/Config src/psm/Util/Error/RuntimeErrorHandler.php src/psm/Util/Server/UpdateManager.php src/templates/default/module/config/diagnostics.tpl.html src/config/services.xml tests/Unit/Diagnostics tests/Unit/Util/Error/RuntimeErrorHandlerTest.php
git commit -m "feat: add sanitized system diagnostics"
```

### Task 3: Define and verify signed HS release manifests

**Files:**
- Create: `src/psm/Service/Update/ReleaseManifest.php`
- Create: `src/psm/Service/Update/HsVersion.php`
- Create: `src/psm/Service/Update/ManifestVerifier.php`
- Create: `src/psm/Service/Update/keys/hosting-supremo-release-public.pem`
- Create: `tests/Unit/Update/ReleaseManifestTest.php`
- Create: `tests/Unit/Update/ManifestVerifierTest.php`
- Create: `docs/releasing-hs.rst`

- [ ] **Step 1: Write failing parser/signature tests**

Accept only this canonical manifest shape and reject extra/missing keys, invalid URL/name/hash/PHP/HS versions, older/equal versions, malformed Base64 signatures, wrong keys, altered bytes, and hash mismatch:

```json
{
  "schema": 1,
  "version": "4.1.1-hs",
  "archive": "phpservermon-4.1.1-hs.zip",
  "sha256": "64-lowercase-hex-characters",
  "min_php": "8.5.0",
  "repository": "RicRey1988/phpservermon"
}
```

- [ ] **Step 2: Run and confirm verifier classes/key are absent**

Run: `vendor/bin/phpunit tests/Unit/Update/ReleaseManifestTest.php tests/Unit/Update/ManifestVerifierTest.php`

Expected: missing class/key failures.

- [ ] **Step 3: Generate the signing key safely and implement verification**

Generate an RSA-3072 key in a validated temporary directory outside the repository, derive the public PEM into `src/psm/Service/Update/keys/hosting-supremo-release-public.pem`, and verify a fixture signature before provisioning the private PEM as GitHub Actions secret `RELEASE_SIGNING_PRIVATE_KEY`. Confirm the secret exists, then securely delete only the resolved temporary private-key path; do not copy it to the VPS.

Verification must be byte-exact:

```php
$signature = base64_decode(trim($signatureBase64), true);
if ($signature === false
    || openssl_verify($manifestBytes, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
    throw new UpdateVerificationException('Release signature is invalid.');
}
if (!hash_equals($manifest->sha256, hash_file('sha256', $archivePath))) {
    throw new UpdateVerificationException('Release archive checksum does not match.');
}
```

Document key rotation, manifest canonicalization (UTF-8 JSON plus final newline), and incident response in `docs/releasing-hs.rst`.

- [ ] **Step 4: Run updater crypto tests**

Run: `vendor/bin/phpunit tests/Unit/Update/ReleaseManifestTest.php tests/Unit/Update/ManifestVerifierTest.php`

Expected: valid fixture passes; every tamper case fails closed.

- [ ] **Step 5: Commit only the public material**

```bash
git add src/psm/Service/Update src/psm/Service/Update/keys/hosting-supremo-release-public.pem tests/Unit/Update docs/releasing-hs.rst
git commit -m "feat: verify signed HS release manifests"
```

### Task 4: Implement the GitHub release client and safe extractor

**Files:**
- Create: `src/psm/Service/Update/GitHubReleaseClient.php`
- Create: `src/psm/Service/Update/ReleaseAssets.php`
- Create: `src/psm/Service/Update/SafeZipExtractor.php`
- Create: `src/psm/Service/Update/UpdateVerificationException.php`
- Modify: `composer.json`
- Modify: `src/psm/Util/Install/PlatformRequirements.php`
- Modify: `src/config/services.xml`
- Create: `tests/Unit/Update/GitHubReleaseClientTest.php`
- Create: `tests/Unit/Update/SafeZipExtractorTest.php`

- [ ] **Step 1: Write failing client/extraction tests**

Mock GitHub API responses and assert only non-draft, non-prerelease `*-hs` releases from `RicRey1988/phpservermon` are accepted; exactly matching `.zip`, `.json`, `.json.sig` assets are required; redirects/timeouts/body sizes are bounded. Create ZIP fixtures for `../`, absolute paths, drive letters, NUL, duplicate normalized paths, symlinks, >100 MiB uncompressed size, >10,000 files, and valid nested files.

- [ ] **Step 2: Run and confirm client/extractor are absent**

Run: `vendor/bin/phpunit tests/Unit/Update/GitHubReleaseClientTest.php tests/Unit/Update/SafeZipExtractorTest.php`

Expected: missing class failures.

- [ ] **Step 3: Require ZipArchive and implement bounded downloads/extraction**

Add `ext-zip` to Composer/platform checks. Use Symfony HttpClient with a 10-second connect timeout, 60-second total timeout, `Accept: application/vnd.github+json`, explicit HS `User-Agent`, and a 150 MiB download ceiling. Never accept asset URLs not returned by the selected GitHub release object.

`SafeZipExtractor` normalizes `/`, rejects empty/dot/dot-dot/absolute/drive/NUL paths and symlink mode bits, enforces entry/size limits, and writes only under a freshly created staging directory after verifying each resolved parent remains below it.

- [ ] **Step 4: Run update client/extractor and platform tests**

Run: `vendor/bin/phpunit tests/Unit/Update tests/Unit/Util/Install/PlatformRequirementsTest.php`

Expected: all focused tests pass.

- [ ] **Step 5: Commit**

```bash
git add composer.json src/psm/Util/Install/PlatformRequirements.php src/psm/Service/Update src/config/services.xml tests/Unit/Update tests/Unit/Util/Install/PlatformRequirementsTest.php
git commit -m "feat: download and extract HS updates safely"
```

### Task 5: Build transactional application update orchestration and CLI

**Files:**
- Create: `src/psm/Service/Update/MaintenanceMode.php`
- Create: `src/psm/Service/Update/MigrationRunner.php`
- Create: `src/psm/Service/Update/ApplicationUpdater.php`
- Create: `src/psm/Service/Update/UpdateResult.php`
- Create: `bin/psm`
- Modify: `index.php`
- Modify: `.gitignore`
- Create: `tests/Unit/Update/ApplicationUpdaterTest.php`
- Create: `tests/Unit/Update/MigrationRunnerTest.php`

- [ ] **Step 1: Write failing orchestration tests**

Use temporary app roots to cover: lock contention; verified package required before maintenance; protected runtime paths never copied/deleted; maintenance returns 503; additive migration runs once; health success cleans staging/rollback; copy failure rolls files back; migration/health failure rolls files back but reports additive DB migration; rollback failure keeps maintenance enabled and reports a request ID; all temporary directories remain inside `<app>/.psm-update` and are removed after a successful run.

- [ ] **Step 2: Run and confirm updater orchestrator is absent**

Run: `vendor/bin/phpunit tests/Unit/Update/ApplicationUpdaterTest.php tests/Unit/Update/MigrationRunnerTest.php`

Expected: missing class failures.

- [ ] **Step 3: Implement the ordered state machine**

`ApplicationUpdater::apply()` performs exactly:

1. acquire `<app>/.psm-update/update.lock` with `flock(LOCK_EX|LOCK_NB)`;
2. download manifest/signature/archive;
3. verify signature, HS version, minimum PHP, and SHA-256;
4. extract into a new same-filesystem staging directory;
5. validate required files `index.php`, `composer.json`, `src/includes/psmconfig.inc.php`, `vendor/autoload.php`;
6. copy current replaceable paths into a temporary rollback directory, excluding `config.php`, `.psm-update`, `logs`, `icons`, and `public/server-images`;
7. enable maintenance marker containing public message/request ID only;
8. atomically replace the fixed allowlist `index.php`, `install.php`, `public.php`, `.htaccess`, `cron/`, `bin/`, `src/`, `vendor/`, `docs/`, `manifest.webmanifest`, `service-worker.js`, `offline.html`, `favicon.ico`, `favicon.png`, and `phpservermon.png`; merge only tracked protection files under `public/server-images/` and never replace uploaded `.webp` files;
9. run `MigrationRunner` from stored DB version to package version;
10. run health checks for bootstrap, DB, schema version, writable runtime, and Twig render;
11. disable maintenance and delete archive/staging/rollback on success;
12. on failure restore files, retain sanitized job result, disable maintenance only if rollback health passes, and release the lock.

`bin/psm` bootstraps the same services and supports only `version`, `migrate`, and `health`, with exit codes 0 success, 1 health/migration failure, 2 invalid usage. It never prints secrets.

- [ ] **Step 4: Run orchestration and existing installer tests**

Run: `vendor/bin/phpunit tests/Unit/Update tests/Unit/Util/Install`

Run: `vendor/bin/phpstan analyse`

Expected: updater/installer tests pass and PHPStan exits 0.

- [ ] **Step 5: Commit**

```bash
git add .gitignore bin/psm index.php src/psm/Service/Update tests/Unit/Update
git commit -m "feat: apply verified updates with rollback"
```

### Task 6: Add the administrator update experience and release workflow

**Files:**
- Create: `src/psm/Module/Config/Controller/SystemUpdateController.php`
- Modify: `src/psm/Module/Config/ConfigModule.php`
- Create: `src/templates/default/module/config/system_update/index.tpl.html`
- Create: `src/templates/default/module/config/system_update/confirm.tpl.html`
- Create: `src/templates/default/static/js/system-update.js`
- Modify: `src/psm/Module/AbstractController.php`
- Create: `.github/workflows/release-hs.yml`
- Create: `tests/Unit/Update/SystemUpdateControllerTest.php`

- [ ] **Step 1: Write failing controller and workflow contract tests**

Assert admin-only access, check action read-only, apply POST+CSRF+typed confirmation text, no arbitrary URL/version input, one updater process, sanitized progress/result, notification badge when newer HS release exists, and refusal of equal/older/non-HS releases. Validate workflow assets/names, canonical manifest, SHA-256, detached signature, and secret-only private-key access.

- [ ] **Step 2: Run and confirm the update page is absent**

Run: `vendor/bin/phpunit tests/Unit/Update/SystemUpdateControllerTest.php`

Expected: missing controller/template failures.

- [ ] **Step 3: Implement cards, confirmation, and manual release workflow**

The Update page shows Installed Version, Latest Signed HS Release, PHP/extension readiness, Last Update Result, and a five-stage stepper. Applying requires typing the exact target version and checking acknowledgement of temporary maintenance. The server chooses all URLs/assets from the verified GitHub response.

`release-hs.yml` runs only through `workflow_dispatch` with an input matching `^v[0-9]+\.[0-9]+\.[0-9]+-hs$`; it checks out that tag, runs Composer validation/audit/PHPUnit/PHPStan, installs production dependencies, creates `phpservermon-{version}.zip`, writes canonical JSON, signs it with `RELEASE_SIGNING_PRIVATE_KEY`, verifies with the repository public key, and then creates the GitHub release/assets. Do not dispatch this workflow for `4.1.0-hs` in this execution.

- [ ] **Step 4: Run update/UI tests and validate workflow YAML**

Run: `vendor/bin/phpunit tests/Unit/Update tests/Unit/Ui/ModernViewContractTest.php`

Expected: all focused tests pass; workflow parser accepts `release-hs.yml`.

- [ ] **Step 5: Commit**

```bash
git add src/psm/Module/Config src/templates/default/module/config/system_update src/templates/default/static/js/system-update.js src/psm/Module/AbstractController.php .github/workflows/release-hs.yml tests/Unit/Update/SystemUpdateControllerTest.php
git commit -m "feat: add signed GitHub release updater"
```

### Task 7: Integrate version, changelog, README, and complete CI

**Files:**
- Modify: `src/includes/psmconfig.inc.php`
- Modify: `src/includes/functions.inc.php`
- Modify: `README.rst`
- Modify: `CHANGELOG.md`
- Modify: `CHANGELOG.rst`
- Modify: `docs/install.rst`
- Modify: `docs/configuration.rst`
- Modify: `tests/Unit/VersionTest.php`
- Modify: `.github/workflows/ci.yml`

- [ ] **Step 1: Update failing version/documentation tests first**

Set expected version to `4.1.0-hs`. Add README assertions for PHP 8.5+, `ext-gd`, `ext-zip`, Hope UI cards, image uploads, statistics, PWA install, Web Push/VAPID, incident deduplication, cron, invitations, diagnostics, signed updater, Hosting Supremo fork, and upgrade instructions. Reject upstream repository URLs except attribution/history sections.

- [ ] **Step 2: Run version/template tests and observe old documentation**

Run: `vendor/bin/phpunit tests/Unit/VersionTest.php tests/Unit/TemplateAssetTest.php`

Expected: failures until version/docs are updated.

- [ ] **Step 3: Update version and user-facing documentation**

Set `PSM_VERSION` to `4.1.0-hs`, keep `PSM_UPDATE_URL` on `RicRey1988/phpservermon`, and change all User-Agent/repository strings to the HS fork. Add a complete `4.1.0-hs` changelog section grouped into Added, Changed, Fixed, Security, Upgrade Notes, and Known Requirements. Explain that this source commit is not yet a release and the signed updater acts only when a newer published HS release exists.

Extend CI to install/check GD and Zip, run `composer validate --strict`, `composer audit`, the complete PHPUnit suite, PHPStan, manifest JSON validation, service-worker policy tests, and a production `composer install --no-dev` smoke build.

- [ ] **Step 4: Run the final local gate**

Run: `composer validate --strict`

Run: `composer audit`

Run: `vendor/bin/phpunit`

Run: `vendor/bin/phpstan analyse`

Run: `php bin/psm version`

Expected: no advisories, all tests green, PHPStan exit 0, CLI prints only `4.1.0-hs`.

- [ ] **Step 5: Commit integrated version/docs**

```bash
git add src/includes README.rst CHANGELOG.md CHANGELOG.rst docs tests/Unit/VersionTest.php .github/workflows/ci.yml
git commit -m "docs: prepare PHP Server Monitor 4.1.0-hs"
```

### Task 8: Review, publish `main`, and wait for GitHub CI

**Files:**
- Review: all files changed by the four 4.1.0-hs plans.

- [ ] **Step 1: Review scope and sensitive-data hygiene**

Run: `git status --short`

Run: `git diff --check`

Run: `git diff --stat origin/main...HEAD`

Run: `gitleaks git . --redact --no-banner`

Expected: only intended 4.1.0-hs changes, no whitespace errors, and no secret matches.

- [ ] **Step 2: Re-run the final verification immediately before publication**

Run: `composer audit`

Run: `vendor/bin/phpunit`

Run: `vendor/bin/phpstan analyse`

Expected: all green from the final tree.

- [ ] **Step 3: Push the integrated commit history to main**

Push the reviewed current branch to `origin/main` using a normal fast-forward push. Do not force-push and do not create a tag or release.

- [ ] **Step 4: Wait for and inspect GitHub Actions**

Use the GitHub connector or `gh run list --branch main --limit 1`, then inspect the run until completion. If a check fails, inspect its log, fix locally, rerun the full relevant test, push the fix, and wait for a green run.

- [ ] **Step 5: Record publication evidence**

Record final commit SHA and green Actions URL in the implementation handoff; do not store credentials or runtime diagnostic output in the repository.

### Task 9: Perform the single production deployment and acceptance pass

**Files:**
- Deploy root: `/home/hostin/public_html`
- Runtime-preserved: `/home/hostin/public_html/config.php`, `logs/`, `icons/`, `public/server-images/`, `.psm-update/`.

- [ ] **Step 1: Verify production prerequisites without mutation**

Over SSH, confirm the active app root, PHP reports 8.5+, required extensions include curl/gd/intl/json/mbstring/openssl/pdo_mysql/xml/zip, Apache and cron are active, current DB/app version is expected, filesystem has at least 1 GiB free, and no monitor/update lock is active. Do not print `phpinfo()`, environment variables, config contents, or credentials.

- [ ] **Step 2: Build one production package from the green commit**

Create a clean staging tree from the exact published SHA, run `composer install --no-dev --classmap-authoritative`, exclude `.git`, tests, local configuration, logs, legacy/runtime images, `.psm-update`, and the release private key, then archive as `phpservermon-4.1.0-hs-production.tar.gz`. Verify its SHA-256 locally.

- [ ] **Step 3: Upload and validate the exact target**

Upload the archive to `/tmp/phpservermon-4.1.0-hs-production.tar.gz`. Resolve and verify that the deployment target is exactly `/home/hostin/public_html` before any extraction. Compare the remote archive SHA-256 with the local value.

- [ ] **Step 4: Deploy once without a persistent backup**

Enable the application's maintenance marker, extract the package into the verified app root without deleting/preserving runtime paths listed above, restore ownership to the existing app owner, apply the existing writable SELinux labels to `logs`, `public/server-images`, and `.psm-update`, then run as the app owner:

```bash
php bin/psm migrate
php bin/psm health
```

Expected: both commands exit 0 and the DB version is `4.1.0-hs`. Disable maintenance only after health succeeds. Delete the uploaded `/tmp/phpservermon-4.1.0-hs-production.tar.gz`; do not leave a code/database backup.

- [ ] **Step 5: Validate every production tab and workflow**

Using the supplied test account in an authenticated browser, verify HTTP 200 and correct content for Dashboard, Servidores, Registro, Usuarios, Configuración, Información PHP, Diagnósticos, Actualizar sistema, Perfil, Notificaciones, invitation management, Login, Olvidé contraseña, Reset, and valid invitation acceptance. Check light/dark/auto, mobile/desktop, no horizontal overflow, fixed image boxes, generic image, upload replace/remove, and no DataTables.

- [ ] **Step 6: Validate monitoring and notifications once**

Run one manual update and confirm immediate status-card/summary refresh. Run cron once and confirm `processed`/`failed` health. Use a controlled monitor target to cross the warning threshold, verify exactly one down in-app alert and one delivery through configured Email/Telegram plus one Web Push test device, run again while down to confirm no duplicates, restore it, and verify one recovery per channel. Do not expose channel tokens in screenshots/logs.

- [ ] **Step 7: Validate PWA and updater safely**

Install the PWA in one browser, confirm service worker scope/cache policy/offline shell and push click behavior. On the updater page, run “Buscar actualización” only; confirm it refuses unsigned/non-new results and do not apply/create a release. Confirm diagnostics show healthy PHP, DB, cron, paths, and last jobs with no secrets.

- [ ] **Step 8: Observe production logs and services**

Review only the new deployment window in Apache/PHP/application logs for fatal errors, uncaught exceptions, CSP/mixed-content errors, failed cron, and notification/update failures. Confirm Apache, PHP handler, and cron remain active. Apply corrections through source, tests, GitHub main, and a narrowly scoped hotfix deployment only if acceptance uncovers a real defect.

## Plan Completion Criteria

- Invitations are hashed, expiring, single-use, and public registration is otherwise closed.
- Diagnostics expose actionable health without secrets.
- The updater trusts only signed, checksum-matching, newer `-hs` releases from the HS fork and can safely stage/rollback files.
- `README.rst` and changelogs document the complete 4.1.0-hs system and requirements.
- Local tests, PHPStan, Composer audit, and GitHub Actions are green.
- `main` contains `4.1.0-hs`, with no tag/release created.
- The VPS receives one deployment, all tabs/workflows pass, immediate monitoring works, and down/recovery alerts are exactly once.
