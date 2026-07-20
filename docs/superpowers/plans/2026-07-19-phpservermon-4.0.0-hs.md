# PHP Server Monitor 4.0.0-hs Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Modernize the `RicRey1988/phpservermon` fork as `4.0.0-hs`, require PHP 8.5, preserve dark mode and server images, modernize notifications and cron, add safe PHP diagnostics, deploy it to the VPS, and publish it to `main` without a tag or GitHub Release.

**Architecture:** Merge the useful upstream `develop` history into the existing `-hs` branch, then replace legacy notification calls with small channel adapters that return structured delivery results. Share platform checks between the installer and the admin-only PHP information page, and make cron orchestration isolate per-server failures behind an atomic lock.

**Tech Stack:** PHP 8.5, Composer 2.10+, Symfony 7.4 LTS components and HttpClient, Twig 3.28+, PHPMailer 7.1+, PHPUnit 12, PHPStan 2, MariaDB, Apache, GitHub.

## Global Constraints

- Product version is exactly `4.0.0-hs`; every future fork version keeps the `-hs` suffix.
- Composer PHP constraint is `^8.5`; PHP 9 is not claimed as supported.
- Preserve dark mode, server images, users, servers, history, logs, and compatible configuration from `3.5.3-hs`.
- Remove Jabber/JAXL, `random_compat`, the old Pushover package, and the committed `composer.phar`.
- Keep database changes additive or non-destructive; do not drop legacy columns during upgrade.
- Do not create new VPS backups; the owner has an external backup.
- Do not create a Git tag, GitHub Release, or release asset.
- Run each focused test after its task and run the complete compact verification matrix only once at the end.

---

### Task 1: Merge the modern upstream baseline without losing `-hs` customizations

**Files:**
- Modify through merge: repository files changed by `upstream/develop`
- Resolve: `README.rst`
- Resolve: `docs/conf.py`
- Verify unchanged: `src/templates/default/main/body.tpl.html`
- Verify unchanged: `src/templates/default/module/server/server/view.tpl.html`
- Verify unchanged: `src/templates/default/module/server/status/index.tpl.html`
- Verify unchanged: `src/templates/default/static/css/custom.css`
- Verify unchanged: `icons/README.md`

**Interfaces:**
- Consumes: Git history ending at `3.5.3-hs` plus the two approved design commits.
- Produces: a merged source tree containing upstream fixes and the visual `-hs` behavior.

- [ ] **Step 1: Record the expected `-hs` customization surface**

Run:

```powershell
git show --name-status --format='' 0a73673
```

Expected: the output includes the five template/style paths above, `icons/README.md`, version documentation, and no private configuration.

- [ ] **Step 2: Merge `upstream/develop`**

Run:

```powershell
git fetch upstream
git merge --no-ff upstream/develop -m "Merge upstream develop for 4.0.0-hs"
```

Expected: merge conflicts are limited to fork metadata such as `README.rst` and `docs/conf.py`; the removed `puphpet/` tree stays removed.

- [ ] **Step 3: Resolve fork metadata while retaining upstream documentation**

Keep upstream documentation content, then make these values exact:

```rst
PHP Server Monitor
==================
Version 4.0.0-hs

This HS fork adds a dark interface and per-server images while tracking
compatible improvements from phpservermon/phpservermon.
```

```python
version = '4.0'
release = '4.0.0-hs'
```

- [ ] **Step 4: Verify the dark theme and image hooks survived the merge**

Run:

```powershell
rg -n "custom.css|icons/|server_image|image" src/templates/default/main/body.tpl.html src/templates/default/module/server src/templates/default/static/css/custom.css icons/README.md
```

Expected: `custom.css` is loaded, server views reference the image/icon path, and the custom stylesheet is non-empty.

- [ ] **Step 5: Commit the baseline merge resolution**

```powershell
git add README.rst docs/conf.py src/templates/default icons
git commit -m "chore: merge upstream improvements for 4.0.0-hs"
```

---

### Task 2: Establish the PHP 8.5 toolchain, version, and compact CI

**Files:**
- Modify: `composer.json`
- Modify: `composer.lock`
- Delete: `composer.phar`
- Modify: `.gitignore`
- Modify: `src/includes/psmconfig.inc.php`
- Create: `CHANGELOG.md`
- Create: `phpunit.xml.dist`
- Create: `phpstan.neon.dist`
- Create: `tests/bootstrap.php`
- Create: `tests/Unit/VersionTest.php`
- Create: `.github/workflows/php85.yml`

**Interfaces:**
- Produces: Composer autoloading on PHP 8.5, PHPUnit bootstrap, PHPStan configuration, and `PSM_VERSION = 4.0.0-hs`.

- [ ] **Step 1: Write the failing version test**

```php
<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class VersionTest extends TestCase
{
    public function testHsVersionIsExposed(): void
    {
        self::assertSame('4.0.0-hs', PSM_VERSION);
    }
}
```

`tests/bootstrap.php`:

```php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
```

- [ ] **Step 2: Run the test and observe the old version**

Run:

```powershell
C:\php-ia\php.exe -d extension=openssl -d extension=pdo_mysql vendor/bin/phpunit tests/Unit/VersionTest.php
```

Expected: FAIL because the constant is still `3.5.3-hs` or dependencies have not yet been regenerated.

- [ ] **Step 3: Replace production and development constraints**

Use this dependency shape in `composer.json` while retaining the existing package metadata and PSR-4 autoload block:

```json
{
  "require": {
    "php": "^8.5",
    "ext-ctype": "*",
    "ext-curl": "*",
    "ext-filter": "*",
    "ext-hash": "*",
    "ext-json": "*",
    "ext-libxml": "*",
    "ext-mbstring": "*",
    "ext-openssl": "*",
    "ext-pdo": "*",
    "ext-pdo_mysql": "*",
    "ext-xml": "*",
    "phpmailer/phpmailer": "^7.1",
    "symfony/config": "^7.4",
    "symfony/dependency-injection": "^7.4",
    "symfony/event-dispatcher": "^7.4",
    "symfony/filesystem": "^7.4",
    "symfony/http-client": "^7.4",
    "symfony/http-foundation": "^7.4",
    "twig/twig": "^3.28",
    "viharm/psm-ldap-auth": "^1.1"
  },
  "require-dev": {
    "phpstan/phpstan": "^2.1",
    "phpunit/phpunit": "^12.0"
  }
}
```

Remove `jaxl/jaxl`, `paragonie/random_compat`, and `php-pushover/php-pushover`. Delete `composer.phar` and add `/composer.phar` to `.gitignore`.

- [ ] **Step 4: Set the application version and initialize the changelog**

Use:

```php
define('PSM_VERSION', '4.0.0-hs');
```

`CHANGELOG.md` starts with:

```markdown
# Changelog

## [4.0.0-hs] - Unreleased

### Added
- Secure administrator-only PHP environment diagnostics.

### Changed
- Minimum runtime is PHP 8.5.

### Fixed
- Notification and cron compatibility with PHP 8.5.

### Removed
- Jabber/JAXL and obsolete compatibility packages.

### Security
- Sensitive runtime data is excluded from PHP diagnostics and notification logs.
```

- [ ] **Step 5: Regenerate the lock file with the verified Composer binary**

Run:

```powershell
C:\php-ia\php.exe -d extension=openssl -d extension=pdo_mysql C:\Users\HouseRic\Documents\Codex\2026-07-19\root-147-135-112-18\work\tools\composer\composer-2.10.2.phar update --with-all-dependencies --no-interaction
```

Expected: dependency resolution succeeds on PHP 8.5 and no abandoned JAXL/Pushover package is installed.

- [ ] **Step 6: Add PHPUnit, PHPStan, and one PHP 8.5 CI job**

`phpunit.xml.dist`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="tests/bootstrap.php" colors="true" cacheDirectory=".phpunit.cache">
  <testsuites>
    <testsuite name="PHP Server Monitor"><directory>tests</directory></testsuite>
  </testsuites>
</phpunit>
```

`phpstan.neon.dist`:

```neon
parameters:
    level: 5
    paths:
        - src/psm/Notification
        - src/psm/Util/Cron
        - src/psm/Util/Install/PlatformRequirements.php
        - src/psm/Service/PhpInfoService.php
```

`.github/workflows/php85.yml`:

```yaml
name: PHP 8.5
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          extensions: curl, mbstring, openssl, pdo_mysql, xml
          coverage: none
      - run: composer install --no-interaction --prefer-dist
      - run: composer validate --strict
      - run: composer check-platform-reqs
      - run: vendor/bin/phpunit
      - run: vendor/bin/phpstan analyse --no-progress
```

- [ ] **Step 7: Run the focused toolchain verification**

```powershell
C:\php-ia\php.exe -d extension=openssl -d extension=pdo_mysql vendor/bin/phpunit tests/Unit/VersionTest.php
C:\php-ia\php.exe -d extension=openssl -d extension=pdo_mysql C:\Users\HouseRic\Documents\Codex\2026-07-19\root-147-135-112-18\work\tools\composer\composer-2.10.2.phar validate --strict
```

Expected: PASS and a valid lock file.

- [ ] **Step 8: Commit the PHP 8.5 baseline**

```powershell
git add composer.json composer.lock .gitignore src/includes/psmconfig.inc.php CHANGELOG.md phpunit.xml.dist phpstan.neon.dist tests .github/workflows/php85.yml
git rm composer.phar
git commit -m "build: require PHP 8.5 for 4.0.0-hs"
```

---

### Task 3: Share platform requirements between installer and diagnostics

**Files:**
- Create: `src/psm/Util/Install/PlatformRequirements.php`
- Create: `tests/Unit/Util/Install/PlatformRequirementsTest.php`
- Modify: `src/psm/Module/Install/Controller/InstallController.php`

**Interfaces:**
- Produces: `PlatformRequirements::evaluate(): array` and `PlatformRequirements::isSatisfied(): bool`.

- [ ] **Step 1: Write failing platform tests**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Util\Install;

use PHPUnit\Framework\TestCase;
use psm\Util\Install\PlatformRequirements;

final class PlatformRequirementsTest extends TestCase
{
    public function testRejectsPhpBefore85(): void
    {
        $requirements = new PlatformRequirements('8.4.9', PlatformRequirements::REQUIRED_EXTENSIONS);
        self::assertFalse($requirements->isSatisfied());
    }

    public function testReportsMissingExtension(): void
    {
        $extensions = array_values(array_diff(PlatformRequirements::REQUIRED_EXTENSIONS, ['pdo_mysql']));
        $requirements = new PlatformRequirements('8.5.0', $extensions);
        self::assertContains('pdo_mysql', $requirements->missingExtensions());
    }
}
```

- [ ] **Step 2: Run the new tests**

Run: `C:\php-ia\php.exe -d extension=openssl -d extension=pdo_mysql vendor/bin/phpunit tests/Unit/Util/Install/PlatformRequirementsTest.php`

Expected: FAIL because the class does not exist.

- [ ] **Step 3: Implement the platform allowlist**

```php
<?php

declare(strict_types=1);

namespace psm\Util\Install;

final class PlatformRequirements
{
    public const MIN_PHP = '8.5.0';
    public const REQUIRED_EXTENSIONS = [
        'ctype', 'curl', 'filter', 'hash', 'json', 'libxml',
        'mbstring', 'openssl', 'pdo', 'pdo_mysql', 'xml',
    ];

    private string $phpVersion;
    private array $extensions;

    public function __construct(?string $phpVersion = null, ?array $extensions = null)
    {
        $this->phpVersion = $phpVersion ?? PHP_VERSION;
        $this->extensions = array_map('strtolower', $extensions ?? get_loaded_extensions());
    }

    public function missingExtensions(): array
    {
        return array_values(array_diff(self::REQUIRED_EXTENSIONS, $this->extensions));
    }

    public function isSatisfied(): bool
    {
        return version_compare($this->phpVersion, self::MIN_PHP, '>=')
            && $this->missingExtensions() === [];
    }

    public function evaluate(): array
    {
        return [
            'php_version' => $this->phpVersion,
            'php_supported' => version_compare($this->phpVersion, self::MIN_PHP, '>='),
            'required_extensions' => self::REQUIRED_EXTENSIONS,
            'missing_extensions' => $this->missingExtensions(),
            'satisfied' => $this->isSatisfied(),
        ];
    }
}
```

- [ ] **Step 4: Replace the installer’s PHP 5/7 checks**

In `InstallController`, create `PlatformRequirements`, render every missing extension as an error, and stop progression when `isSatisfied()` is false. Remove the old PHP 5 end-of-life warning and the now-optional sockets requirement.

Core integration:

```php
$requirements = new \psm\Util\Install\PlatformRequirements();
$platform = $requirements->evaluate();
if (!$platform['php_supported']) {
    $this->addMessage('PHP 8.5 or newer is required. Detected: ' . $platform['php_version'], 'error');
}
foreach ($platform['missing_extensions'] as $extension) {
    $this->addMessage('Missing required PHP extension: ' . $extension, 'error');
}
```

- [ ] **Step 5: Run the focused tests and commit**

```powershell
C:\php-ia\php.exe -d extension=openssl -d extension=pdo_mysql vendor/bin/phpunit tests/Unit/Util/Install/PlatformRequirementsTest.php
git add src/psm/Util/Install/PlatformRequirements.php src/psm/Module/Install/Controller/InstallController.php tests/Unit/Util/Install/PlatformRequirementsTest.php
git commit -m "feat: enforce PHP 8.5 platform requirements"
```

---

### Task 4: Remove Jabber/JAXL without destructive database changes

**Files:**
- Modify: `src/includes/functions.inc.php`
- Modify: `src/includes/psmconfig.inc.php`
- Modify: `src/psm/Module/Config/Controller/ConfigController.php`
- Modify: `src/psm/Module/User/Controller/UserController.php`
- Modify: `src/psm/Module/User/Controller/ProfileController.php`
- Modify: `src/psm/Module/Server/Controller/AbstractServerController.php`
- Modify: `src/psm/Module/Server/Controller/ServerController.php`
- Modify: `src/psm/Module/Server/Controller/LogController.php`
- Modify: `src/psm/Util/Install/Installer.php`
- Modify: `src/psm/Util/Server/UpdateManager.php`
- Modify: `src/psm/Util/Server/Updater/StatusNotifier.php`
- Modify: `src/templates/default/module/config/config.tpl.html`
- Modify: `src/templates/default/module/user/profile.tpl.html`
- Modify: `src/templates/default/module/user/user/update.tpl.html`
- Modify: `src/templates/default/module/server/server/list.tpl.html`
- Modify: `src/templates/default/module/server/server/update.tpl.html`
- Modify: `src/templates/default/module/server/server/view.tpl.html`
- Modify: `src/lang/*.lang.php`
- Modify: `README.rst`
- Modify: `docs/intro.rst`
- Modify: `docs/faq.rst`
- Create: `tests/Unit/ObsoleteFeatureRemovalTest.php`

**Interfaces:**
- Produces: no Jabber setting, notification branch, user field, fresh-schema column, or runtime dependency.

- [ ] **Step 1: Write a removal regression test**

```php
<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ObsoleteFeatureRemovalTest extends TestCase
{
    public function testRuntimeSourceDoesNotReferenceJabber(): void
    {
        $root = dirname(__DIR__, 2);
        $paths = [
            $root . '/src/includes',
            $root . '/src/psm',
            $root . '/src/templates/default/module',
        ];
        foreach ($paths as $path) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                if ($file->isFile() && preg_match('/\.(php|html)$/', $file->getFilename())) {
                    self::assertDoesNotMatchRegularExpression('/jabber|JAXL/i', file_get_contents($file->getPathname()));
                }
            }
        }
    }
}
```

- [ ] **Step 2: Run it and verify existing references are detected**

Run: `C:\php-ia\php.exe -d extension=openssl -d extension=pdo_mysql vendor/bin/phpunit tests/Unit/ObsoleteFeatureRemovalTest.php`

Expected: FAIL on the existing Config, User, Server, installer, notifier, or template references.

- [ ] **Step 3: Remove Jabber end to end**

Delete Jabber checkbox/field/encrypted-field entries, tests, labels, templates, notifier methods, SQL select columns, logging filters, and helper functions. Remove Jabber columns from only the fresh `CREATE TABLE` definitions. Do not add `DROP COLUMN` to any upgrade method.

- [ ] **Step 4: Verify no runtime reference or package remains**

```powershell
rg -n -i --glob '!CHANGELOG*' --glob '!docs/superpowers/**' --glob '!composer.lock' "jabber|jaxl" src README.rst docs
C:\php-ia\php.exe -d extension=openssl -d extension=pdo_mysql vendor/bin/phpunit tests/Unit/ObsoleteFeatureRemovalTest.php
C:\php-ia\php.exe -d extension=openssl -d extension=pdo_mysql C:\Users\HouseRic\Documents\Codex\2026-07-19\root-147-135-112-18\work\tools\composer\composer-2.10.2.phar show jaxl/jaxl
```

Expected: the source search returns no product reference, PHPUnit passes, and Composer reports that JAXL is not installed.

- [ ] **Step 5: Commit the removal**

```powershell
git add src README.rst docs tests/Unit/ObsoleteFeatureRemovalTest.php composer.lock
git commit -m "refactor: remove obsolete Jabber integration"
```

---

### Task 5: Add notification contracts and a retrying HTTP boundary

**Files:**
- Create: `src/psm/Notification/NotificationMessage.php`
- Create: `src/psm/Notification/Recipient.php`
- Create: `src/psm/Notification/DeliveryResult.php`
- Create: `src/psm/Notification/NotificationChannelInterface.php`
- Create: `src/psm/Notification/Http/RetryingHttpTransport.php`
- Create: `src/psm/Notification/Http/HttpTransportResult.php`
- Create: `tests/Unit/Notification/RetryingHttpTransportTest.php`

**Interfaces:**
- Produces: `NotificationChannelInterface::send(NotificationMessage $message, Recipient $recipient): DeliveryResult` and `RetryingHttpTransport::post(string $url, array $options): HttpTransportResult`.

- [ ] **Step 1: Write failing retry classification tests with `MockHttpClient`**

```php
public function testRetriesOneServerErrorThenSucceeds(): void
{
    $client = new MockHttpClient([
        new MockResponse('{"ok":false}', ['http_code' => 503]),
        new MockResponse('{"ok":true}', ['http_code' => 200]),
    ]);
    $transport = new RetryingHttpTransport($client, 2, static function (): void {});
    $result = $transport->post('https://example.test/send', ['json' => ['text' => 'test']]);
    self::assertTrue($result->isSuccess());
    self::assertSame(2, $result->attempts());
}
```

Add cases for `429` as temporary, `400` as permanent, malformed JSON as permanent, and a transport exception with no secret URL included in the public message.

- [ ] **Step 2: Run tests and verify the boundary is absent**

Run: `C:\php-ia\php.exe -d extension=openssl -d extension=pdo_mysql vendor/bin/phpunit tests/Unit/Notification/RetryingHttpTransportTest.php`

Expected: FAIL because the transport classes do not exist.

- [ ] **Step 3: Implement immutable notification values and results**

`DeliveryResult` exposes named constructors:

```php
public static function success(string $message = 'Delivered'): self;
public static function skipped(string $message): self;
public static function temporaryFailure(string $message): self;
public static function permanentFailure(string $message): self;
public function status(): string;
public function message(): string;
public function isSuccess(): bool;
```

`Recipient` stores `userId` plus a string-keyed attribute array and exposes `value(string $key): ?string`. `NotificationMessage` stores subject, body, and optional URL.

- [ ] **Step 4: Implement bounded retry behavior**

The transport performs at most three total attempts, retries only transport errors, `429`, and `5xx`, accepts an injected sleeper callback, decodes JSON to an array, and returns a sanitized `HttpTransportResult`. Never include request URLs or payloads in the result message.

- [ ] **Step 5: Run tests and commit**

```powershell
C:\php-ia\php.exe -d extension=openssl -d extension=pdo_mysql vendor/bin/phpunit tests/Unit/Notification/RetryingHttpTransportTest.php
git add src/psm/Notification tests/Unit/Notification
git commit -m "feat: add testable notification transport contracts"
```

---

### Task 6: Implement Telegram and Pushover adapters

**Files:**
- Create: `src/psm/Notification/Channel/TelegramChannel.php`
- Create: `src/psm/Notification/Channel/PushoverChannel.php`
- Create: `tests/Unit/Notification/TelegramChannelTest.php`
- Create: `tests/Unit/Notification/PushoverChannelTest.php`
- Modify: `src/includes/functions.inc.php`

**Interfaces:**
- Consumes: notification values and `RetryingHttpTransport` from Task 5.
- Produces: `TelegramChannel` and `PushoverChannel`, each implementing `NotificationChannelInterface`.

- [ ] **Step 1: Write failing Telegram tests**

Use `MockHttpClient` to assert a `POST` request, form fields `chat_id`, `text`, `parse_mode=HTML`, success parsing, a missing token/chat ID returning `skipped`, and messages over 4096 characters producing multiple sends without content loss.

- [ ] **Step 2: Implement Telegram with sanitized error handling**

Constructor and method signatures:

```php
public function __construct(RetryingHttpTransport $http, string $token);
public function name(): string;
public function send(NotificationMessage $message, Recipient $recipient): DeliveryResult;
```

Build `https://api.telegram.org/bot{urlencoded-token}/sendMessage` only inside `send()`, post form data, validate `ok === true`, HTML-escape untrusted values, and never return the URL or token in an error.

- [ ] **Step 3: Write failing Pushover tests**

Assert `token`, `user`, `message`, `title`, and optional `device`; assert offline priority adds `priority=2`, `retry=300`, and `expire=3600`; assert API status `0` is a permanent failure.

- [ ] **Step 4: Implement direct Pushover API delivery**

Use `https://api.pushover.net/1/messages.json`, the shared POST boundary, and recipient keys `pushover_key` and `pushover_device`. Do not instantiate the removed `Pushover` package class.

- [ ] **Step 5: Keep legacy helpers only until callers move in Task 7**

Do not delete the global `Telegram` implementation or package factories in this task because existing Config and cron callers still reference them. Task 7 removes them immediately after both entry points use the adapters.

- [ ] **Step 6: Run adapter tests and commit**

```powershell
C:\php-ia\php.exe -d extension=openssl -d extension=pdo_mysql vendor/bin/phpunit tests/Unit/Notification/TelegramChannelTest.php tests/Unit/Notification/PushoverChannelTest.php
git add src/psm/Notification/Channel src/includes/functions.inc.php tests/Unit/Notification
git commit -m "feat: modernize Telegram and Pushover notifications"
```

---

### Task 7: Route all notification entry points through channel adapters

**Files:**
- Create: `src/psm/Notification/Channel/EmailChannel.php`
- Create: `src/psm/Notification/Channel/DiscordChannel.php`
- Create: `src/psm/Notification/Channel/WebhookChannel.php`
- Create: `src/psm/Notification/Channel/SmsChannel.php`
- Create: `src/psm/Notification/ChannelRegistry.php`
- Modify: `src/config/services.xml`
- Modify: `src/psm/Util/Server/Updater/StatusNotifier.php`
- Modify: `src/psm/Util/Server/UpdateManager.php`
- Modify: `src/psm/Module/Config/Controller/ConfigController.php`
- Create: `tests/Unit/Notification/StatusNotifierTest.php`

**Interfaces:**
- Consumes: channel contract from Task 5 and Telegram/Pushover from Task 6.
- Produces: `ChannelRegistry::get(string $name): NotificationChannelInterface` used by both Config tests and cron notifications.

- [ ] **Step 1: Write a failing shared-path test**

Create a fake channel that records calls. Assert `StatusNotifier` sends to it once for a configured recipient and continues to a second channel after the first returns `temporary_failure`.

- [ ] **Step 2: Implement remaining adapters**

- `EmailChannel` wraps PHPMailer 7, clears addresses after each send, and maps `Send()` failures to `permanent_failure`.
- `DiscordChannel` posts `{"content": "..."}` through the shared transport.
- `WebhookChannel` validates the configured URL and JSON template before posting.
- `SmsChannel` wraps the selected existing SMS gateway and returns a structured result.

Each implements the exact Task 5 interface and rejects an empty recipient setting with `DeliveryResult::skipped()`.

- [ ] **Step 3: Register adapters once**

Add service definitions for the HTTP client/factory, retrying transport, channel registry, and channels in `src/config/services.xml`. Configuration values remain read at send time through existing `psm_get_conf()` calls so saved settings are not cached across requests.

- [ ] **Step 4: Refactor `StatusNotifier`**

Replace direct email/cURL/package calls with registry lookups. Preserve existing per-channel message templates, combined notifications, logging options, recipient filtering, and add-URL settings. Map database rows into `Recipient` once per user.

After every Config and cron caller uses the registry, delete the global `Telegram` class plus `psm_build_telegram()` and `psm_build_pushover()` from `src/includes/functions.inc.php`.

- [ ] **Step 5: Refactor Config test buttons**

`testEmail()`, `testTelegram()`, `testPushover()`, `testDiscord()`, `testWebhook()`, and `testSms()` call the same registered adapter used by `StatusNotifier`, prefix the test message with `[PRUEBA 4.0.0-hs]`, and display `DeliveryResult::message()`.

- [ ] **Step 6: Run focused notification tests and commit**

```powershell
C:\php-ia\php.exe -d extension=openssl -d extension=pdo_mysql vendor/bin/phpunit tests/Unit/Notification
git add src/config/services.xml src/psm/Notification src/psm/Util/Server src/psm/Module/Config/Controller/ConfigController.php tests/Unit/Notification
git commit -m "refactor: unify notification delivery paths"
```

---

### Task 8: Make cron locking, authorization, and results reliable

**Files:**
- Create: `src/psm/Util/Cron/CronLock.php`
- Create: `src/psm/Util/Cron/WebCronAuthorizer.php`
- Create: `src/psm/Util/Server/UpdateSummary.php`
- Modify: `src/psm/Util/Server/UpdateManager.php`
- Modify: `cron/status.cron.php`
- Create: `tests/Unit/Cron/CronLockTest.php`
- Create: `tests/Unit/Cron/WebCronAuthorizerTest.php`
- Create: `tests/Unit/Server/UpdateManagerTest.php`

**Interfaces:**
- Produces: `CronLock::acquire(): bool`, `CronLock::release(): void`, `WebCronAuthorizer::isAllowed(string $remoteIp, ?string $key): bool`, and `UpdateManager::run(...): UpdateSummary`.

- [ ] **Step 1: Write failing lock and authorization tests**

Assert two lock instances cannot acquire the same file concurrently; release allows a later acquisition; empty/wrong webcron keys fail; correct keys use configured allowlist behavior; forwarded headers are ignored by the class.

- [ ] **Step 2: Implement atomic `flock()` locking**

`CronLock` opens the lock path with `c+`, acquires `LOCK_EX | LOCK_NB`, and releases/ closes it idempotently. It must not delete a lock file owned by another process.

- [ ] **Step 3: Implement constant-time webcron authorization**

Return false unless webcron is enabled. Accept when a non-empty configured key matches via `hash_equals()`, or when the remote address is in the explicit allowlist. Never read `HTTP_X_FORWARDED_FOR` in this class.

- [ ] **Step 4: Isolate per-server failures**

Change `UpdateManager::run()` to catch `Throwable` around each server update/archive/notification, increment `processed` or `failed`, continue, and return an immutable `UpdateSummary`. Preserve combined notification delivery after the loop.

- [ ] **Step 5: Wrap the cron lifecycle in `try/finally`**

`status.cron.php` checks PHP 8.5/platform requirements, authorizes webcron if not CLI, acquires `PSM_PATH_LOGS . 'status.cron.lock'`, runs the manager, prints a concise summary, exits `0` on full success, `1` on per-server failures, `2` on platform/bootstrap failure, and always releases the lock.

- [ ] **Step 6: Run cron tests and commit**

```powershell
C:\php-ia\php.exe -d extension=openssl -d extension=pdo_mysql vendor/bin/phpunit tests/Unit/Cron tests/Unit/Server/UpdateManagerTest.php
git add cron/status.cron.php src/psm/Util/Cron src/psm/Util/Server tests/Unit/Cron tests/Unit/Server
git commit -m "fix: harden cron execution on PHP 8.5"
```

---

### Task 9: Add administrator-only safe PHP information

**Files:**
- Create: `src/psm/Service/PhpInfoService.php`
- Modify: `src/config/services.xml`
- Modify: `src/psm/Module/Config/Controller/ConfigController.php`
- Modify: `src/templates/default/module/config/config.tpl.html`
- Modify: `src/lang/en_US.lang.php`
- Modify: `src/lang/es_ES.lang.php`
- Create: `tests/Unit/Service/PhpInfoServiceTest.php`

**Interfaces:**
- Consumes: `PlatformRequirements::evaluate()` from Task 3.
- Produces: `PhpInfoService::collect(): array` containing only approved keys.

- [ ] **Step 1: Write a failing allowlist/redaction test**

```php
public function testCollectReturnsOnlySafeKeys(): void
{
    $data = (new PhpInfoService(new PlatformRequirements()))->collect();
    self::assertSame([
        'application_version', 'php_version', 'sapi', 'os', 'php_ini',
        'memory_limit', 'upload_max_filesize', 'post_max_size',
        'max_execution_time', 'timezone', 'opcache', 'platform',
    ], array_keys($data));
    self::assertArrayNotHasKey('environment', $data);
    self::assertStringNotContainsString('DB_', json_encode($data, JSON_THROW_ON_ERROR));
}
```

- [ ] **Step 2: Implement the explicit allowlist**

`collect()` reads only `PHP_VERSION`, `PHP_SAPI`, `PHP_OS_FAMILY`, `php_ini_loaded_file()`, approved `ini_get()` keys, `date_default_timezone_get()`, `opcache_get_status(false)` reduced to enabled/cache-full, and the Task 3 platform array. Do not call `phpinfo()`, inspect superglobals, or enumerate environment variables.

- [ ] **Step 3: Add the admin tab**

Register `service.php_info`, collect it in `executeIndex()`, and add a `PHP information / Información PHP` tab showing escaped labels, values, and success/warning/error badges. The existing `PSM_USER_ADMIN` minimum on `ConfigController` remains the authorization boundary.

- [ ] **Step 4: Run service tests and commit**

```powershell
C:\php-ia\php.exe -d extension=openssl -d extension=pdo_mysql vendor/bin/phpunit tests/Unit/Service/PhpInfoServiceTest.php
git add src/psm/Service/PhpInfoService.php src/config/services.xml src/psm/Module/Config/Controller/ConfigController.php src/templates/default/module/config/config.tpl.html src/lang/en_US.lang.php src/lang/es_ES.lang.php tests/Unit/Service/PhpInfoServiceTest.php
git commit -m "feat: add safe administrator PHP diagnostics"
```

---

### Task 10: Finish installer migration, documentation, and changelog

**Files:**
- Modify: `src/psm/Util/Install/Installer.php`
- Modify: `src/psm/Module/Install/Controller/InstallController.php`
- Modify: `docs/install.rst`
- Modify: `docs/requirements.rst`
- Modify: `README.rst`
- Modify: `CHANGELOG.md`
- Create: `tests/Unit/Install/InstallerSchemaTest.php`

**Interfaces:**
- Consumes: `PSM_VERSION`, platform requirements, and the Jabber-free fresh schema.
- Produces: non-destructive `3.5.3-hs` to `4.0.0-hs` upgrade behavior.

- [ ] **Step 1: Write schema regression tests**

Assert fresh SQL contains the image-compatible server data, does not create Jabber fields, upgrade SQL does not contain `DROP COLUMN`, and the version is updated only after prior migration statements succeed.

- [ ] **Step 2: Implement an additive 4.0.0-hs upgrade step**

Add an installer method selected for versions before `4.0.0-hs`. It may add missing upstream columns/indexes with existence checks, but it must not drop or rewrite user/server/history rows. Update `config.version` last.

- [ ] **Step 3: Update source installation documentation**

Replace `php composer.phar install` with:

```console
composer install --no-dev --classmap-authoritative
composer check-platform-reqs --no-dev
```

Document PHP 8.5, required extensions, `-hs` versioning, CLI cron, webcron disabled by default, removed Jabber, and safe PHP diagnostics.

- [ ] **Step 4: Complete `CHANGELOG.md`**

List the actual package versions from `composer.lock`, adopted upstream improvements, notification changes, cron behavior, installer migration, removed packages/integrations, and security changes. Keep the heading `Unreleased`.

- [ ] **Step 5: Run installer tests and commit**

```powershell
C:\php-ia\php.exe -d extension=openssl -d extension=pdo_mysql vendor/bin/phpunit tests/Unit/Install/InstallerSchemaTest.php tests/Unit/Util/Install/PlatformRequirementsTest.php
git add src/psm/Util/Install src/psm/Module/Install docs README.rst CHANGELOG.md tests/Unit/Install
git commit -m "docs: prepare the 4.0.0-hs upgrade path"
```

---

### Task 11: Run the single final local verification and build a deployable tree

**Files:**
- Verify: all source and tests
- Create outside Git: `work/release-build/4.0.0-hs/`

**Interfaces:**
- Produces: one verified source commit and a deployable directory containing production `vendor/`.

- [ ] **Step 1: Run syntax once across owned PHP files**

Run a PowerShell loop over `src`, `cron`, root entry points, and `tests` with `C:\php-ia\php.exe -l`.

Expected: zero syntax failures.

- [ ] **Step 2: Run the compact complete matrix once**

```powershell
C:\php-ia\php.exe -d extension=openssl -d extension=pdo_mysql vendor/bin/phpunit
C:\php-ia\php.exe -d extension=openssl -d extension=pdo_mysql vendor/bin/phpstan analyse --no-progress
C:\php-ia\php.exe -d extension=openssl -d extension=pdo_mysql C:\Users\HouseRic\Documents\Codex\2026-07-19\root-147-135-112-18\work\tools\composer\composer-2.10.2.phar validate --strict
C:\php-ia\php.exe -d extension=openssl -d extension=pdo_mysql C:\Users\HouseRic\Documents\Codex\2026-07-19\root-147-135-112-18\work\tools\composer\composer-2.10.2.phar audit --locked --no-dev
C:\php-ia\php.exe -d extension=openssl -d extension=pdo_mysql C:\Users\HouseRic\Documents\Codex\2026-07-19\root-147-135-112-18\work\tools\composer\composer-2.10.2.phar check-platform-reqs --no-dev
```

Expected: all tests pass, PHPStan has no errors, Composer is valid, audit has no advisories, and platform requirements pass.

- [ ] **Step 3: Scan tracked content for secrets**

```powershell
C:\Users\HouseRic\Documents\Codex\2026-07-19\root-147-135-112-18\work\tools\gitleaks\gitleaks.exe git . --redact --no-banner
```

Expected: no leaks.

- [ ] **Step 4: Build the deployment directory**

Run from the repository root:

```powershell
$buildRoot='C:\Users\HouseRic\Documents\Codex\2026-07-19\root-147-135-112-18\work\release-build\4.0.0-hs'
$archive='C:\Users\HouseRic\Documents\Codex\2026-07-19\root-147-135-112-18\work\release-build\4.0.0-hs.tar'
New-Item -ItemType Directory -Path $buildRoot | Out-Null
git archive -o $archive HEAD
tar -xf $archive -C $buildRoot
C:\php-ia\php.exe -d extension=openssl -d extension=pdo_mysql C:\Users\HouseRic\Documents\Codex\2026-07-19\root-147-135-112-18\work\tools\composer\composer-2.10.2.phar install --working-dir=$buildRoot --no-dev --classmap-authoritative --no-interaction
```

Do not copy `config.php`, database data, logs, user icons from the VPS snapshot, tests, or development caches.

- [ ] **Step 5: Commit any verification-only corrections**

If a correction was required, rerun only its focused test, amend the relevant task commit or add a narrowly named fix commit, then repeat Step 2 once after all corrections are complete.

---

### Task 12: Validate PHP 8.5 and 4.0.0-hs in an isolated VPS stage

**Files:**
- Deploy outside Git: `/home/hostin/psm-4.0.0-hs-stage`
- Configure outside Git: an isolated stage database and local-only web endpoint

**Interfaces:**
- Consumes: deployable tree from Task 11.
- Produces: VPS evidence for platform, install/upgrade, UI, cron, and Telegram before production changes.

- [ ] **Step 1: Inventory the VPS without changing it**

Run over the pinned SSH host key:

```bash
cat /etc/os-release
php -v
apache2ctl -M
apache2ctl -S
mysql --version
crontab -l
```

Expected: identify the installed Debian/Ubuntu release, Apache PHP mode, current PHP 7.4 command, vhost document root, MariaDB/MySQL version, and cron command.

- [ ] **Step 2: Update installed packages and install PHP 8.5 in parallel**

On Ubuntu/Debian, refresh the package index, apply normal package upgrades, enable the maintained PHP repository only if the distribution repository does not provide 8.5, and install:

```bash
php8.5 php8.5-cli php8.5-common php8.5-curl php8.5-intl php8.5-mbstring php8.5-mysql php8.5-opcache php8.5-xml libapache2-mod-php8.5
```

Do not disable PHP 7.4 or reload Apache in this step. Verify `php8.5 -v` and `php8.5 -m` first.

- [ ] **Step 3: Upload the stage tree and create an isolated database**

Upload Task 11 to `/home/hostin/psm-4.0.0-hs-stage`, set the same safe ownership model as production, create `psm_400hs_stage` plus a dedicated random database password, and write only the stage `config.php`. Do not copy production credentials into Git or command output.

- [ ] **Step 4: Exercise fresh install and upgrade paths**

Use the stage database for a fresh install. Then reset the stage database from a sanitized 3.5.3-hs schema/data fixture and run the upgrade. Compare row counts for users, servers, history, and logs before/after; verify server images resolve from the stage icon directory.

- [ ] **Step 5: Run stage cron and local HTTP smoke checks**

Run cron with `php8.5`, verify a second simultaneous invocation exits because of the atomic lock, and curl the local-only stage endpoint for login, status, Config, and PHP information. Confirm the PHP page lacks environment/cookie/header data.

- [ ] **Step 6: Send one controlled Telegram test**

Use the administrator’s configured Telegram recipient and send exactly one message prefixed `[PRUEBA 4.0.0-hs]`. Confirm a successful Bot API result and that logs do not contain the token.

---

### Task 13: Publish the verified code to GitHub `main` without a Release

**Files:**
- Publish: local branch history to `origin/main`

**Interfaces:**
- Consumes: successful Task 11 and Task 12 evidence.
- Produces: public `main` at the verified `4.0.0-hs` commit.

- [ ] **Step 1: Confirm publication scope**

```powershell
git status --short --branch
git log --oneline origin/main..HEAD
git diff --stat origin/main..HEAD
git tag --points-at HEAD
```

Expected: clean branch, intentional 4.0.0-hs commits, and no tag at HEAD.

- [ ] **Step 2: Fast-forward local `main` and push only `main`**

```powershell
git switch main
git merge --ff-only codex/4.0.0-hs
git push origin main
```

Expected: push succeeds without creating a PR, tag, or Release.

- [ ] **Step 3: Verify remote state**

Confirm `origin/main` equals local `HEAD`, `PSM_VERSION` is `4.0.0-hs`, `CHANGELOG.md` says `Unreleased`, and the existing GitHub release list has no `4.0.0-hs` release.

---

### Task 14: Switch production to PHP 8.5 and 4.0.0-hs without creating backups

**Files:**
- Move on VPS: `/home/hostin/public_html` → `/home/hostin/public_html-3.5.3-hs`
- Move on VPS: verified release tree → `/home/hostin/public_html`
- Modify on VPS: Apache PHP module selection and existing cron PHP command

**Interfaces:**
- Consumes: published commit and successful isolated VPS stage.
- Produces: live PHP 8.5 application with the old application directory and PHP 7.4 retained temporarily for rollback.

- [ ] **Step 1: Prepare the new production directory without a backup operation**

Create a clean release directory from the verified build, copy only the existing production `config.php` and user-owned icon/image data into it, set ownership/permissions, and run `php8.5 composer check-platform-reqs --no-dev` plus one CLI bootstrap check. Do not dump the database or duplicate the old application tree.

- [ ] **Step 2: Apply only additive installer changes**

Run the 4.0.0-hs updater against production. Verify row counts and version afterward. Abort the switch if any planned SQL is destructive or if the updater fails before setting the final version.

- [ ] **Step 3: Atomically retain the old directory and activate the new one**

```bash
mv /home/hostin/public_html /home/hostin/public_html-3.5.3-hs
mv /home/hostin/public_html-4.0.0-hs /home/hostin/public_html
```

This is a rename for rollback, not a new copied backup.

- [ ] **Step 4: Switch Apache and cron to PHP 8.5**

Disable the Apache PHP 7.4 module, enable PHP 8.5, run `apache2ctl configtest`, reload Apache only after `Syntax OK`, and update the existing cron command from the PHP 7.4 binary to `php8.5` without changing its schedule.

- [ ] **Step 5: Run one production smoke pass**

Verify HTTP status/login, dark status page, server images, Config, safe PHP information showing PHP 8.5, a manual cron invocation, and no new Apache/PHP fatal errors. Do not send another Telegram message if the stage test already validated the same credentials and code path.

- [ ] **Step 6: Keep the rollback path available**

If a production check fails, switch Apache back to PHP 7.4, rename the directories back, and restore the original cron binary. Because migrations are additive, the old application remains able to read the database without a newly created backup.

- [ ] **Step 7: Record final evidence**

Report the GitHub commit, `php -v`/`php8.5 -v`, Composer platform result, cron exit result, HTTP smoke result, and confirmation that no tag or GitHub Release was created.
