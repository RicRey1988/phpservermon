# Hope UI Native Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the hybrid legacy interface with a complete Hope UI 2.0 shell and component system branded for Hosting Supremo.

**Architecture:** Keep the supplied Hope UI runtime unchanged and render application-specific Twig templates directly with Hope UI/Bootstrap 5 structures. A local SVG macro supplies the original icon language; a single scoped `hs-monitor.css` file styles only server-monitor concepts and derives every surface from theme variables.

**Tech Stack:** PHP 8.5, Twig, Hope UI HTML 2.0, Bootstrap 5, native JavaScript, PHPUnit 12, PHPStan.

## Global Constraints

- Retain Hosting Supremo / Server Monitor / HS branding.
- Remove legacy `custom.css`, `app-shell.css` and Font Awesome from the runtime and repository.
- Use no jQuery or DataTables.
- Support `data-bs-theme="light"` and `data-bs-theme="dark"` without mixed surfaces.
- Preserve all controller-owned form fields and application behavior.
- Prevent document overflow at 360, 390, 768, 1024, 1366 and 1600 px.
- Release and deploy as `4.3.0-hs` only after automated and browser verification.

---

### Task 1: Enforce the native Hope asset policy

**Files:**
- Modify: `tests/Unit/Ui/HopeAssetPolicyTest.php`
- Modify: `tests/Unit/Ui/AppShellTest.php`
- Modify: `tests/Unit/Ui/ModernViewContractTest.php`
- Modify: `tests/Unit/Ui/ResponsiveLayoutContractTest.php`
- Create: `src/templates/default/main/icons.tpl.html`
- Create: `src/templates/default/static/css/hs-monitor.css`
- Delete: `src/templates/default/static/css/custom.css`
- Delete: `src/templates/default/static/css/app-shell.css`

**Interfaces:**
- Produces: Twig macro `hope.icon(name, class = 'icon-20')` returning an accessible decorative inline SVG.
- Produces: one runtime stylesheet at `static/css/hs-monitor.css`.

- [ ] **Step 1: Write the failing asset-policy tests**

Add assertions that the shell contains `main/icons.tpl.html` and `hs-monitor.css`, and recursively reject `fas fa-`, `data-fa-i2svg`, `plugin/font-awesome`, `custom.css` and `app-shell.css` from active Twig templates.

- [ ] **Step 2: Run the focused tests and confirm RED**

Run: `php vendor/bin/phpunit tests/Unit/Ui/HopeAssetPolicyTest.php tests/Unit/Ui/AppShellTest.php tests/Unit/Ui/ModernViewContractTest.php tests/Unit/Ui/ResponsiveLayoutContractTest.php --colors=never`

Expected: failures naming the legacy stylesheets and Font Awesome runtime.

- [ ] **Step 3: Add the SVG macro and scoped stylesheet**

Implement named icons used by the monitor (`category`, `chart`, `server`, `time`, `users`, `setting`, `refresh`, `profile`, `logout`, `search`, `moon`, `sun`, `notification`, `download`, `delete`, `image`, `show`, `hide`, `document`, `danger`, `tick`, `send`, `message`, `lock`) with the supplied Hope/Iconly view boxes and paths. Define Hope-token aliases and scoped `.hs-*` component rules without global hard-coded body/card/table backgrounds.

- [ ] **Step 4: Remove both legacy stylesheets and run GREEN**

Run the focused PHPUnit command from Step 2.

Expected: all focused tests pass.

### Task 2: Rebuild the Hope UI application shell

**Files:**
- Modify: `src/templates/default/main/body.tpl.html`
- Modify: `src/templates/default/main/app-navbar.tpl.html`
- Modify: `src/templates/default/main/menu.tpl.html`
- Modify: `src/templates/default/main/appearance-customizer.tpl.html`
- Modify: `src/templates/default/static/js/app-shell.js`
- Test: `tests/Unit/Ui/AppShellTest.php`
- Test: `tests/Unit/Ui/CompositeTemplateRenderingTest.php`

**Interfaces:**
- Consumes: `hope.icon()` and existing appearance-save endpoint.
- Produces: Hope sidebar/navbar/banner/customizer used by every page.

- [ ] **Step 1: Extend shell tests for exact Hope hierarchy and SVG controls**

Require `navs-rounded-all`, local SVG macro calls, the original sidebar toggle shape, quick theme control and absence of all Font Awesome scripts/classes.

- [ ] **Step 2: Run shell tests and confirm RED**

Run: `php vendor/bin/phpunit tests/Unit/Ui/AppShellTest.php tests/Unit/Ui/CompositeTemplateRenderingTest.php --colors=never`

- [ ] **Step 3: Port the original sidebar, header and banner structures**

Use the supplied Hope UI partial hierarchy, keep application URLs and data, and render every control through `hope.icon()`. Load only Hope UI CSS/JS, `hs-monitor.css` and native application scripts.

- [ ] **Step 4: Verify theme persistence and shell tests GREEN**

Run the command from Step 2 and `php vendor/bin/phpunit tests/Unit/Ui/AppearanceServiceTest.php --colors=never`.

### Task 3: Redesign Estado and server collections

**Files:**
- Modify: `src/templates/default/module/server/status/cards.tpl.html`
- Modify: `src/templates/default/module/server/status/index.tpl.html`
- Modify: `src/templates/default/module/server/server/list.tpl.html`
- Modify: `src/templates/default/module/server/server/view.tpl.html`
- Modify: `src/templates/default/static/js/status.js`
- Test: `tests/Unit/Ui/ModernViewContractTest.php`
- Test: `tests/Unit/Ui/CompositeTemplateRenderingTest.php`
- Test: `tests/Unit/TemplateAssetTest.php`

**Interfaces:**
- Preserves: `data-server-id`, `data-server-last-online`, `data-server-last-check`, `data-server-latency` for immediate status refresh.

- [ ] **Step 1: Add failing contracts for full names and Hope state cards**

Reject `text-truncate` on server names; require `hs-server-name`, bounded media, soft status accents and local SVG icons.

- [ ] **Step 2: Confirm RED with focused PHPUnit tests**

Run: `php vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php tests/Unit/Ui/CompositeTemplateRenderingTest.php tests/Unit/TemplateAssetTest.php --colors=never`

- [ ] **Step 3: Build the responsive server cards and detail composition**

Use a wrapping name, endpoint, status pill and aligned definition rows. Preserve the image upload/fallback contract and status-refresh DOM hooks.

- [ ] **Step 4: Run focused tests GREEN**

Run the command from Step 2.

### Task 4: Rebuild logs, configuration and form primitives

**Files:**
- Modify: `src/templates/default/module/server/log.tpl.html`
- Modify: `src/templates/default/module/config/config.tpl.html`
- Modify: `src/templates/default/main/macros.tpl.html`
- Modify: `src/templates/default/main/components.tpl.html`
- Test: `tests/Unit/Ui/ModernViewContractTest.php`
- Test: `tests/Unit/Ui/FormFieldCoverageTest.php`

**Interfaces:**
- Preserves every field name enumerated by `FormFieldCoverageTest`.
- Produces activity entries using `.profile-media` and configuration groups using `.hs-settings-card`.

- [ ] **Step 1: Write failing log/config structural tests**

Require `profile-media`, `hs-log-entry`, `hs-settings-nav`, `hs-settings-card`; reject the old `.timeline-item` grid and bordered fieldset presentation.

- [ ] **Step 2: Confirm RED**

Run: `php vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php tests/Unit/Ui/FormFieldCoverageTest.php --colors=never`

- [ ] **Step 3: Implement Hope activity and settings layouts**

Move tab titles into Hope card headers, keep semantic fieldsets borderless, standardize input groups and switches, and use SVG macro icons in warnings, search and actions.

- [ ] **Step 4: Run contracts GREEN**

Run the command from Step 2.

### Task 5: Port authentication to the supplied Hope layout

**Files:**
- Modify: `src/templates/default/module/user/login/login.tpl.html`
- Modify: `src/templates/default/module/user/login/forgot.tpl.html`
- Modify: `src/templates/default/module/user/login/reset.tpl.html`
- Modify: `src/templates/default/module/user/login/register.tpl.html`
- Add: `src/templates/default/static/hope/images/auth/01.png`
- Test: `tests/Unit/Ui/ModernViewContractTest.php`
- Test: `tests/Unit/Ui/ResponsiveLayoutContractTest.php`

**Interfaces:**
- Preserves authentication field names, CSRF, actions and autocomplete values.

- [ ] **Step 1: Add failing authentication composition tests**

Require `auth-card`, `auth-side-content`, the supplied `images/auth/01.png`, password SVG control and absence of Font Awesome.

- [ ] **Step 2: Confirm RED**

Run: `php vendor/bin/phpunit tests/Unit/Ui/ModernViewContractTest.php tests/Unit/Ui/ResponsiveLayoutContractTest.php --colors=never`

- [ ] **Step 3: Port login, recovery, reset and registration**

Use the original Hope two-column authentication geometry with Hosting Supremo branding and a single-column phone fallback.

- [ ] **Step 4: Run focused tests GREEN**

Run the command from Step 2.

### Task 6: Normalize every remaining view

**Files:**
- Modify: all remaining templates listed by `ModernViewContractTest::testEveryApplicationPageUsesModernHopeUiContracts`
- Modify: `src/templates/default/static/css/hs-monitor.css`
- Test: `tests/Unit/Ui/ModernViewContractTest.php`
- Test: `tests/Unit/Ui/TwigTemplateSyntaxTest.php`
- Test: `tests/Unit/Ui/ResponsiveLayoutContractTest.php`

**Interfaces:**
- Preserves controller inputs and route behavior.
- Produces one consistent Hope component vocabulary across every route.

- [ ] **Step 1: Add recursive failing contracts**

Reject `fa-`, legacy table/layout utilities, global palette rules and non-wrapping user content in all active Twig templates.

- [ ] **Step 2: Confirm RED**

Run: `php vendor/bin/phpunit tests/Unit/Ui --colors=never`.

- [ ] **Step 3: Convert remaining cards, actions, empty states and icons**

Update users, profile, notifications, statistics, system, updater, installer, history, modals and errors to Hope cards and the SVG macro.

- [ ] **Step 4: Run the complete UI suite GREEN**

Run: `php vendor/bin/phpunit tests/Unit/Ui --colors=never`.

### Task 7: Version, documentation and PWA cache

**Files:**
- Modify: `src/includes/psmconfig.inc.php`
- Modify: `service-worker.js`
- Modify: `README.rst`
- Modify: `CHANGELOG.md`
- Modify: `CHANGELOG.rst`
- Modify: `docs/conf.py`
- Modify: `docs/configuration-hs.rst`
- Modify: `THIRD_PARTY_NOTICES.md`
- Modify: version/documentation/PWA unit tests.

**Interfaces:**
- Produces version `4.3.0-hs` and cache `psm-static-4.3.0-hs`.

- [ ] **Step 1: Change version contracts to 4.3.0-hs and confirm RED**

Run the version, documentation and PWA PHPUnit files and verify expected-version failures.

- [ ] **Step 2: Update runtime version, cache and documentation**

Document the native Hope UI replacement, SVG icon policy, full-name cards, corrected logs/configuration and authentication layout.

- [ ] **Step 3: Run version contracts GREEN**

Run: `php vendor/bin/phpunit tests/Unit/VersionTest.php tests/Unit/DocumentationContractTest.php tests/Unit/Pwa/PwaAssetTest.php --colors=never`.

### Task 8: Verify, publish and deploy

**Files:**
- Modify if required by audit: `dev/verify-hope-ui-layout.mjs`

- [ ] **Step 1: Run full local verification**

Run `php vendor/bin/phpunit --colors=never`, `php vendor/bin/phpstan analyse --no-progress`, and `git diff --check`.

- [ ] **Step 2: Audit production-like pages in both themes**

Verify Estado, Registros, Configurar, login and every remaining menu route at all required widths. Assert `scrollWidth === clientWidth`, correct computed light/dark surfaces, zero Font Awesome nodes and zero browser errors.

- [ ] **Step 3: Publish main and create the signed release**

Commit the tested implementation, push `HEAD:main`, tag `v4.3.0-hs`, run `release-hs.yml`, and independently verify signature, archive hash and embedded version.

- [ ] **Step 4: Deploy and verify the VPS**

Install the signed release, migrate to database version `4.3.0-hs`, run `php bin/psm health`, repeat the route/theme/browser audit on production and confirm no new PHP-FPM errors.

