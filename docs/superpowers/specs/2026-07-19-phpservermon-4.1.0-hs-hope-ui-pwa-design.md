# PHP Server Monitor 4.1.0-hs Hope UI and PWA Design

**Status:** Approved in conversation on 2026-07-19

**Target version:** `4.1.0-hs`

**Delivery model:** one production deployment after complete local and integration verification

**Theme baseline:** Hope UI HTML Admin Dashboard, MIT licensed

## 1. Product direction

`4.1.0-hs` is a complete presentation and workflow modernization of the HS
fork. It keeps the proven PHP Server Monitor domain model, MariaDB data,
monitoring engine, cron, notification adapters and upgrade history while
replacing the legacy Bootstrap 4 interface with a cohesive Hope UI / Bootstrap
5 application shell.

The release must remain compatible with PHP 8.5 and later supported PHP
versions. Every HS version keeps the `-hs` suffix.

### Goals

- Replace every authenticated and authentication-facing screen with a modern,
  responsive Hope UI design.
- Preserve every current functional field and option while improving grouping,
  help text, validation and error states.
- Use cards, responsive lists, timelines, drawers and charts; do not introduce
  DataTables.
- Provide a useful operational dashboard with current and historical status.
- Add installable PWA support and per-device Web Push notifications.
- Reliably notify configured recipients when a server crosses its failure
  threshold and when it recovers.
- Add safe image upload and processing to the server editor.
- Add a confirm-to-install GitHub Release updater with integrity checks,
  migrations, health checks and automatic file rollback.
- Update the GitHub README so installation and every HS feature are documented.

### Non-goals

- No full backend rewrite or migration to a new PHP framework.
- No public self-registration by default.
- No unattended production upgrades.
- No destructive schema migrations in this release.
- No caching of authenticated POST responses or credentials in the PWA.
- No GitHub Release is created merely by implementing this version; release
  publication remains a separate owner-approved action.

## 2. Architecture

The existing controllers, services and Twig rendering remain the application
foundation. Presentation logic moves into typed view models and reusable Twig
components so templates do not repeat field, status or permission rules.

The Hope UI source is treated as a design dependency, not as a second
application. Only required compiled assets, icons and components are vendored.
The upstream MIT license and attribution are retained. Runtime assets are
served locally with content/version hashes; the application must not depend on
a third-party CDN.

The redesigned application has these layers:

1. Existing monitoring and persistence services.
2. Application services for dashboard statistics, incident events, images,
   invitations, Web Push and update jobs.
3. Controller/view-model boundary with explicit authorization and validation.
4. Hope UI Twig component library and PWA application shell.

All database changes are additive and idempotent. Existing servers, users,
history, notification preferences and per-server channel flags are preserved.

## 3. Information architecture

### Application shell

- Collapsible left sidebar on desktop and an accessible drawer on mobile.
- Top bar with global search, theme control, PWA install action, notification
  bell and user menu.
- Breadcrumbs and a consistent page title/action region.
- Light, dark and operating-system themes stored per user.
- Color customization is limited to tested design tokens so contrast remains
  accessible.
- Hosting Supremo branding and a footer link to the HS fork.

### Dashboard / Estado

- KPI cards for online, warning, offline, average latency and active incidents.
- Availability and latency charts for 24 hours, 7 days, 30 days and 90 days.
- Recent-incident timeline and a compact update/cron health card.
- Responsive server-card grid with search, status/type filters, sorting and
  server-side pagination.
- Each server card contains a normalized image, accessible status label,
  current latency, last check, uptime summary and quick actions.
- Offline data cached by the PWA is visibly marked as stale and never presented
  as a live result.

### Servidores

- Card/list hybrid built with semantic lists and CSS grid, not HTML data tables.
- Filter chips for status, type, notification channel and active state.
- Server detail uses summary cards, history charts, response/certificate panels,
  recent incidents and channel state.
- Create/edit is divided into General, Check, Authentication, Notifications,
  Advanced and Image sections. Rare legacy settings remain available under an
  Advanced disclosure instead of disappearing.

### Registro

- Incident and delivery timeline cards instead of DataTables.
- Filters for date, server, transition, channel and delivery outcome.
- Expandable details show safe error information, notification attempts and
  correlation identifiers.
- Cursor/server-side pagination prevents large histories from bloating the DOM.

### Usuarios

- User cards show role, status, enabled channels, last activity and actions.
- User editing groups identity, access, assigned servers, notification
  destinations, PWA devices and appearance preferences.
- Public registration stays disabled. Administrators may create a user directly
  or issue a single-use invitation with an expiry time.

### Configuración

- Section cards for General, Authentication, Email, SMS, Pushover, Telegram,
  Discord, Webhook, PWA/Web Push, Updates, Appearance and PHP Information.
- Channel test actions use the exact production delivery path and show safe,
  actionable results.
- Secrets are masked by default, never returned to the browser after save and
  never written to diagnostics.

### Actualizaciones

- Current-version and available-release cards.
- Release notes, compatibility results and integrity state are visible before
  confirmation.
- A stepper reports download, verification, maintenance, files, migration,
  health checks and completion/rollback.

## 4. Component and form system

Reusable Twig components cover buttons, cards, badges, alerts, empty states,
inputs, selects, textareas, checkboxes, radio groups, password/secret fields,
file drops, pagination, charts, timelines, drawers, confirmation dialogs and
loading skeletons.

Every existing editable field is entered into a form inventory with:

- source column/config key and controller action;
- label and help text;
- input component and default value;
- required/optional/advanced state;
- server-side validation rule and client-side hint;
- authorization and visibility rule;
- sensitive-data treatment;
- light/dark/mobile visual state;
- save, reload and validation-error test.

An automated coverage test compares the accepted controller/config keys with
the form inventory. A key cannot silently disappear during the redesign.

Validation is server-authoritative. Client validation improves feedback but
does not replace CSRF, authorization, normalization or backend checks. Errors
appear beside the relevant field and in a linked summary. Keyboard focus moves
to the first invalid field.

## 5. Server images

- Drag-and-drop and file-picker input with preview, replace and remove actions.
- Accept JPEG, PNG and WebP only; SVG is rejected because it can contain active
  content.
- Validate MIME type from file contents, extension, dimensions and size.
- Decode and re-encode the upload, strip metadata, crop/pad without distortion
  and generate standard thumbnails.
- Store generated names rather than user-supplied paths.
- A neutral HS generic image is used when no custom image exists or processing
  fails.
- Images are served with immutable hashed URLs; cards reserve a fixed image box
  so layout cannot shift or overflow.

Existing label-based images are migrated or resolved as a compatibility
fallback until each server has an explicit image reference.

## 6. PWA and Web Push

### Installation and caching

- A standards-compliant manifest defines HS name, colors, scope, start URL,
  display mode and icon sizes.
- The service worker precaches only hashed public application assets.
- Authenticated navigation uses network-first behavior with a short timeout.
- The last successful dashboard shell may be shown read-only when offline with
  an unmistakable stale/offline banner and timestamp.
- POST, upload, update, authentication and secret-bearing responses are never
  cached.
- Cache names include the application version; activation removes old caches.
- Logout tells the service worker to clear user-scoped cached responses.

### Push subscriptions

- Web Push uses VAPID keys configured by an administrator.
- Permission is requested only after an explanatory, user-triggered action.
- A user may name, list and revoke each subscribed device.
- Subscription endpoints and key material are encrypted at rest and excluded
  from normal diagnostics.
- Expired/invalid subscriptions are disabled after provider responses indicate
  they are gone.
- Notification clicks open the relevant server or incident using a validated
  same-origin URL.

## 7. Incident and notification lifecycle

The monitor creates a durable incident event only when a server crosses its
configured warning threshold. A failed probe below the threshold remains a
warning and does not generate a down incident. A recovery closes the open
incident and generates one recovery event.

Each event has a stable deduplication key. Repeated cron/manual checks cannot
send duplicate down or recovery notifications. Deliveries are recorded per
user, channel and device with success, temporary failure, permanent failure,
attempt count and a sanitized diagnostic.

The event is delivered through enabled per-server and per-user channels:

- in-app notification center;
- Web Push;
- Telegram;
- email;
- the existing supported SMS, Pushover, Discord and webhook channels.

Temporary failures use bounded retries. One failing channel does not stop the
remaining channels or the remaining server checks. Manual and cron updates use
the same event pipeline and shared lock.

## 8. Statistics

Dashboard statistics are calculated from the existing uptime/history data and
incident events. Queries are bounded by time range, server permissions and
pagination. Expensive aggregates may use short-lived server-side caches that
are invalidated after status updates; browser caches are never treated as live
monitor data.

Charts provide accessible numeric summaries and do not rely on color alone.
Missing history is displayed as unavailable, not as 100% uptime.

## 9. GitHub Release updater

The updater checks only the configured HS repository and accepts stable tags
matching the `-hs` version policy. It never installs automatically.

The administrator flow is:

1. Fetch release metadata with timeouts and rate-limit handling.
2. Compare semantic HS versions and platform requirements.
3. Download the package to a private staging directory.
4. Verify a signed update manifest and SHA-256 hashes using a public key pinned
   in the application.
5. Reject path traversal, links, unexpected executables and files outside the
   manifest.
6. Confirm writable paths and available disk space.
7. Enter maintenance mode and create a temporary rollback snapshot of code
   only. Configuration, uploads, images and logs are preserved.
8. Install files and run additive migrations inside supported transactions.
9. Run bootstrap, platform, database and authenticated-route health checks.
10. Exit maintenance on success. On failure, roll back files and the active
    migration transaction, then show a safe diagnostic reference.

The temporary rollback snapshot is deleted after a verified success; it is not
a persistent VPS backup. Destructive database migrations are forbidden because
they cannot be safely auto-rolled back.

## 10. Authentication and invitations

- Existing login and password-reset behavior is preserved and redesigned.
- Public registration routes return unavailable unless an administrator later
  enables a separately protected policy.
- Invitations are single-use, expire, are stored hashed and may be revoked.
- The invitee sets a password and confirms the invited identity; role and
  server access are chosen by the administrator, not by the invitee.
- Login, reset and invitation endpoints are rate-limited and use CSRF/session
  protections appropriate to the request.
- Successful authentication rotates the session identifier. Logout revokes the
  session and clears user-scoped PWA state.

## 11. Errors and observability

- User-facing failures receive a correlation/reference identifier.
- Administrators get a searchable diagnostic-card view containing sanitized
  context, component, route, time and remediation hint.
- Tokens, passwords, cookies, authorization headers, SMTP credentials, push
  endpoints and full secret URLs are redacted.
- Cron and update jobs expose last start, finish, duration, processed count and
  safe failure summary.
- Notification tests report provider category, HTTP/SMTP class and retry count
  without exposing secrets.

## 12. Data changes

Additive migrations provide durable storage for:

- incident events and their recovery relationship;
- notification deliveries and read/unread state;
- encrypted Web Push subscriptions/devices;
- invitations;
- explicit server image metadata;
- user appearance/PWA preferences where existing preference storage is not
  sufficient;
- update job state and safe diagnostic references.

Indexes cover open incident lookup, server/time history, unread notifications,
delivery deduplication, active subscriptions and invitation token hashes.
Migration code remains idempotent and keeps legacy data readable.

## 13. Security and accessibility

- Administrator authorization protects configuration, users, diagnostics and
  updates.
- Server-level permissions constrain dashboard, history, incidents and push
  content.
- CSRF protection covers all state changes; update/install requires recent
  administrator authentication.
- Uploaded files and release archives never execute from writable directories.
- Content Security Policy and same-origin URLs are compatible with the local
  Hope UI assets and service worker.
- Target WCAG 2.1 AA: keyboard navigation, focus visibility, semantic labels,
  contrast, reduced-motion support and non-color status indicators.

## 14. README and repository documentation

The GitHub README is rewritten for the HS fork and includes:

- feature overview and `-hs` version policy;
- Hope UI attribution and screenshots for light/dark desktop and mobile;
- PHP 8.5+ and extension requirements;
- Apache/Nginx, MariaDB, Composer and cron installation;
- required writable paths and SELinux guidance;
- upgrade instructions from `3.5.3-hs` and `4.0.x-hs`;
- server images, PWA installation and VAPID/Web Push setup;
- email, Telegram and other channel setup/testing;
- secure GitHub updater/release-manifest workflow;
- diagnostics, common failures and security reporting;
- upstream and HS repository links and licenses.

## 15. Verification and acceptance

### Automated verification

- PHPUnit for new services, migrations, deduplication, image validation,
  invitations, PWA endpoints and updater security.
- PHPStan and Composer/platform validation on PHP 8.5.
- Route smoke tests for anonymous, user and administrator roles.
- Form-inventory coverage for all accepted fields/configuration keys.
- Notification contract tests plus VPS delivery tests for Telegram, email and
  Web Push.
- Service-worker and manifest checks, including cache clearing on logout.
- Security tests for archive traversal, upload spoofing, CSRF, unauthorized
  access, redaction and unsafe notification URLs.

### Visual verification

Every page is reviewed in light, dark and automatic themes at desktop, tablet
and mobile widths. Tests cover empty, loading, populated, validation-error,
provider-failure, offline and permission-restricted states. Screenshots verify
that no field, card, image, menu, dialog or notification overflows and that
keyboard focus remains visible.

DataTables and their assets must be absent from runtime code.

### Production acceptance

- Existing users, servers, history and settings remain intact.
- Dashboard and every top-level navigation destination return successfully.
- Manual refresh and cron agree on final state and do not overlap.
- A controlled down/recovery transition produces one incident and one delivery
  per enabled destination, including an opted-in PWA device.
- Email, Telegram and Web Push tests succeed from the production delivery path.
- PWA installs and updates its cache without retaining authenticated data after
  logout.
- The updater can verify a test package, reject a tampered package and recover
  from a simulated failed health check.
- The VPS is changed only after the complete release candidate passes the
  verification matrix; deployment is one coordinated update followed by
  targeted corrections if production reveals an issue.
