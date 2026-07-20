# PHP Server Monitor 4.2.0-hs Authentic Hope UI Design

**Status:** Approved by the owner on 2026-07-20

**Target version:** `4.2.0-hs`

**Reference package:** `hope-ui-html-2.0.zip` supplied by the owner, SHA-256
`4c1838f231fe506a13c63aba007f508ea2c5ef67219c165e3020f26c9d6a5e7a`

## 1. Goal

Replace the hand-built approximation of Hope UI with a faithful integration of
the supplied Hope UI 2.0.0 HTML dashboard while preserving PHP Server Monitor's
Twig controllers, monitoring behavior, data, permissions, notifications, PWA,
and signed updater.

The application must look and behave like Hope UI in both light and dark modes.
Every page, control, form, chart, dialog, and responsive state must remain inside
the viewport without horizontal overflow.

## 2. Source-of-truth strategy

The supplied package is the visual and structural source of truth. Its compiled
HTML, Handlebars partials, SCSS, images, icons, `hope-ui.css`, `dark.css`,
`customizer.css`, `hope-ui.js`, and `setting.js` define the accepted layout and
component behavior.

The default Hope UI dashboard layout and partials will be translated to Twig
without changing their essential class hierarchy:

- `aside.sidebar.sidebar-default.sidebar-white.sidebar-base`;
- `main.main-content` as the direct sibling of the sidebar;
- `nav.iq-navbar` with the original responsive navbar structure;
- `iq-navbar-header` and `content-inner` page regions;
- the original settings offcanvas markup and `data-setting` contracts.

The complete template is not embedded as an iframe or separate frontend. PHP
Server Monitor continues rendering server-authoritative Twig pages.

Only required runtime dependencies are shipped. The template's DataTables and
jQuery initialization bundles are excluded. No DataTables markup, JavaScript,
or CSS is loaded by PHP Server Monitor.

## 3. Application shell

### Sidebar

The sidebar uses the supplied default Hope UI layout, dimensions, responsive
mini state, icons, section labels, active-item treatment, and mobile behavior.
The menu contains:

1. Estado
2. Estadísticas
3. Servidores
4. Registro
5. Usuarios
6. Configurar
7. Sistema
8. Actualizar

Profile and logout remain in the authenticated user area. Items continue to
respect role and permission checks.

### Navbar

The top navbar follows the supplied Hope UI header. It contains the mobile
sidebar control, current page context, PWA install action, quick light/dark
toggle, notification center, version, and user menu.

The quick theme control always remains visible at desktop and mobile widths. It
shows a moon while light mode is active and a sun while dark mode is active,
with an accessible label describing the resulting action.

### Customizer

The original Hope UI gear opens the supplied settings offcanvas. It provides:

- Auto, Dark, and Light color modes;
- tested accent colors;
- LTR and RTL direction;
- Default, Dark, Color, and Transparent sidebar colors;
- supported sidebar types and active-item styles;
- supported navbar styles.

Changes preview immediately. Authenticated choices are validated and persisted
in existing per-user preferences. The original session-storage behavior is a
temporary client cache only; server preferences remain authoritative after
login or reload. Invalid values fall back to safe defaults.

## 4. Information architecture

### Estado

Estado is the operational view. After the page title and a compact toolbar, the
server cards are the first content visible. The toolbar contains search,
status/type filters, card/list choice, auto-refresh state, and “Actualizar
ahora”. It does not contain KPI cards or historical charts.

Server cards use Hope UI card structure, a fixed normalized image frame, a
text-and-icon status badge, current latency, last check, last online/offline
information, and quick actions. Status changes returned by manual or automatic
refresh update immediately without reloading the page.

### Estadísticas

Estadísticas is a new top-level destination. It contains the monitoring KPIs,
availability and latency charts, time-range selector, status distribution,
incident summary, and recent operational activity. Charts use Hope UI cards and
ApexCharts with explicit empty states and light/dark theme updates.

### Servidores

The server list remains card based. Create and edit screens use Hope UI form
cards, tabs or sections, file drop, input groups, switches, validation states,
and responsive actions.

Server detail is rebuilt from the supplied Hope UI cards. Its first viewport
shows identity, image, live status, latency, endpoint, last check, and actions.
History, output, incidents, notification channels, and monitor configuration
appear in responsive tabs or stacked sections. Legacy inline `vw` widths,
Bootstrap 4 spacing utilities, and fixed horizontal button groups are removed.

### Remaining modules

Registro, Usuarios, Configurar, Sistema, Actualizar, Perfil, login, password
recovery, invitation registration, installer, error pages, and maintenance
pages use the matching Hope UI cards, forms, authentication layouts, steppers,
alerts, badges, dropdowns, modals, and empty states.

No accepted field or action may disappear. Buttons use consistent primary,
secondary, success, warning, danger, soft, outline, and icon variants. Destructive
actions retain confirmation and CSRF protection.

## 5. Responsive and overflow rules

The shell and every module are tested at widths 360, 390, 768, 1024, 1366, and
1600 pixels. At each width:

- `document.documentElement.scrollWidth` must not exceed the viewport width;
- grids collapse through Bootstrap breakpoints without clipped content;
- button groups wrap or become stacked controls;
- charts use fluid containers and bounded heights;
- images keep their reserved dimensions and `object-fit` behavior;
- long labels, endpoints, output, and error messages wrap safely;
- sidebar and customizer behave as drawers on small screens;
- modals remain scrollable inside the viewport.

Horizontal page scrolling is never hidden as a substitute for fixing an
overflowing component.

## 6. Theme behavior

Light, dark, and auto modes share the same component hierarchy and spacing.
Theme changes update Hope UI classes, Bootstrap color mode attributes, chart
palettes, browser theme color, and icon state without a page reload.

All authenticated and anonymous pages are verified for readable text, borders,
inputs, dropdowns, charts, alerts, disabled controls, focus rings, and status
colors in both themes. Status never relies on color alone.

## 7. Error handling and compatibility

Missing or corrupt appearance preferences fall back to light/auto defaults and
must not prevent a page from rendering. If chart JavaScript cannot load, the
page retains its numeric summary and an explicit unavailable state.

Existing URLs, controller actions, CSRF behavior, authorization, server data,
images, incidents, notifications, PWA installation, and signed updates remain
compatible. Database changes are limited to additive preference storage only
if an existing preference key cannot represent an approved Hope UI option.

## 8. Verification

Automated tests cover:

- the authentic Hope UI shell and asset contracts;
- visible quick theme toggle and settings offcanvas;
- validation and persistence of every appearance option;
- separate Estado and Estadísticas routes and menu entries;
- server cards appearing before any statistics on Estado;
- absence of DataTables and legacy fixed-width history markup;
- all existing form fields and actions;
- Twig syntax, PHPStan, PHPUnit, Composer validation, and platform checks.

Browser verification covers every top-level page and representative create,
edit, detail, authentication, modal, empty, error, and populated state in light
and dark mode across all target widths. Production deployment occurs only after
local tests and GitHub Actions succeed. The VPS is then checked for navigation,
theme persistence, refresh behavior, cron health, PWA assets, and zero overflow.

## 9. Delivery

The complete change is published to the Hosting Supremo fork's `main` branch as
`4.2.0-hs` without creating a GitHub Release unless the owner later requests
one. The VPS receives one coordinated deployment after verification, followed
only by targeted production corrections if evidence requires them.
