# PHP Server Monitor 4.1.0-hs Monitoring, Images, and Statistics Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver an immediate, accurate monitoring dashboard with normalized server images, drag-and-drop uploads, modern cards, and range-based uptime/latency statistics.

**Architecture:** Keep `StatusUpdater` as the authoritative check engine and build read models over `servers_uptime` plus `servers_history`. Add an image service that names files by server ID, validates decoded MIME data with GD, strips metadata through re-encoding, and always supplies a bundled generic image. Split manual status updates into a JSON POST action so the page can update all status cards immediately without stale full-page markup.

**Tech Stack:** PHP 8.5+, PDO MySQL, GD, Twig, Symfony HttpFoundation, native Fetch API, ApexCharts 3.27.1, PHPUnit 12.

## Global Constraints

- Preserve current ping, service, website, SSL, authentication, pattern, headers, timeout, notification, and user-assignment behavior.
- Displayed status changes only after the existing `warning_threshold` has been reached; transient failed attempts stay warning-colored.
- Images accept JPEG, PNG, and WebP only, are re-encoded, and never use a user-supplied filename.
- Cards use fixed image boxes and `object-fit: contain`; a missing or invalid image always resolves to the generic asset.
- Collections use cards and native filters, not DataTables.
- This plan is locally testable and must not deploy independently to the VPS.

---

## File and Interface Map

**Existing integration points**

- `src/psm/Util/Server/Updater/StatusUpdater.php`: calculates thresholded status and writes uptime samples.
- `src/psm/Util/Server/UpdateManager.php`: runs checks for all active servers.
- `src/psm/Module/Server/Controller/UpdateController.php`: current manual-update redirect.
- `src/psm/Module/Server/Controller/StatusController.php`: current status read model.
- `src/psm/Module/Server/Controller/ServerController.php`: server save/delete and image form integration.
- `src/psm/Util/Server/HistoryGraph.php`, `ArchiveManager.php`: existing historical aggregation.
- `src/psm/Util/Install/Installer.php`: fresh schema and additive `4.1.0-hs` migration.

**New interfaces**

- `src/psm/Service/ServerImage/ImageProcessorInterface.php`: `process(string $temporaryPath): ProcessedImage`.
- `src/psm/Service/ServerImage/GdImageProcessor.php`: validation and re-encoding.
- `src/psm/Service/ServerImage/ServerImageStorage.php`: store, resolve URL, and delete by server ID.
- `src/psm/Service/Statistics/StatisticsRange.php`: `24h|7d|30d|90d` enum.
- `src/psm/Service/Statistics/DashboardStatistics.php`: aggregate summary and time series.
- `src/psm/Service/Statistics/DashboardSnapshot.php`: typed dashboard data.
- `UpdateController::executeRun()`: CSRF-protected JSON update endpoint.
- `StatusController::executeSnapshot()`: read-only JSON status/statistics endpoint.

### Task 1: Add the 4.1 server-image schema and GD requirement

**Files:**
- Modify: `composer.json`
- Modify: `src/psm/Util/Install/PlatformRequirements.php`
- Modify: `src/psm/Util/Install/Installer.php`
- Modify: `tests/Unit/Util/Install/InstallerSchemaTest.php`
- Modify: `tests/Unit/Util/Install/PlatformRequirementsTest.php`

- [ ] **Step 1: Write failing schema and platform tests**

Assert that fresh and upgraded schemas contain nullable `servers.image_file VARCHAR(255)` and `servers.image_updated_at DATETIME`, that `upgrade410hs()` exists, and that GD is required with a clear installer message.

- [ ] **Step 2: Run the focused tests**

Run: `vendor/bin/phpunit tests/Unit/Util/Install/InstallerSchemaTest.php tests/Unit/Util/Install/PlatformRequirementsTest.php`

Expected: failures for the absent columns/migration and missing GD requirement.

- [ ] **Step 3: Implement the additive migration**

Add `ext-gd` to Composer and require `function_exists('imagewebp')` in platform checks. Extend the fresh `servers` table after `custom_header`, then add this migration gate:

```php
if (version_compare($version_from, '4.1.0-hs', '<')) {
    $this->upgrade410hs();
}
```

The migration body must use the existing idempotent helper:

```php
protected function upgrade410hs(): void
{
    $this->addColumnIfMissing('servers', 'image_file', 'VARCHAR(255) NULL AFTER `custom_header`');
    $this->addColumnIfMissing('servers', 'image_updated_at', 'DATETIME NULL AFTER `image_file`');
}
```

Later plans extend this same method with their own idempotent tables/config keys rather than creating competing version gates.

- [ ] **Step 4: Run focused tests and Composer validation**

Run: `composer validate --strict`

Run: `vendor/bin/phpunit tests/Unit/Util/Install/InstallerSchemaTest.php tests/Unit/Util/Install/PlatformRequirementsTest.php`

Expected: Composer valid and focused tests pass.

- [ ] **Step 5: Commit**

```bash
git add composer.json src/psm/Util/Install/PlatformRequirements.php src/psm/Util/Install/Installer.php tests/Unit/Util/Install
git commit -m "feat: add server image schema and GD requirement"
```

### Task 2: Implement secure image normalization and storage

**Files:**
- Create: `src/psm/Service/ServerImage/ImageProcessorInterface.php`
- Create: `src/psm/Service/ServerImage/ProcessedImage.php`
- Create: `src/psm/Service/ServerImage/GdImageProcessor.php`
- Create: `src/psm/Service/ServerImage/ServerImageStorage.php`
- Create: `src/templates/default/static/images/server-generic.svg`
- Create: `public/server-images/.htaccess`
- Create: `public/server-images/index.html`
- Modify: `.gitignore`
- Modify: `src/config/services.xml`
- Create: `tests/Unit/ServerImage/GdImageProcessorTest.php`
- Create: `tests/Unit/ServerImage/ServerImageStorageTest.php`

- [ ] **Step 1: Write failing image tests with generated fixtures**

Generate 1×1 JPEG, PNG, and WebP fixtures in test setup with GD. Assert output is WebP, maximum dimensions are 512×512, malformed/polyglot data is rejected, files over 5 MiB are rejected before decode, and server ID 42 becomes `server-42.webp`. Assert `urlFor()` returns the generic SVG when the database value or physical file is absent.

- [ ] **Step 2: Run and confirm missing classes**

Run: `vendor/bin/phpunit tests/Unit/ServerImage`

Expected: errors for missing image service classes.

- [ ] **Step 3: Implement decoded-MIME validation and re-encoding**

The processor contract is:

```php
final readonly class ProcessedImage
{
    public function __construct(
        public string $bytes,
        public string $extension,
        public int $width,
        public int $height,
    ) {}
}
```

`GdImageProcessor` must check file size, call `getimagesize()`, allow only `IMAGETYPE_JPEG|PNG|WEBP`, decode with the matching GD function, preserve transparency, resize proportionally within 512×512, and encode through `imagewebp($canvas, null, 82)`. Re-encoding removes EXIF and ignores the original filename/extension.

`ServerImageStorage` writes to a same-directory temporary file with mode `0640`, renames atomically to `public/server-images/server-{id}.webp`, and deletes only that exact server ID path. `.htaccess` disables script execution and content sniffing.

- [ ] **Step 4: Run image tests and PHPStan**

Run: `vendor/bin/phpunit tests/Unit/ServerImage`

Run: `vendor/bin/phpstan analyse`

Expected: all image tests pass and PHPStan exits 0.

- [ ] **Step 5: Commit**

```bash
git add .gitignore public/server-images src/psm/Service/ServerImage src/templates/default/static/images/server-generic.svg src/config/services.xml tests/Unit/ServerImage
git commit -m "feat: normalize and store server images securely"
```

### Task 3: Add drag-and-drop image management to the server form

**Files:**
- Modify: `src/psm/Module/Server/Controller/ServerController.php`
- Modify: `src/templates/default/module/server/server/update.tpl.html`
- Modify: `src/templates/default/static/js/app-shell.js`
- Modify: `src/templates/default/static/css/app-shell.css`
- Create: `tests/Unit/Server/ServerImageFormTest.php`

- [ ] **Step 1: Write failing save-flow tests**

Cover: create server then store upload using the new ID; replace an existing image; keep the old image when no file is posted; delete it only when `remove_image=1`; reject an invalid upload without changing the existing path; delete the physical image after server deletion.

- [ ] **Step 2: Run and see image form behavior fail**

Run: `vendor/bin/phpunit tests/Unit/Server/ServerImageFormTest.php`

Expected: failure because the controller does not inspect `$_FILES['server_image']`.

- [ ] **Step 3: Integrate the upload after server validation/save**

Add `enctype="multipart/form-data"`, `server_image`, and `remove_image` to the form. Render the component macro with `accept="image/jpeg,image/png,image/webp"`, keyboard activation, preview, replace/remove buttons, and a 5 MiB explanation. JavaScript may enhance drag-and-drop, but the native file input must work without JavaScript.

After the server row exists, process the uploaded temporary path, store by numeric server ID, and persist only `image_file` plus `image_updated_at`. On failure, show a translated validation message and keep the previously saved image.

- [ ] **Step 4: Run save-flow and field-coverage tests**

Run: `vendor/bin/phpunit tests/Unit/Server/ServerImageFormTest.php tests/Unit/Ui/FormFieldCoverageTest.php`

Expected: all focused tests pass and the pre-existing server fields remain present.

- [ ] **Step 5: Commit**

```bash
git add src/psm/Module/Server/Controller/ServerController.php src/templates/default/module/server/server/update.tpl.html src/templates/default/static/js/app-shell.js src/templates/default/static/css/app-shell.css tests/Unit/Server/ServerImageFormTest.php
git commit -m "feat: add drag and drop server images"
```

### Task 4: Create typed statistics queries across live and archived data

**Files:**
- Create: `src/psm/Service/Statistics/StatisticsRange.php`
- Create: `src/psm/Service/Statistics/DashboardSnapshot.php`
- Create: `src/psm/Service/Statistics/DashboardStatistics.php`
- Modify: `src/config/services.xml`
- Create: `tests/Unit/Statistics/DashboardStatisticsTest.php`

- [ ] **Step 1: Write failing range and aggregation tests**

Seed deterministic samples around range boundaries. Assert:

- `24h` uses live `servers_uptime` hourly buckets.
- `7d`, `30d`, and `90d` combine unarchived live samples with `servers_history` daily aggregates without double counting.
- Uptime is `(checks_total - checks_failed) / checks_total * 100` and is `null` when there are no checks.
- Latency min/average/max ignores null latency.
- The summary reports online, warning, offline, paused, active incidents, checks, and failures.
- A user sees only assigned servers unless they are admin.

- [ ] **Step 2: Run and confirm missing statistics services**

Run: `vendor/bin/phpunit tests/Unit/Statistics/DashboardStatisticsTest.php`

Expected: missing class errors.

- [ ] **Step 3: Implement the range enum and snapshot**

```php
enum StatisticsRange: string
{
    case Day = '24h';
    case Week = '7d';
    case Month = '30d';
    case Quarter = '90d';

    public function startsAt(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return match ($this) {
            self::Day => $now->modify('-24 hours'),
            self::Week => $now->modify('-7 days'),
            self::Month => $now->modify('-30 days'),
            self::Quarter => $now->modify('-90 days'),
        };
    }
}
```

Use prepared parameters for time/user filters. Return arrays suitable for `json_encode`, not SQL rows or HTML. Round percentages/latency only in the read model, never in stored samples.

- [ ] **Step 4: Run tests and static analysis**

Run: `vendor/bin/phpunit tests/Unit/Statistics/DashboardStatisticsTest.php`

Run: `vendor/bin/phpstan analyse`

Expected: tests pass and PHPStan exits 0.

- [ ] **Step 5: Commit**

```bash
git add src/psm/Service/Statistics src/config/services.xml tests/Unit/Statistics
git commit -m "feat: add dashboard statistics service"
```

### Task 5: Build the status dashboard cards and charts

**Files:**
- Modify: `src/psm/Module/Server/Controller/StatusController.php`
- Modify: `src/psm/Module/Server/Controller/AbstractServerController.php`
- Modify: `src/templates/default/module/server/status/index.tpl.html`
- Modify: `src/templates/default/module/server/status/header.tpl.html`
- Create: `src/templates/default/module/server/status/cards.tpl.html`
- Create: `src/templates/default/static/js/dashboard.js`
- Modify: `src/templates/default/static/css/app-shell.css`
- Create: `tests/Unit/Server/StatusDashboardTest.php`

- [ ] **Step 1: Write failing dashboard read-model tests**

Assert status groups and counts, fixed image URLs, accessible state labels, range validation, chart JSON, user scoping, generic-image fallback, and no table/DataTables markup.

- [ ] **Step 2: Run and confirm legacy dashboard failure**

Run: `vendor/bin/phpunit tests/Unit/Server/StatusDashboardTest.php`

Expected: failure because the controller only supplies three legacy server arrays.

- [ ] **Step 3: Implement the dashboard read model and templates**

Set `needs_charts=true`, parse `range` through `StatisticsRange::tryFrom()` with `24h` fallback, and provide summary cards, uptime/latency series, incident timeline preview, and server cards. Each server card must use:

```twig
<div class="server-image-box">
  <img src="{{ server.image_url }}" width="80" height="80"
       alt="" loading="lazy" decoding="async">
</div>
<span class="badge bg-{{ server.status_tone }}" aria-label="{{ server.status_label }}">
  {{ server.status_label }}
</span>
```

CSS fixes the box to 96×96 desktop and 72×72 mobile with `object-fit: contain`; images never size cards. ApexCharts receives data from a JSON script element encoded by Twig, not executable string concatenation.

- [ ] **Step 4: Run dashboard and UI tests**

Run: `vendor/bin/phpunit tests/Unit/Server/StatusDashboardTest.php tests/Unit/Ui/ModernViewContractTest.php`

Expected: all focused tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/psm/Module/Server/Controller src/templates/default/module/server/status src/templates/default/static/js/dashboard.js src/templates/default/static/css/app-shell.css tests/Unit/Server/StatusDashboardTest.php
git commit -m "feat: build live monitoring dashboard"
```

### Task 6: Make manual updates reflect immediately

**Files:**
- Modify: `src/psm/Module/Server/Controller/UpdateController.php`
- Modify: `src/psm/Module/Server/Controller/StatusController.php`
- Modify: `src/templates/default/module/server/status/header.tpl.html`
- Modify: `src/templates/default/static/js/dashboard.js`
- Create: `tests/Unit/Server/ManualUpdateResponseTest.php`

- [ ] **Step 1: Write failing endpoint tests**

Assert `server_update&action=run` is POST-only, admin/authorized, CSRF-protected, and returns JSON with `processed`, `failed`, `busy`, `checked_at`, and current cards/summary. Assert snapshot GET never runs checks. Assert a genuinely online result changes an offline card in the same response.

- [ ] **Step 2: Run and confirm the route is absent**

Run: `vendor/bin/phpunit tests/Unit/Server/ManualUpdateResponseTest.php`

Expected: failure because `UpdateController` supports only redirecting `index`.

- [ ] **Step 3: Add JSON run and snapshot actions**

Keep the shared `CronLock`. On success return status 200; on a held lock return 409 with `busy=true`; on partial check failures return 207 with sanitized per-server messages. The browser button disables while running, shows progress, applies returned card classes/text/timestamps/summary immediately, and falls back to a normal refresh if JavaScript or JSON parsing fails.

The regular auto-refresh fetches `server_status&action=snapshot` with `cache: 'no-store'`; it must never call the update route.

- [ ] **Step 4: Run endpoint and existing coordinator tests**

Run: `vendor/bin/phpunit tests/Unit/Server/ManualUpdateResponseTest.php tests/Unit/Server/ManualUpdateCoordinatorTest.php tests/Unit/Server/UpdateManagerTest.php`

Expected: all focused tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/psm/Module/Server/Controller/UpdateController.php src/psm/Module/Server/Controller/StatusController.php src/templates/default/module/server/status/header.tpl.html src/templates/default/static/js/dashboard.js tests/Unit/Server/ManualUpdateResponseTest.php
git commit -m "fix: refresh status cards immediately after checks"
```

### Task 7: Verify monitoring, images, and statistics together

**Files:**
- Modify if needed: `src/templates/default/static/css/app-shell.css`
- Modify if needed: `src/templates/default/static/js/dashboard.js`

- [ ] **Step 1: Run the subsystem suite**

Run: `vendor/bin/phpunit tests/Unit/Server tests/Unit/ServerImage tests/Unit/Statistics tests/Unit/Util/Install`

Expected: all subsystem tests pass.

- [ ] **Step 2: Run global checks**

Run: `vendor/bin/phpunit`

Run: `vendor/bin/phpstan analyse`

Expected: full suite green and PHPStan exit 0.

- [ ] **Step 3: Perform one controlled browser check**

With disposable local servers, exercise offline → warning → offline and offline → online; press Actualizar once and confirm cards/summary/charts update in the returned response. Upload one JPEG, PNG, and WebP, then remove an image and confirm the generic asset. Inspect 390×844 and 1440×900 layouts for fixed image sizing.

- [ ] **Step 4: Commit only required corrections**

```bash
git add src/templates/default/static/css/app-shell.css src/templates/default/static/js/dashboard.js
git commit -m "fix: complete monitoring dashboard verification"
```

## Plan Completion Criteria

- Manual updates recolor cards in the same request and never wait for a later cron refresh.
- Server images are safely uploaded, normalized, fixed-size, and replaceable from the form.
- Missing images use the generic bundled asset.
- 24h/7d/30d/90d statistics are accurate across live and archived tables.
- Dashboard, server collections, and logs remain card/timeline based.
- Full tests and PHPStan pass before integration.
