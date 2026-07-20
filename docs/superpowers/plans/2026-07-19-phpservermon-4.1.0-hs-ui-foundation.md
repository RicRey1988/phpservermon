# PHP Server Monitor 4.1.0-hs UI Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the legacy Bootstrap 4 shell, authentication pages, lists, and forms with a responsive Hope UI-based Bootstrap 5 interface while preserving every existing field and controller action.

**Architecture:** Keep the current controller/Twig routing model and introduce a single app shell plus reusable Twig components. Vendor only the Hope UI 4.0.0 CSS and the exact Bootstrap/ApexCharts runtime files needed by PHP Server Monitor; do not ship or initialize DataTables. Store theme choices in existing `users_preferences` records and expose them as validated `data-bs-theme` attributes.

**Tech Stack:** PHP 8.5+, Twig 3.28, Hope UI 4.0.0 (MIT), Bootstrap 5.2.3 JavaScript, ApexCharts 3.27.1, PHPUnit 12, PHPStan 2.1.

## Global Constraints

- The target version is `4.1.0-hs`; every future HS version keeps the `-hs` suffix.
- Preserve controller actions, CSRF keys, permissions, translated labels, and every current POST field.
- Do not include `datatables.net`, DataTables assets, DataTables markup, or `data-toggle="data-table"`.
- Do not expose stored SMTP, Telegram, webhook, password, or update-signing secrets in HTML or JavaScript.
- The footer must say “Mejorado por Hosting Supremo” and link only to `https://github.com/RicRey1988/phpservermon`.
- Complete this plan locally; production receives only the single integrated deployment in the updater/security plan.

---

## File and Interface Map

**Existing entry points to modify**

- `src/templates/default/main/body.tpl.html`: replace the Bootstrap 4 navbar shell.
- `src/templates/default/main/menu.tpl.html`: render Hope UI navigation items.
- `src/templates/default/main/macros.tpl.html`: retain compatibility wrappers while delegating to new components.
- `src/psm/Module/AbstractController.php`: provide active navigation and appearance globals.
- `src/psm/Module/User/Controller/ProfileController.php`: save appearance preferences.
- `src/templates/default/module/user/login.tpl.html`, `forgot.tpl.html`, `reset.tpl.html`: modern authentication views.
- `src/templates/default/module/server/**`, `src/templates/default/module/user/**`, `src/templates/default/module/config/config.tpl.html`: convert lists and forms to cards.

**New interfaces and assets**

- `src/psm/Service/Ui/Appearance.php`: immutable validated appearance values.
- `src/psm/Service/Ui/AppearanceService.php`: load/save `ui_scheme`, `ui_accent`, `ui_direction`, `ui_sidebar` preferences.
- `src/templates/default/main/components.tpl.html`: field, card, badge, empty-state, and pagination macros.
- `src/templates/default/main/app-sidebar.tpl.html`, `app-navbar.tpl.html`: shell partials.
- `src/templates/default/static/hope/css/hope-ui.min.css`, `dark.min.css`: vendored Hope UI styles.
- `src/templates/default/static/hope/js/bootstrap.bundle.min.js`, `apexcharts.min.js`: pinned slim runtimes.
- `src/templates/default/static/js/app-shell.js`, `src/templates/default/static/css/app-shell.css`: PHP Server Monitor behavior and overrides.
- `THIRD_PARTY_NOTICES.md`: Hope UI, Bootstrap, and ApexCharts attribution.

### Task 1: Lock the allowed UI asset set

**Files:**
- Create: `tests/Unit/Ui/HopeAssetPolicyTest.php`
- Create: `THIRD_PARTY_NOTICES.md`
- Create: `src/templates/default/static/hope/css/hope-ui.min.css`
- Create: `src/templates/default/static/hope/css/dark.min.css`
- Create: `src/templates/default/static/hope/js/bootstrap.bundle.min.js`
- Create: `src/templates/default/static/hope/js/apexcharts.min.js`

- [ ] **Step 1: Write the failing asset-policy test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Ui;

use PHPUnit\Framework\TestCase;

final class HopeAssetPolicyTest extends TestCase
{
    public function testOnlyApprovedHopeUiAssetsAreReferenced(): void
    {
        $root = dirname(__DIR__, 3);
        self::assertFileExists($root . '/src/templates/default/static/hope/css/hope-ui.min.css');
        self::assertFileExists($root . '/src/templates/default/static/hope/css/dark.min.css');
        self::assertFileExists($root . '/src/templates/default/static/hope/js/bootstrap.bundle.min.js');
        self::assertFileExists($root . '/src/templates/default/static/hope/js/apexcharts.min.js');
        self::assertFileExists($root . '/THIRD_PARTY_NOTICES.md');
    }

    public function testNoTemplateInitializesDataTables(): void
    {
        $templates = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            dirname(__DIR__, 3) . '/src/templates/default'
        ));
        foreach ($templates as $template) {
            if ($template->isFile()) {
                $contents = file_get_contents($template->getPathname());
                self::assertStringNotContainsString('data-toggle="data-table"', $contents);
                self::assertStringNotContainsString('.DataTable(', $contents);
            }
        }
    }
}
```

- [ ] **Step 2: Run the test and confirm it fails on the legacy shell**

Run: `vendor/bin/phpunit tests/Unit/Ui/HopeAssetPolicyTest.php`

Expected: failure because `body.tpl.html` still references Bootstrap 4 and the notices file does not exist.

- [ ] **Step 3: Vendor only the approved files**

From the adjacent Hope UI clone, copy `dist/assets/css/hope-ui.min.css` and `dist/assets/css/dark.min.css`. Install the versions declared by Hope UI into that clone with `npm install --no-save bootstrap@5.2.3 apexcharts@3.27.1`, then copy `node_modules/bootstrap/dist/js/bootstrap.bundle.min.js` and `node_modules/apexcharts/dist/apexcharts.min.js`. Do not copy `dist/assets/css/core/libs.min.css` or `dist/assets/js/core/libs.min.js`, because both bundle DataTables.

Record the exact projects, versions, license names, copyright notices, and upstream URLs in `THIRD_PARTY_NOTICES.md`.

- [ ] **Step 4: Re-run the focused asset test**

Run: `vendor/bin/phpunit tests/Unit/Ui/HopeAssetPolicyTest.php`

Expected: `OK (2 tests)`.

- [ ] **Step 5: Commit the asset policy**

```bash
git add THIRD_PARTY_NOTICES.md src/templates/default/static/hope tests/Unit/Ui/HopeAssetPolicyTest.php
git commit -m "build: vendor slim Hope UI runtime"
```

### Task 2: Add a validated appearance preference service

**Files:**
- Create: `src/psm/Service/Ui/Appearance.php`
- Create: `src/psm/Service/Ui/AppearanceService.php`
- Create: `tests/Unit/Ui/AppearanceServiceTest.php`
- Modify: `src/config/services.xml`
- Modify: `src/psm/Module/User/Controller/ProfileController.php`

- [ ] **Step 1: Write failing tests for defaults and invalid values**

```php
public function testInvalidPreferencesFallBackToSafeDefaults(): void
{
    $appearance = Appearance::fromPreferences([
        'ui_scheme' => 'javascript:alert(1)',
        'ui_direction' => 'sideways',
        'ui_accent' => '#fff;}',
        'ui_sidebar' => 'hidden',
    ]);

    self::assertSame(
        ['auto', 'blue', 'ltr', 'default'],
        $appearance->toArray()
    );
}
```

Cover all accepted values: schemes `auto|light|dark`, accents `blue|orange|red|purple|pink`, directions `ltr|rtl`, and sidebars `default|dark`.

- [ ] **Step 2: Run and confirm the new service is missing**

Run: `vendor/bin/phpunit tests/Unit/Ui/AppearanceServiceTest.php`

Expected: error `Class "psm\Service\Ui\AppearanceService" not found`.

- [ ] **Step 3: Implement the value object and service**

Use a named constructor that converts arbitrary stored strings to safe values, and keep the regular constructor private:

```php
final readonly class Appearance
{
    private function __construct(
        public string $scheme,
        public string $accent,
        public string $direction,
        public string $sidebar,
    ) {}

    public static function fromPreferences(array $values): self
    {
        return new self(
            in_array($values['ui_scheme'] ?? '', ['auto', 'light', 'dark'], true) ? $values['ui_scheme'] : 'auto',
            in_array($values['ui_accent'] ?? '', ['blue', 'orange', 'red', 'purple', 'pink'], true) ? $values['ui_accent'] : 'blue',
            in_array($values['ui_direction'] ?? '', ['ltr', 'rtl'], true) ? $values['ui_direction'] : 'ltr',
            in_array($values['ui_sidebar'] ?? '', ['default', 'dark'], true) ? $values['ui_sidebar'] : 'default',
        );
    }

    /** @return array{0: string, 1: string, 2: string, 3: string} */
    public function toArray(): array
    {
        return [$this->scheme, $this->accent, $this->direction, $this->sidebar];
    }
}
```

`AppearanceService::forCurrentUser()` reads existing `User::getUserPref()` keys and `saveForCurrentUser()` calls `setUserPref()` using the normalized value object. Register the service as `service.ui.appearance` and make `ProfileController` accept a CSRF-protected `appearance_submit` POST branch.

- [ ] **Step 4: Run tests and static analysis**

Run: `vendor/bin/phpunit tests/Unit/Ui/AppearanceServiceTest.php`

Run: `vendor/bin/phpstan analyse`

Expected: tests pass and PHPStan exits 0.

- [ ] **Step 5: Commit**

```bash
git add src/psm/Service/Ui src/config/services.xml src/psm/Module/User/Controller/ProfileController.php tests/Unit/Ui/AppearanceServiceTest.php
git commit -m "feat: add validated appearance preferences"
```

### Task 3: Replace the legacy page shell

**Files:**
- Modify: `src/templates/default/main/body.tpl.html`
- Modify: `src/templates/default/main/menu.tpl.html`
- Create: `src/templates/default/main/app-sidebar.tpl.html`
- Create: `src/templates/default/main/app-navbar.tpl.html`
- Create: `src/templates/default/static/css/app-shell.css`
- Create: `src/templates/default/static/js/app-shell.js`
- Modify: `src/psm/Module/AbstractController.php`
- Modify: `tests/Unit/TemplateAssetTest.php`

- [ ] **Step 1: Extend the shell test with the required landmarks**

Assert one `<aside class="sidebar`, one `<nav class="nav navbar`, one `<main id="main-content"`, a skip link, the Hosting Supremo footer link, cache-busted `?v={{ version|url_encode }}` CSS/JS URLs, and the absence of the original repository URL.

- [ ] **Step 2: Run the focused tests and see the legacy navbar fail**

Run: `vendor/bin/phpunit tests/Unit/TemplateAssetTest.php tests/Unit/Ui/HopeAssetPolicyTest.php`

Expected: failures for missing shell landmarks and legacy assets.

- [ ] **Step 3: Implement the Hope UI app shell**

In `body.tpl.html`, render this stable hierarchy:

```twig
<html lang="{{ language_current }}" dir="{{ appearance.direction }}"
      data-bs-theme="{{ appearance.resolved_scheme }}"
      data-color-root="{{ appearance.accent }}">
<body class="{{ appearance.sidebar == 'dark' ? 'sidebar-dark' : '' }}">
  <a class="visually-hidden-focusable" href="#main-content">{{ label_skip_to_content }}</a>
  {% include 'main/app-sidebar.tpl.html' %}
  <main class="main-content" id="main-content">
    {% include 'main/app-navbar.tpl.html' %}
    <div class="container-fluid content-inner pb-0">{{ html_content|raw }}</div>
  </main>
</body>
```

Load `hope-ui.min.css`, `dark.min.css`, `app-shell.css`, `bootstrap.bundle.min.js`, and `app-shell.js`. Load `apexcharts.min.js` only when a controller sets `needs_charts=true`. `app-shell.js` must use native DOM APIs for sidebar toggle, theme selection, password visibility, dismissible alerts, and confirmation modals.

Update `AbstractController::createHTMLMenu()` to add stable `key`, `url`, `label`, `icon`, and `active` fields for Dashboard, Servidores, Registro, Usuarios, Configuración, Actualizar, and Perfil without emitting HTML in PHP.

- [ ] **Step 4: Run shell tests**

Run: `vendor/bin/phpunit tests/Unit/TemplateAssetTest.php tests/Unit/Ui/HopeAssetPolicyTest.php`

Expected: all focused tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/templates/default/main src/templates/default/static/css/app-shell.css src/templates/default/static/js/app-shell.js src/psm/Module/AbstractController.php tests/Unit/TemplateAssetTest.php
git commit -m "feat: replace legacy shell with Hope UI"
```

### Task 4: Build reusable form and card components

**Files:**
- Create: `src/templates/default/main/components.tpl.html`
- Modify: `src/templates/default/main/macros.tpl.html`
- Create: `tests/Unit/Ui/FormFieldCoverageTest.php`

- [ ] **Step 1: Create a failing field-coverage test**

The test must scan rendered/source Twig and assert that these controller-owned fields remain present:

- Server: `label`, `ip`, `port`, `timeout`, `type`, `request_method`, `post_field`, `pattern`, `pattern_online`, `redirect_check`, `allow_http_status`, `header_name`, `header_value`, `custom_header`, `warning_threshold`, `ssl_cert_expiry_days`, `website_username`, `website_password`, `active`, `email`, `sms`, `discord`, `webhook`, `pushover`, `telegram`, `user_id`.
- User/profile: `name`, `user_name`, `password`, `password_repeat`, `level`, `mobile`, `discord`, `webhook_url`, `webhook_json`, `pushover_key`, `pushover_device`, `telegram_id`, `email`, `server_id`.
- Configuration: every entry currently declared in `ConfigController::$checkboxes`, `$fields`, and `$encryptedFields`, plus `language`, `site_title`, `sms_gateway`, `alert_type`, `authdir_defaultrole`, `authdir_type`, `email_smtp_security`, `auto_refresh_servers`, `log_retention_period`, and `password_encrypt_key`.

- [ ] **Step 2: Run the test and confirm components do not yet exist**

Run: `vendor/bin/phpunit tests/Unit/Ui/FormFieldCoverageTest.php`

Expected: failure naming the first missing component/template contract.

- [ ] **Step 3: Implement accessible macros**

`components.tpl.html` must export `card`, `text`, `password`, `select`, `switch`, `textarea`, `dropzone`, `status_badge`, `empty_state`, and `pagination`. Each field macro receives an explicit `id`, `name`, `label`, `value`, `help`, `required`, `error`, and `autocomplete`; labels use `for`, help/error IDs are attached through `aria-describedby`, and errors set `aria-invalid="true"`.

Keep compatibility macro names in `macros.tpl.html`, but have them call the new macros so controllers can migrate without a flag day.

- [ ] **Step 4: Run field coverage and Twig syntax smoke tests**

Run: `vendor/bin/phpunit tests/Unit/Ui/FormFieldCoverageTest.php tests/Unit/TemplateAssetTest.php`

Expected: all focused tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/templates/default/main/components.tpl.html src/templates/default/main/macros.tpl.html tests/Unit/Ui/FormFieldCoverageTest.php
git commit -m "feat: add accessible Hope UI components"
```

### Task 5: Redesign authentication and user management

**Files:**
- Modify: `src/templates/default/module/user/login.tpl.html`
- Modify: `src/templates/default/module/user/forgot.tpl.html`
- Modify: `src/templates/default/module/user/reset.tpl.html`
- Modify: `src/templates/default/module/user/profile.tpl.html`
- Modify: `src/templates/default/module/user/user/list.tpl.html`
- Modify: `src/templates/default/module/user/user/update.tpl.html`
- Create: `tests/Unit/Ui/AuthTemplateTest.php`

- [ ] **Step 1: Write failing authentication view assertions**

Assert that login, forgot-password, and reset-password forms contain one H1, visible labels, correct `autocomplete` values, CSRF input, inline message region with `aria-live="polite"`, and no public registration link. Assert user list entries are `<article class="card">` elements rather than rows.

- [ ] **Step 2: Run and confirm failures**

Run: `vendor/bin/phpunit tests/Unit/Ui/AuthTemplateTest.php`

Expected: failures on the legacy form structure.

- [ ] **Step 3: Convert authentication and user templates**

Use a split Hope UI authentication layout on desktop and a single centered card on mobile. Preserve login, remember-me, forgot/reset flows and existing controller URLs. Add the appearance panel to Profile. Render users as searchable cards with identity, role badge, channel chips, assigned-server count, and explicit Edit/Delete actions; do not create a table.

- [ ] **Step 4: Run auth and field tests**

Run: `vendor/bin/phpunit tests/Unit/Ui/AuthTemplateTest.php tests/Unit/Ui/FormFieldCoverageTest.php`

Expected: all focused tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/templates/default/module/user tests/Unit/Ui/AuthTemplateTest.php
git commit -m "feat: modernize authentication and user views"
```

### Task 6: Redesign server, log, configuration, PHP information, and installer views

**Files:**
- Modify: `src/templates/default/module/server/server/list.tpl.html`
- Modify: `src/templates/default/module/server/server/update.tpl.html`
- Modify: `src/templates/default/module/server/server/view.tpl.html`
- Modify: `src/templates/default/module/server/log.tpl.html`
- Modify: `src/templates/default/module/server/history.tpl.html`
- Modify: `src/templates/default/module/config/config.tpl.html`
- Modify: `src/templates/default/module/config/php_info.tpl.html`
- Modify: `src/templates/default/module/install/**/*.tpl.html`
- Create: `tests/Unit/Ui/ModernViewContractTest.php`

- [ ] **Step 1: Write failing view-contract tests**

Assert server and user collections use card grids; logs use filter chips plus timeline cards; configuration uses an accessible vertical tablist; PHP information uses grouped definition cards; installer steps use a progress stepper. Reject `<table` in collection templates while allowing compact key/value tables only inside diagnostics.

- [ ] **Step 2: Run and confirm the legacy views fail**

Run: `vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php`

Expected: failure for legacy table/list markup.

- [ ] **Step 3: Convert every view without removing fields**

Use responsive `row-cols-1 row-cols-md-2 row-cols-xl-3` card grids and server-side query filters. Group the server form into Identification, Check, Authentication/HTTP, Alert policy, Channels, and Access. Group configuration into General, Authentication, Email, SMS, Pushover, Telegram, Discord, Webhook, PWA/Push, Updates, and PHP Information. Display sensitive fields as password inputs with “leave blank to keep current” behavior.

- [ ] **Step 4: Run the full UI contract suite**

Run: `vendor/bin/phpunit tests/Unit/Ui tests/Unit/TemplateAssetTest.php`

Expected: all UI tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/templates/default/module tests/Unit/Ui/ModernViewContractTest.php
git commit -m "feat: convert application views to responsive cards"
```

### Task 7: Verify the UI plan as an independent integration gate

**Files:**
- Modify if needed: `src/templates/default/static/css/app-shell.css`
- Modify if needed: `src/templates/default/static/js/app-shell.js`

- [ ] **Step 1: Run automated verification**

Run: `composer validate --strict`

Run: `vendor/bin/phpunit`

Run: `vendor/bin/phpstan analyse`

Expected: Composer valid, complete PHPUnit suite green, PHPStan exit 0.

- [ ] **Step 2: Run the local PHP server**

Run: `php -S 127.0.0.1:8080`

Expected: the login route responds without PHP warnings; authenticated routes are exercised with a disposable local database, not production data.

- [ ] **Step 3: Perform responsive and theme visual QA**

Using the browser inspector, capture Dashboard, Servidores, Registro, Usuarios, Configuración, Actualizar, Perfil, Login, Olvidé contraseña, and Reset at 390×844, 768×1024, and 1440×900 in light and dark modes. Confirm no horizontal scroll, clipped labels, low-contrast text, or inaccessible focus states.

- [ ] **Step 4: Verify the field inventory one final time**

Run: `vendor/bin/phpunit tests/Unit/Ui/FormFieldCoverageTest.php --testdox`

Expected: every field group reports pass.

- [ ] **Step 5: Commit only evidence-driven adjustments**

```bash
git add src/templates/default/static/css/app-shell.css src/templates/default/static/js/app-shell.js
git commit -m "fix: complete responsive Hope UI verification"
```

## Plan Completion Criteria

- All authenticated and unauthenticated pages share one Hope UI shell.
- Light, dark, and auto schemes persist per user and render safely.
- All pre-4.1 fields and actions remain reachable.
- Server, user, and log collections use cards/timelines, never DataTables.
- Footer and repository links identify Hosting Supremo and the HS fork.
- Full PHPUnit and PHPStan checks pass before this branch can feed the integrated deployment.
