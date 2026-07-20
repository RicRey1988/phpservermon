# Hope UI Native Integration Design

## Objective

Replace the hybrid PHP Server Monitor interface with a coherent Hope UI 2.0
implementation while retaining the Hosting Supremo / Server Monitor / HS
product identity. The finished application must render every authenticated and
anonymous screen correctly in both light and dark modes without inherited PHP
Server Monitor CSS or Font Awesome.

## Confirmed root causes

- `custom.css` unconditionally paints `body`, `.card`, tables and links with the
  legacy dark palette. Production therefore reports `data-bs-theme="light"`
  while rendering a dark body and dark server cards.
- `app-shell.css` is a broad compatibility layer over old markup. Its timeline
  grid constrains the log card body to the marker column, producing one letter
  per line, and its global component overrides diverge from Hope UI.
- The shell and modules use `fas fa-*`; the original Hope UI inline SVG/Iconly
  language is absent.
- Server cards truncate names and treat the original card markup as a styling
  target rather than using Hope UI's card composition.
- Configuration fieldsets retain the original application's semantic layout,
  causing legends, borders and content surfaces to clash with the Hope shell.

## Product and visual identity

- Product name: PHP Server Monitor HS / Server Monitor.
- Maintainer identity: Hosting Supremo.
- Primary logo: an HS mark rendered inside the Hope UI navbar-brand geometry.
- Layout, spacing, typography, controls, cards, navigation, customizer and
  responsive behavior follow the supplied Hope UI HTML 2.0 package.
- Icons use a local Twig SVG library derived from the supplied Hope UI/Iconly
  assets. No Font Awesome runtime, classes or assets are loaded.

## Asset architecture

The page loads, in order:

1. the unmodified local Hope UI production stylesheet;
2. the unmodified Hope UI dark/customizer stylesheets;
3. one narrowly scoped `hs-monitor.css` stylesheet for monitor-specific
   components that Hope UI does not provide (server-state cards, image frames,
   latency facts and chart containers).

`custom.css` and `app-shell.css` are removed from the runtime and repository.
The new stylesheet must not set a global hard-coded body/card/table palette.
All colors come from Hope UI/Bootstrap variables and explicit light/dark token
overrides under `html[data-bs-theme]`.

## Application shell

- Use the original Hope UI `sidebar sidebar-default sidebar-white sidebar-base
  navs-rounded-all` hierarchy and original sidebar/header toggle SVG.
- Use Hope UI navbar structure for search, quick theme toggle, settings,
  installation, notifications and the user menu.
- Map every application menu entry to the local Hope SVG macro. Active, hover,
  compact and mobile states follow Hope classes.
- Preserve keyboard navigation, accessible names, current-page indication and
  the responsive off-canvas behavior.
- The blue Hope banner remains a page header, while operational content starts
  in `content-inner mt-n5` as in the original template.

## Screen composition

### Estado

The first content is a responsive grid of redesigned Hope cards. Each card has
a bounded server image, a colored status indicator, a fully wrapping server
name, endpoint, status badge and aligned fact rows. Names must never be
ellipsized. Online/offline state is expressed through Hope soft colors and a
small accent, never a full hard-coded red/green surface.

### Registros

Channel filters use a contained Hope card header. Entries use the original
Hope `profile-media` activity pattern with a marker, a flexible body, server,
timestamp and message. Content must wrap by words, never characters.

### Configuración and forms

The section navigator is a Hope card with vertical pills on desktop and a
horizontal scrollable selector on small screens. Each tab pane contains Hope
cards with `card-header` titles and `card-body` controls. Native fieldsets remain
for accessibility but have no legacy border/legend treatment. Every input,
select, checkbox, switch, help text, input group and action uses Hope/Bootstrap
5 markup consistently.

### Authentication

Login, recovery, registration and reset use the supplied Hope UI authentication
composition: a focused form column plus the original blue authentication image
panel. Hosting Supremo replaces the Hope UI wordmark. Password visibility uses
the local Hope SVG library. The layout collapses to one column on phones.

### Remaining modules

Servers, users, profile, notifications, statistics, system diagnostics,
updater, installer, details, history and error pages use the same Hope card,
form, badge, activity and empty-state primitives. No DataTables are introduced.

## Theme and behavior

- `data-bs-theme` is the sole resolved-theme source; `.dark` is mirrored only
  where the supplied Hope runtime requires it.
- Auto, light and dark preferences persist per user using the existing
  appearance service.
- Theme changes must update body, sidebar, navbar, cards, forms, dropdowns,
  modals, charts and authentication without reload or mixed surfaces.
- Application JavaScript remains native; jQuery and legacy Bootstrap runtimes
  are not loaded.

## Responsive and accessibility requirements

- No horizontal document overflow at 360, 390, 768, 1024, 1366 or 1600 px.
- Cards and facts use `min-width: 0` and word wrapping where user data is shown.
- Visible focus, labels, roles, accessible icon names and reduced-motion
  behavior are preserved.
- SVG decoration is hidden from assistive technology; icon-only buttons retain
  explicit labels.

## Verification and delivery

- Contract tests reject legacy CSS links, Font Awesome classes/runtime and
  hard-coded global palettes.
- Template/render tests cover shell, cards, logs, configuration and auth.
- Browser audits cover every main route, both themes and required viewports.
- PHP unit tests and PHPStan must pass on PHP 8.5.
- Release as `4.3.0-hs`, update README/changelogs, publish to `main`, create a
  signed GitHub release, deploy through the signed updater and run VPS health.

