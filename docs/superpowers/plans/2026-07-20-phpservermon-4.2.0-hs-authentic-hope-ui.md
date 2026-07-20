# PHP Server Monitor 4.2.0-hs Authentic Hope UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the hand-built dashboard shell with the authentic supplied Hope UI 2.0 layout, separate Estado from Estadísticas, modernize every page and control, and prove zero horizontal overflow in light and dark modes.

**Architecture:** Keep the existing PHP/Twig controller architecture and translate the supplied Hope UI default Handlebars layout and partials into reusable Twig shell components. Vendor the exact required compiled Hope UI assets locally, exclude DataTables/jQuery bundles, persist validated appearance choices through the current user-preference service, and split live status from historical statistics at the controller/template boundary.

**Tech Stack:** PHP 8.5+, Twig 3.28, Hope UI 2.0 package supplied by the owner, Bootstrap 5.2.3, ApexCharts 3.27.1, vanilla JavaScript, PHPUnit 12, PHPStan 2.1.

## Global Constraints

- Target version is exactly `4.2.0-hs`; all Hosting Supremo versions retain the `-hs` suffix.
- The supplied ZIP with SHA-256 `4c1838f231fe506a13c63aba007f508ea2c5ef67219c165e3020f26c9d6a5e7a` is the visual source of truth.
- Use the authentic Hope UI class hierarchy and settings offcanvas; do not embed an iframe or separate frontend.
- Do not load or initialize DataTables, jQuery, `libs.min.js`, or any unused template plugin.
- Preserve all existing fields, routes, permissions, CSRF protections, server data, notifications, PWA behavior, and signed-update behavior.
- Estado displays server cards before historical statistics; Estadísticas is a separate top-level route.
- Light, dark, and auto modes must work on authenticated, anonymous, installer, maintenance, and error pages.
- At 360, 390, 768, 1024, 1366, and 1600 pixels, `scrollWidth <= clientWidth` on every tested page.
- Publish to the Hosting Supremo fork's `main` branch and deploy to the VPS only after local tests and GitHub Actions pass; do not create a GitHub Release.

---

### Task 1: Vendor the authentic Hope UI runtime

**Files:**
- Modify: `tests/Unit/Ui/HopeAssetPolicyTest.php`
- Replace: `src/templates/default/static/hope/css/hope-ui.min.css`
- Replace: `src/templates/default/static/hope/css/dark.min.css`
- Create: `src/templates/default/static/hope/css/customizer.min.css`
- Create: `src/templates/default/static/hope/js/hope-ui.js`
- Create: `src/templates/default/static/hope/js/setting.js`
- Create: `src/templates/default/static/hope/images/shapes/01.png`
- Create: `src/templates/default/static/hope/images/shapes/02.png`
- Create: `src/templates/default/static/hope/images/shapes/03.png`
- Create: `src/templates/default/static/hope/images/shapes/04.png`
- Create: `src/templates/default/static/hope/images/shapes/05.png`
- Create: `src/templates/default/static/hope/images/settings/light/01.png` through `13.png`
- Create: `src/templates/default/static/hope/images/settings/dark/01.png` through `13.png`
- Modify: `THIRD_PARTY_NOTICES.md`

**Interfaces:**
- Consumes: the owner-supplied ZIP under `hope-ui-html-2.0/html/assets/`.
- Produces: local, versioned Hope UI CSS/JS/images referenced by the Twig shell; no runtime CDN dependency.

- [ ] **Step 1: Extend the asset policy test and verify RED**

Add exact assertions:

```php
public function testAuthenticCustomizerAssetsAreVendoredWithoutDataTables(): void
{
    $root = dirname(__DIR__, 3);
    foreach ([
        '/src/templates/default/static/hope/css/hope-ui.min.css',
        '/src/templates/default/static/hope/css/dark.min.css',
        '/src/templates/default/static/hope/css/customizer.min.css',
        '/src/templates/default/static/hope/js/hope-ui.js',
        '/src/templates/default/static/hope/js/setting.js',
        '/src/templates/default/static/hope/images/shapes/01.png',
    ] as $file) {
        self::assertFileExists($root . $file, $file);
    }

    $runtime = file_get_contents($root . '/src/templates/default/static/hope/js/setting.js');
    self::assertIsString($runtime);
    self::assertStringContainsString('data-setting', $runtime);
    self::assertStringNotContainsString('DataTable', $runtime);
    self::assertStringNotContainsString('$.fn', $runtime);
}
```

Run:

```powershell
php vendor/bin/phpunit tests/Unit/Ui/HopeAssetPolicyTest.php
```

Expected: FAIL because `customizer.min.css`, `hope-ui.js`, `setting.js`, and the settings images are absent.

- [ ] **Step 2: Import only the approved package files**

Extract the supplied ZIP to a disposable directory under `work/reference/hope-ui-2.0`, verify the ZIP SHA-256 first, and copy the exact files listed above. Do not copy `assets/js/core/libs.min.js`, `assets/js/core/external.min.js`, `assets/css/core/libs.min.css`, table assets, calendars, maps, demo avatars, or demo business images.

Keep the supplied copyright banners intact. Update `THIRD_PARTY_NOTICES.md` to state that Hope UI 2.0 source package assets are redistributed under MIT and that DataTables/jQuery demo bundles are excluded.

- [ ] **Step 3: Verify GREEN and repository hygiene**

Run:

```powershell
php vendor/bin/phpunit tests/Unit/Ui/HopeAssetPolicyTest.php
rg -n "DataTable|data-table|jquery" src/templates/default/static/hope
git diff --check
```

Expected: PHPUnit PASS; `rg` returns no DataTables/jQuery runtime reference; diff check is clean.

- [ ] **Step 4: Commit**

```powershell
git add tests/Unit/Ui/HopeAssetPolicyTest.php src/templates/default/static/hope THIRD_PARTY_NOTICES.md
git commit -m "build: vendor authentic Hope UI 2.0 runtime"
```

---

### Task 2: Build the authentic shell and complete appearance model

**Files:**
- Modify: `tests/Unit/Ui/AppShellTest.php`
- Modify: `tests/Unit/Ui/AppearanceServiceTest.php`
- Modify: `src/psm/Service/Ui/Appearance.php`
- Modify: `src/psm/Service/Ui/AppearanceService.php`
- Modify: `src/psm/Module/AbstractController.php`
- Replace: `src/templates/default/main/body.tpl.html`
- Replace: `src/templates/default/main/menu.tpl.html`
- Replace: `src/templates/default/main/app-navbar.tpl.html`
- Create: `src/templates/default/main/appearance-customizer.tpl.html`
- Create: `src/templates/default/main/hope-icons.tpl.html`
- Modify: `src/templates/default/main/components.tpl.html`
- Replace: `src/templates/default/static/css/app-shell.css`
- Replace: `src/templates/default/static/js/app-shell.js`

**Interfaces:**
- Consumes: authentic assets from Task 1 and existing `users_preferences` persistence.
- Produces: `appearance` Twig global with `scheme`, `resolved_scheme`, `accent`, `direction`, `sidebar`, `sidebar_types`, `sidebar_active`, and `navbar`; visible `[data-theme-quick-toggle]`; offcanvas `#hope-ui-settings`.

- [ ] **Step 1: Write failing shell and preference tests**

Assert the authentic contracts:

```php
public function testShellUsesAuthenticHopeUiHierarchyAndControls(): void
{
    $body = $this->read('main/body.tpl.html');
    $navbar = $this->read('main/app-navbar.tpl.html');
    $customizer = $this->read('main/appearance-customizer.tpl.html');

    self::assertStringContainsString('sidebar sidebar-default sidebar-white sidebar-base', $body);
    self::assertStringContainsString('<main class="main-content">', $body);
    self::assertStringContainsString('iq-navbar-header', $body);
    self::assertStringContainsString('content-inner mt-n5 py-0', $body);
    self::assertStringContainsString('data-theme-quick-toggle', $navbar);
    self::assertStringContainsString('data-bs-target="#hope-ui-settings"', $body);
    foreach (['auto', 'dark', 'light'] as $scheme) {
        self::assertStringContainsString('data-value="' . $scheme . '"', $customizer);
    }
}
```

Extend `AppearanceServiceTest` with invalid-value fallback and round-trip cases for `ui_sidebar_types`, `ui_sidebar_active`, and `ui_navbar`.

Run:

```powershell
php vendor/bin/phpunit tests/Unit/Ui/AppShellTest.php tests/Unit/Ui/AppearanceServiceTest.php
```

Expected: FAIL on the missing authentic hierarchy and new preference fields.

- [ ] **Step 2: Expand the validated appearance value object**

Add allowlists in `Appearance.php`:

```php
private const SCHEMES = ['auto', 'dark', 'light'];
private const ACCENTS = ['default', 'blue', 'gray', 'red', 'yellow', 'pink'];
private const DIRECTIONS = ['ltr', 'rtl'];
private const SIDEBARS = ['default', 'dark', 'color', 'transparent'];
private const SIDEBAR_TYPES = ['mini', 'hover', 'boxed'];
private const SIDEBAR_ACTIVE = ['rounded-one-side', 'rounded-all', 'pill-one-side', 'pill-all'];
private const NAVBARS = ['default', 'glass', 'color', 'sticky', 'transparent'];
```

Normalize each posted scalar/array against these lists. `AppearanceService::saveForCurrentUser()` writes the exact `ui_*` preferences and returns the resolved object. Auto mode resolves from the browser only for initial painting; the server emits `data-ui-scheme="auto"`.

- [ ] **Step 3: Translate the supplied default layout and settings partial to Twig**

Use the supplied `default.hbs`, `sidebar.hbs`, `header.hbs`, `footer.hbs`, and `setting-offcanvas.hbs` as the line-by-line structure. Replace demo content with existing PHP Server Monitor variables while retaining Hope UI classes and SVG icon dimensions.

The body contract is:

```twig
<body class="{{ appearance.body_classes }}">
  {% if html_menu is defined and html_menu %}
    <aside class="sidebar sidebar-default sidebar-white sidebar-base {{ appearance.sidebar_classes }}">
      {{ html_menu|raw }}
    </aside>
  {% endif %}
  <main class="main-content">
    {% include 'main/app-navbar.tpl.html' %}
    <div class="iq-navbar-header p-3 p-md-4">
      <div class="container-fluid"><h1 class="h3 mb-0">{{ subtitle }}</h1></div>
    </div>
    <div class="container-fluid content-inner mt-n5 py-0">
      <div id="content" class="app-content">{{ html_content|raw }}</div>
    </div>
    <footer class="footer">{{ version }} · Mejorado por Hosting Supremo</footer>
  </main>
  {% include 'main/appearance-customizer.tpl.html' %}
</body>
```

The quick toggle and gear must be real `<button>` elements with unique accessible names. Keep PWA install, notification, version, profile, and logout actions.

- [ ] **Step 4: Implement theme/customizer behavior using the supplied contracts**

Adapt `setting.js` so its `data-setting` events call a small `app-shell.js` preference adapter. The quick control changes only light/dark; the offcanvas supports auto and all approved appearance fields. Submit preference changes to the existing profile endpoint with CSRF and update these targets immediately:

```javascript
document.documentElement.dataset.bsTheme = resolved;
document.documentElement.classList.toggle('dark', resolved === 'dark');
document.body.classList.toggle('dark', resolved === 'dark');
document.dispatchEvent(new CustomEvent('psm:theme-changed', {
    detail: { scheme: selected, resolved: resolved }
}));
```

On failure, restore the last acknowledged preference and show a Bootstrap/Hope UI toast. Never expose CSRF or preference payloads through URLs.

- [ ] **Step 5: Verify and commit**

```powershell
php vendor/bin/phpunit tests/Unit/Ui/AppShellTest.php tests/Unit/Ui/AppearanceServiceTest.php tests/Unit/Ui/TwigTemplateSyntaxTest.php
php vendor/bin/phpstan analyse --no-progress
git diff --check
git add src/psm/Service/Ui src/psm/Module/AbstractController.php src/templates/default/main src/templates/default/static/css/app-shell.css src/templates/default/static/js/app-shell.js tests/Unit/Ui
git commit -m "feat: integrate authentic Hope UI shell and themes"
```

Expected: targeted tests and PHPStan pass; no template syntax errors.

---

### Task 3: Separate Estado from Estadísticas

**Files:**
- Modify: `tests/Unit/Ui/ModernViewContractTest.php`
- Create: `tests/Unit/Statistics/StatisticsControllerContractTest.php`
- Modify: `src/psm/Module/Server/ServerModule.php`
- Modify: `src/psm/Module/AbstractController.php`
- Modify: `src/psm/Module/Server/Controller/StatusController.php`
- Create: `src/psm/Module/Server/Controller/StatisticsController.php`
- Replace: `src/templates/default/module/server/status/index.tpl.html`
- Replace: `src/templates/default/module/server/status/header.tpl.html`
- Modify: `src/templates/default/module/server/status/cards.tpl.html`
- Create: `src/templates/default/module/server/statistics/index.tpl.html`
- Create: `src/templates/default/module/server/statistics/header.tpl.html`
- Create: `src/templates/default/static/js/status.js`
- Replace: `src/templates/default/static/js/dashboard.js`
- Modify: `src/lang/es_ES.lang.php`
- Modify: `src/lang/en_US.lang.php`

**Interfaces:**
- Consumes: `DashboardStatistics`, `StatisticsRange`, status JSON update action, and permission-scoped server queries.
- Produces: `?mod=server_status` for operational cards and `?mod=server_statistics` for KPIs/charts.

- [ ] **Step 1: Write RED route and ordering tests**

```php
public function testEstadoRendersServersBeforeAnyStatistics(): void
{
    $status = $this->read('module/server/status/index.tpl.html');
    self::assertStringContainsString('data-status-board', $status);
    self::assertStringNotContainsString('uptime-chart', $status);
    self::assertStringNotContainsString('latency-chart', $status);
    self::assertStringNotContainsString('dashboard-summary', $status);
}

public function testStatisticsOwnsKpisAndCharts(): void
{
    $statistics = $this->read('module/server/statistics/index.tpl.html');
    self::assertStringContainsString('data-statistics-dashboard', $statistics);
    self::assertStringContainsString('uptime-chart', $statistics);
    self::assertStringContainsString('latency-chart', $statistics);
    self::assertStringContainsString('dashboard-summary', $statistics);
}
```

The controller contract test requires `ServerModule` to map `statistics` and the menu builder to expose `server_statistics` immediately after `server_status`.

Run:

```powershell
php vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php tests/Unit/Statistics/StatisticsControllerContractTest.php
```

Expected: FAIL because the statistics controller/template/menu entry do not exist and Estado still contains charts.

- [ ] **Step 2: Make StatusController operational-only**

Remove statistics queries and chart globals from `StatusController::executeIndex()`. Preserve server image/status view models, layout preference, manual update URL, and auto-refresh. Render cards directly after the compact header. `status.js` owns manual/automatic status refresh and updates the existing cards in place.

- [ ] **Step 3: Add the permission-aware statistics controller**

`StatisticsController` accepts `index` and `snapshot`, validates `StatisticsRange`, calls the existing `DashboardStatistics` with the current user ID/admin flag, emits `dashboard_json`, and marks `needs_charts` plus `needs_dashboard` true. `snapshot` remains GET-only with private no-store headers.

Register the module and menu icon:

```php
'statistics' => __NAMESPACE__ . '\\Controller\\StatisticsController',
```

```php
'server_statistics' => 'chart-line',
```

- [ ] **Step 4: Build the Hope UI statistics page and live chart theming**

Use a Hope UI hero/header, responsive KPI cards, a 7/5 desktop chart split, empty-state cards, time range, incident summary, and recent activity. On `psm:theme-changed`, destroy and redraw ApexCharts with the resolved mode; never stack duplicate chart instances.

- [ ] **Step 5: Verify and commit**

```powershell
php vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php tests/Unit/Statistics tests/Unit/Ui/TwigTemplateSyntaxTest.php
php vendor/bin/phpstan analyse --no-progress
git add src/psm/Module/Server src/psm/Module/AbstractController.php src/templates/default/module/server/status src/templates/default/module/server/statistics src/templates/default/static/js/status.js src/templates/default/static/js/dashboard.js src/lang/es_ES.lang.php src/lang/en_US.lang.php tests/Unit
git commit -m "feat: separate live status from statistics"
```

---

### Task 4: Rebuild server list, editor, detail, and history

**Files:**
- Modify: `tests/Unit/Ui/ModernViewContractTest.php`
- Modify: `tests/Unit/TemplateAssetTest.php`
- Replace: `src/templates/default/module/server/server/list.tpl.html`
- Replace: `src/templates/default/module/server/server/update.tpl.html`
- Replace: `src/templates/default/module/server/server/view.tpl.html`
- Replace: `src/templates/default/module/server/history.tpl.html`
- Replace: `src/templates/default/static/js/history.js`
- Modify: `src/templates/default/static/css/app-shell.css`

**Interfaces:**
- Consumes: existing server controller template data, image URLs, logs, history datasets, CSRF, and edit/delete/status actions.
- Produces: authentic Hope UI cards/forms/tabs without fixed viewport widths or Bootstrap 4-only utilities.

- [ ] **Step 1: Add failing legacy/overflow contract tests**

```php
public function testServerViewsContainNoLegacyFixedWidthOrBootstrapFourUtilities(): void
{
    foreach ([
        'module/server/server/list.tpl.html',
        'module/server/server/update.tpl.html',
        'module/server/server/view.tpl.html',
        'module/server/history.tpl.html',
    ] as $template) {
        $html = $this->read($template);
        self::assertDoesNotMatchRegularExpression('/width\s*:\s*\d+vw/i', $html, $template);
        self::assertDoesNotMatchRegularExpression('/\b(?:pl|pr|ml|mr|float)-(?:0|auto|left|right)\b/', $html, $template);
    }
}
```

Add assertions for `server-detail-grid`, `server-detail-tabs`, responsive button wrapping, fixed image frame, semantic tab controls, and Bootstrap 5 `ps/pe/ms/me` utilities.

Run:

```powershell
php vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php tests/Unit/TemplateAssetTest.php
```

Expected: FAIL on current legacy `pl-0`, `pr-0`, `ml-auto`, `float-right`, and `width:60vw` markup.

- [ ] **Step 2: Rebuild server list and editor from Hope UI cards/forms**

Use cards with `row g-4`, fixed image media, responsive action toolbars, filter chips, and no HTML table. The editor groups General, Check, Authentication, Notifications, Advanced, and Image sections with the existing field names unchanged. Reuse Twig components for inputs, selects, switches, secret inputs, validation help, file drop, and save/cancel actions.

- [ ] **Step 3: Rebuild server detail and history**

The first detail row uses a responsive status summary and identity card. A Bootstrap 5 tablist exposes Overview, History, Incidents, Output, Notifications, and Settings. Panels stack at mobile widths and remain keyboard navigable. History containers use `width:100%`, `min-width:0`, and `max-width:100%`; range controls wrap with `flex-wrap`.

Retain Chart.js compatibility only where the controller still supplies its existing dataset; remove inline layout styling and let `history.js` update the selected scale using native DOM APIs.

- [ ] **Step 4: Verify and commit**

```powershell
php vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php tests/Unit/TemplateAssetTest.php tests/Unit/Ui/FormFieldCoverageTest.php tests/Unit/Ui/TwigTemplateSyntaxTest.php
git diff --check
git add src/templates/default/module/server/server src/templates/default/module/server/history.tpl.html src/templates/default/static/js/history.js src/templates/default/static/css/app-shell.css tests/Unit
git commit -m "feat: rebuild server workflows with Hope UI"
```

---

### Task 5: Modernize every remaining page and control

**Files:**
- Modify: `tests/Unit/Ui/ModernViewContractTest.php`
- Modify: `tests/Unit/Ui/FormFieldCoverageTest.php`
- Modify: `tests/Unit/Ui/TwigTemplateSyntaxTest.php`
- Replace: `src/templates/default/module/server/log.tpl.html`
- Replace: `src/templates/default/module/user/user/list.tpl.html`
- Replace: `src/templates/default/module/user/user/update.tpl.html`
- Replace: `src/templates/default/module/user/profile.tpl.html`
- Replace: `src/templates/default/module/user/notification/index.tpl.html`
- Replace: `src/templates/default/module/user/login/login.tpl.html`
- Replace: `src/templates/default/module/user/login/forgot.tpl.html`
- Replace: `src/templates/default/module/user/login/reset.tpl.html`
- Replace: `src/templates/default/module/user/login/register.tpl.html`
- Replace: `src/templates/default/module/config/config.tpl.html`
- Replace: `src/templates/default/module/config/system.tpl.html`
- Replace: `src/templates/default/module/config/system-updated.tpl.html`
- Replace: `src/templates/default/module/install/index.tpl.html`
- Replace: `src/templates/default/module/install/main.tpl.html`
- Replace: `src/templates/default/module/install/config_new.tpl.html`
- Replace: `src/templates/default/module/install/config_new_user.tpl.html`
- Replace: `src/templates/default/module/install/config_upgrade.tpl.html`
- Replace: `src/templates/default/module/install/results.tpl.html`
- Replace: `src/templates/default/module/install/success.tpl.html`
- Replace: `src/templates/default/module/error/401.tpl.html`
- Replace: `src/templates/default/util/module/modal.tpl.html`
- Replace: `src/templates/default/util/module/sidebar.tpl.html`
- Modify: `src/templates/default/main/macros.tpl.html`
- Modify: `src/templates/default/main/components.tpl.html`
- Modify: `src/templates/default/static/css/app-shell.css`

**Interfaces:**
- Consumes: every existing controller key/action and shared authentic shell/components.
- Produces: consistent Hope UI cards, forms, alerts, badges, buttons, modals, steppers, timelines, auth layouts, and empty states across the complete product.

- [ ] **Step 1: Extend field and modern-view tests; verify RED**

Add a provider containing every template above. For each rendered-source file, reject Bootstrap 4 spacing, inline fixed width, `<table`, `data-toggle=`, and button elements without one of the approved Hope UI/Bootstrap classes. Require `card`, `form-control|form-select|form-check-input`, `btn`, and responsive grid contracts where appropriate.

Keep `FormFieldCoverageTest` authoritative: compare controller-accepted field names against template `name=` attributes so no configuration, user, server, invitation, VAPID, SMTP, Telegram, updater, or installer field disappears.

Run:

```powershell
php vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php tests/Unit/Ui/FormFieldCoverageTest.php
```

Expected: FAIL on remaining legacy page markup.

- [ ] **Step 2: Rebuild shared components and authentication pages**

Define authentic components for button variants, icon buttons, input groups, secrets, switches, selects, textareas, file fields, cards, badges, alerts, empty states, pagination, timelines, tabs, modals, and steppers. Authentication pages follow the supplied Hope UI sign-in/recover layouts, use Hosting Supremo branding, and preserve remember, reset, invitation, CSRF, and validation behavior.

- [ ] **Step 3: Convert users, logs, notifications, configuration, and updater**

Use responsive card/list views without tables. Configuration keeps its complete section navigation but uses Hope UI nav pills and cards. Provider test buttons remain adjacent to their channel settings. System diagnostics and updater use summary cards and the existing safe install stepper; no updater logic changes in this task.

- [ ] **Step 4: Convert installer, errors, modal, and utility sidebar**

Use the supplied Hope UI auth/error/form visuals. Installer steps remain server-rendered and preserve all field names. Modals use Bootstrap 5 attributes and remain viewport-scrollable. The unauthorized page keeps status semantics and a safe back action.

- [ ] **Step 5: Verify and commit**

```powershell
php vendor/bin/phpunit tests/Unit/Ui tests/Unit/TemplateAssetTest.php
php vendor/bin/phpstan analyse --no-progress
git diff --check
git add src/templates/default tests/Unit/Ui tests/Unit/TemplateAssetTest.php
git commit -m "feat: modernize all pages with authentic Hope UI"
```

---

### Task 6: Add responsive/theme regression contracts and browser matrix

**Files:**
- Create: `tests/Unit/Ui/ResponsiveLayoutContractTest.php`
- Create: `dev/verify-hope-ui-layout.mjs`
- Modify: `.github/workflows/ci.yml`
- Modify: `src/templates/default/static/css/app-shell.css`
- Modify: `src/templates/default/static/js/app-shell.js`

**Interfaces:**
- Consumes: all pages from Tasks 2–5 and authenticated local/staging URLs.
- Produces: static overflow guardrails plus a browser audit that reports route, theme, width, `scrollWidth`, overflowing selectors, and missing controls.

- [ ] **Step 1: Write the failing responsive contract**

```php
public function testStylesDoNotHidePageOverflowOrUseViewportWidthForContent(): void
{
    $css = file_get_contents(dirname(__DIR__, 3) . '/src/templates/default/static/css/app-shell.css');
    self::assertIsString($css);
    self::assertStringNotContainsString('body { overflow-x: hidden', $css);
    self::assertDoesNotMatchRegularExpression('/(?:width|min-width)\s*:\s*\d+vw/', $css);
    self::assertStringContainsString('min-width: 0', $css);
    self::assertStringContainsString('max-width: 100%', $css);
}
```

Run and expect RED because the current CSS hides horizontal overflow.

- [ ] **Step 2: Implement the Playwright audit script**

`dev/verify-hope-ui-layout.mjs` accepts `PSM_BASE_URL`, `PSM_TEST_USER`, and `PSM_TEST_PASSWORD` from the environment. It logs in through the visible form, visits the explicit route list, switches light and dark using `[data-theme-quick-toggle]` and the customizer, and runs this page evaluation at every target width:

```javascript
const result = await page.evaluate(() => ({
  viewport: document.documentElement.clientWidth,
  scrollWidth: document.documentElement.scrollWidth,
  overflows: [...document.querySelectorAll('body *')]
    .map((element) => {
      const box = element.getBoundingClientRect();
      return { selector: element.id || element.className || element.tagName, left: box.left, right: box.right };
    })
    .filter((item) => item.left < -1 || item.right > document.documentElement.clientWidth + 1)
    .slice(0, 20),
}));
```

The script exits nonzero when `scrollWidth > viewport`, an overflow list is nonempty, the quick control/settings gear is absent, a route returns an error page, or a theme does not change.

- [ ] **Step 3: Fix root causes exposed by RED results**

Use `min-width:0`, `max-width:100%`, `overflow-wrap:anywhere`, responsive Bootstrap columns, wrapping toolbars, fluid chart containers, and offcanvas/mobile sidebar rules. Remove `overflow-x:hidden` from `body`; each offending child must be corrected.

- [ ] **Step 4: Add CI-safe static checks and run the browser matrix locally**

CI runs `ResponsiveLayoutContractTest` and verifies the audit script parses with `node --check`; it does not require production credentials. Run the full browser script against a local disposable database in both authenticated roles, then repeat against staging.

Expected report: all target routes/themes/widths have equal viewport and scroll width with zero overflow selectors.

- [ ] **Step 5: Commit**

```powershell
git add tests/Unit/Ui/ResponsiveLayoutContractTest.php dev/verify-hope-ui-layout.mjs .github/workflows/ci.yml src/templates/default/static/css/app-shell.css src/templates/default/static/js/app-shell.js
git commit -m "test: enforce responsive Hope UI layouts"
```

---

### Task 7: Update version, cache policy, and documentation

**Files:**
- Modify: `tests/Unit/VersionTest.php`
- Modify: `tests/Unit/DocumentationContractTest.php`
- Modify: `src/includes/psmconfig.inc.php`
- Modify: `README.rst`
- Modify: `CHANGELOG.md`
- Modify: `CHANGELOG.rst`
- Modify: `service-worker.js`
- Modify: `manifest.webmanifest`

**Interfaces:**
- Consumes: completed 4.2.0-hs implementation and vendored asset paths.
- Produces: correct displayed version, PWA cache invalidation, and owner-facing setup/use documentation.

- [ ] **Step 1: Set RED version/documentation expectations**

Set `VersionTest` to require `4.2.0-hs`. Require README/CHANGELOG text for authentic Hope UI 2.0, quick theme toggle, settings gear, separate Estadísticas page, supported widths, and DataTables exclusion.

Run:

```powershell
php vendor/bin/phpunit tests/Unit/VersionTest.php tests/Unit/DocumentationContractTest.php
```

Expected: FAIL while code/docs still report 4.1.0-hs.

- [ ] **Step 2: Update version and PWA assets**

Set `PSM_VERSION` and persisted upgrade version to `4.2.0-hs`. Increment the service-worker cache name and precache the new Hope UI customizer/runtime assets. Preserve network-first authenticated navigation, no POST caching, and logout cache clearing.

- [ ] **Step 3: Update README and changelogs**

Document theme controls, per-user persistence, Estado/Estadísticas split, responsive behavior, template attribution, PHP 8.5+, upgrade process, and absence of a 4.2.0-hs release until owner approval.

- [ ] **Step 4: Verify and commit**

```powershell
php vendor/bin/phpunit tests/Unit/VersionTest.php tests/Unit/DocumentationContractTest.php tests/Unit/Pwa/PwaAssetTest.php
php -r "json_decode(file_get_contents('manifest.webmanifest'), true, 512, JSON_THROW_ON_ERROR);"
git add src/includes/psmconfig.inc.php README.rst CHANGELOG.md CHANGELOG.rst service-worker.js manifest.webmanifest tests/Unit
git commit -m "docs: prepare PHP Server Monitor 4.2.0-hs"
```

---

### Task 8: Full verification, GitHub main publication, and VPS deployment

**Files:**
- Verify: complete repository
- Publish: current branch to `origin/main`
- Deploy: signed-compatible application tree to `/home/hostin/public_html`

**Interfaces:**
- Consumes: all previous commits and the existing production database/config/uploads.
- Produces: GitHub `main` and production VPS running the identical verified `4.2.0-hs` code, without a new GitHub Release.

- [ ] **Step 1: Run complete local verification**

```powershell
composer validate --strict
composer check-platform-reqs
composer audit
php vendor/bin/phpunit --colors=never
php vendor/bin/phpstan analyse --no-progress
node --check dev/verify-hope-ui-layout.mjs
git diff --check
git status --short
```

Expected: all commands exit 0; only the two intentionally skipped environment-dependent tests remain skipped; worktree is clean after commits.

- [ ] **Step 2: Run local browser acceptance**

Validate anonymous login/forgot/invitation pages and authenticated Estado, Estadísticas, Servidores list/create/edit/detail, Registro, Usuarios, Configurar, Sistema, Actualizar, Perfil, notification center, customizer, modals, and PWA install. Test both themes and every target width. Verify zero browser console errors attributable to the application.

- [ ] **Step 3: Push to the fork's main branch and wait for CI**

Confirm `origin` is `RicRey1988/phpservermon`, then push the integrated commit history to `main`. Inspect the exact GitHub Actions run for the pushed SHA and continue only when its conclusion is `success`. Do not create or publish a release/tag.

- [ ] **Step 4: Deploy atomically to the VPS**

Build the production Composer tree, exclude `.git`, tests, local config, logs, existing uploads, and temporary reference files, then use the existing updater/deployment allowlist to stage and atomically replace application code. Preserve `/home/hostin/public_html/config.php`, uploaded server images, logs, and database content. Run additive migrations, restore required SELinux labels/permissions, verify Apache syntax, and reload Apache only after checks pass.

- [ ] **Step 5: Run production acceptance**

Verify HTTPS 200, PHP 8.5, database, cron freshness, maintenance lock clear, PWA manifest/service worker/icons, theme persistence, quick toggle, settings gear, all navigation destinations, immediate status refresh, separate statistics, email/Telegram configuration visibility, and responsive overflow at desktop/mobile widths.

Compare critical deployed file hashes with the Git commit. If any acceptance check fails, stop and apply only an evidence-backed targeted correction with a failing regression test before republishing/redeploying.

- [ ] **Step 6: Report completion**

Provide the GitHub commit and successful Actions URL, production version, automated test totals, browser width/theme coverage, deployment health, release status (“no 4.2.0-hs release created”), and any administrator-only configuration still intentionally pending.
