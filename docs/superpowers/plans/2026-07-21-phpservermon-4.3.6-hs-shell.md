# PHP Server Monitor 4.3.6-hs Shell Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Repair the Hope UI header and responsive sidebar, remove duplicated account navigation, and release the verified result as `4.3.6-hs`.

**Architecture:** Keep Hope UI's `sidebar-mini` class as the single sidebar state. Add only accessibility and mobile drawer orchestration in `app-shell.js`, while `hs-monitor.css` owns component surfaces and responsive geometry without inventing a second state model.

**Tech Stack:** PHP 8.5, Twig, Hope UI 2.0, Bootstrap 5, vanilla JavaScript, CSS, PHPUnit 12, PHPStan, Playwright verification.

## Global Constraints

- Build from the application content of `v4.3.2-hs`; ignore failed release contents from `v4.3.3-hs` through `v4.3.5-hs`.
- Publish the completed version as exactly `4.3.6-hs` / `v4.3.6-hs`.
- Do not load Bootstrap 4, jQuery, Font Awesome, DataTables or legacy application styles in the shell.
- Preserve Hope UI class names and assets supplied in `hope-ui-html-2.0.zip`.
- Do not create a VPS backup, per the user's standing instruction.

---

### Task 1: Hope UI shell contracts

**Files:**
- Modify: `tests/Unit/Ui/AppShellTest.php`
- Modify: `tests/Unit/Ui/HopeAssetPolicyTest.php`

**Interfaces:**
- Consumes: Twig shell source, project CSS and vanilla runtime as text contracts.
- Produces: regression assertions for one account menu, one sidebar state, mobile backdrop and Hope-aligned controls.

- [ ] **Step 1: Write the failing tests**

Add tests asserting that `menu.tpl.html` has no `sidebar-user`, the body contains `data-sidebar-backdrop`, the CSS contains `.topbar-icon-button`, `.hope-search`, `.sidebar-mini` responsive rules and no `body.sidebar-open`, and the runtime contains `initializeSidebar()` plus Escape/backdrop/link close behavior.

- [ ] **Step 2: Run the focused tests and verify RED**

Run: `php vendor/bin/phpunit tests/Unit/Ui/AppShellTest.php tests/Unit/Ui/HopeAssetPolicyTest.php --colors=never`

Expected: failures identify the current duplicated account block, missing backdrop/component classes and obsolete `body.sidebar-open` rule.

- [ ] **Step 3: Keep test scope minimal**

Confirm each failure maps to one requested behavior and does not require application data or database fixtures.

### Task 2: Native sidebar behavior

**Files:**
- Modify: `src/templates/default/main/body.tpl.html`
- Modify: `src/templates/default/main/menu.tpl.html`
- Modify: `src/templates/default/main/app-navbar.tpl.html`
- Modify: `src/templates/default/static/js/app-shell.js`
- Modify: `src/templates/default/static/css/hs-monitor.css`

**Interfaces:**
- Consumes: Hope UI `[data-toggle="sidebar"]` buttons and `.sidebar-mini` state.
- Produces: `initializeSidebar()` which synchronizes `aria-expanded`, mobile overlay, Escape, outside and navigation close behavior.

- [ ] **Step 1: Remove duplicate account navigation**

Delete the sidebar separator, username, Profile and Logout list. Keep the existing top account dropdown as the only account surface.

- [ ] **Step 2: Add the mobile backdrop and stable hooks**

Add `data-sidebar-backdrop`, `data-sidebar-primary-toggle` and `data-sidebar-mobile-toggle` attributes while retaining Hope UI's structural classes and SVG icon macro.

- [ ] **Step 3: Implement minimal sidebar orchestration**

Add `initializeSidebar()` to observe Hope's existing clicks, synchronize labels and expanded state, and close below 1200 px after backdrop click, Escape or navigation. Do not create `sidebar-open` or `sidebar-collapsed` classes.

- [ ] **Step 4: Replace conflicting responsive rules**

Remove the custom `body.sidebar-open` transform contract. Define full-width mobile content, off-canvas `.sidebar-mini`, open non-mini sidebar, backdrop and body scroll lock using Hope state.

- [ ] **Step 5: Run focused tests and verify GREEN**

Run: `php vendor/bin/phpunit tests/Unit/Ui/AppShellTest.php tests/Unit/Ui/HopeAssetPolicyTest.php --colors=never`

Expected: all focused shell tests pass.

### Task 3: Header, theme control and search surfaces

**Files:**
- Modify: `src/templates/default/main/app-navbar.tpl.html`
- Modify: `src/templates/default/static/css/hs-monitor.css`
- Modify: `tests/Unit/Ui/AppShellTest.php`

**Interfaces:**
- Consumes: `data-theme-quick-toggle`, `.theme-icon-dark`, `.theme-icon-light` and `data-card-search`.
- Produces: `.topbar-icon-button` and `.hope-search` component contracts shared across light and dark themes.

- [ ] **Step 1: Add failing semantic assertions**

Require the theme and settings controls to use `.topbar-icon-button`, the search group to use `.hope-search`, and CSS to define 44 px boxes, centered SVG, light/dark colors and focus surfaces.

- [ ] **Step 2: Verify RED**

Run: `php vendor/bin/phpunit tests/Unit/Ui/AppShellTest.php --colors=never`

Expected: component class and sizing assertions fail.

- [ ] **Step 3: Apply native Hope-compatible markup and CSS**

Use a single flex-centered 44 px icon button contract. Style search group segments as one Hope surface with theme-aware colors, placeholder and focus ring, visible at tablet widths and bounded on mobile.

- [ ] **Step 4: Verify GREEN**

Run: `php vendor/bin/phpunit tests/Unit/Ui/AppShellTest.php --colors=never`

Expected: all AppShell tests pass.

### Task 4: Version and release documentation

**Files:**
- Modify: `src/includes/psmconfig.inc.php`
- Modify: `src/templates/default/service-worker.js`
- Modify: `README.rst`
- Modify: `CHANGELOG.md`
- Modify: `tests/Unit/VersionTest.php`
- Modify: `tests/Unit/DocumentationContractTest.php`
- Modify: `tests/Unit/Pwa/PwaAssetTest.php`

**Interfaces:**
- Consumes: version constants and cache contract.
- Produces: consistent `4.3.6-hs` application, documentation, updater and PWA identifiers.

- [ ] **Step 1: Update contract tests first**

Replace expected `4.3.2-hs` values with `4.3.6-hs` and release URL `v4.3.6-hs`.

- [ ] **Step 2: Verify RED**

Run: `php vendor/bin/phpunit tests/Unit/VersionTest.php tests/Unit/DocumentationContractTest.php tests/Unit/Pwa/PwaAssetTest.php --colors=never`

Expected: failures show source version, README and service-worker cache still report `4.3.2-hs`.

- [ ] **Step 3: Update sources and user documentation**

Set the version constant and cache to `4.3.6-hs`; document the Hope shell, mobile drawer, search and theme fixes, and point README links at `RicRey1988/phpservermon-Redesigned-by-hostingsupremo`.

- [ ] **Step 4: Verify GREEN**

Run the same three focused test files and expect all to pass.

### Task 5: Full verification, production and release

**Files:**
- Modify: `dev/verify-hope-ui-layout.mjs`
- Deployment target: `/home/hostin/public_html`

**Interfaces:**
- Consumes: authenticated production routes and responsive viewport states.
- Produces: verified VPS deployment and signed GitHub release `v4.3.6-hs`.

- [ ] **Step 1: Expand browser verification**

At 390 px assert initial closed sidebar, click the unique mobile toggle, assert the sidebar is visible and content remains full width, then close through backdrop. At desktop assert icon centers share the same vertical midpoint and both themes have contrast.

- [ ] **Step 2: Run local quality gates**

Run: `php vendor/bin/phpunit --colors=never`

Run: `php vendor/bin/phpstan analyse --no-progress`

Run: `php -l` for each changed PHP source and parse changed Twig templates.

Expected: zero failures and zero static-analysis errors.

- [ ] **Step 3: Commit intended files**

Review `git diff --check` and `git status --short`, stage only requested sources/tests/docs, and commit `fix: release 4.3.6-hs Hope UI shell`.

- [ ] **Step 4: Deploy without backup**

Archive the committed application, upload it to the VPS, preserve `config.php`, restore the `apache` group/write contract, regenerate authoritative Composer autoload, restart PHP-FPM and run `php bin/psm migrate` plus `php bin/psm health`.

- [ ] **Step 5: Validate production interaction**

Use the live browser at desktop and 390 px in both themes. Verify search surface, icon alignment/contrast, arrow rotation, mobile open/close, no duplicated sidebar account block and no horizontal overflow.

- [ ] **Step 6: Reconcile and publish GitHub history**

Merge remote main with the `ours` strategy so its failed-release history remains reachable while application content stays based on `v4.3.2-hs`. Push main, create and push `v4.3.6-hs`, run the signed `Release HS` workflow and verify ZIP, manifest and signature assets.
