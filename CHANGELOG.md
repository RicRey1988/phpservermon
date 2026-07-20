# Changelog

## [4.2.0-hs] - 2026-07-20

### Added
- Authentic Hope UI 2.0 shell and customizer using the owner-supplied template
  assets, with a quick light/dark control and per-user visual preferences.
- Dedicated Statistics module for KPIs, availability and latency charts.
- Automated responsive contracts and browser audit coverage for 360, 390, 768,
  1024, 1366 and 1600 pixel viewports in light and dark modes.

### Changed
- Estado now opens directly on live server cards; historical charts no longer
  delay or displace operational status.
- Server detail, history, user editing, anonymous pages and every shared shell
  control use responsive Hope UI/Bootstrap 5 markup.
- Hope UI images have bounded, normalized frames and chart/tool controls wrap
  instead of overflowing the viewport.
- The PWA static cache includes the new versioned Hope UI runtime and drops the
  previous cache on activation.

### Fixed
- Manual status checks apply returned server colours and values immediately.
- Light/dark changes redraw statistics charts and remain available on login,
  installer and error pages.

### Notes
- DataTables and jQuery demo bundles are intentionally excluded.
- Published as a signed GitHub Release with the production ZIP, canonical
  manifest and detached RSA-SHA256 signature required by the HS updater.

## [4.1.0-hs] - Unreleased

### Added
- Complete Hope UI application shell with responsive card views, accessible
  light/dark themes and redesigned authentication, installer and forms.
- Server image drag-and-drop uploads with GD normalization, fixed display
  dimensions and a generic fallback.
- Availability, latency and incident statistics plus immediate locked manual
  checks that return the completed state to the dashboard.
- Persistent incident and delivery queues with in-app notifications, retry
  policy and exactly-once down/recovery notification scheduling.
- Installable PWA, offline application shell, per-device Web Push subscriptions
  and encrypted VAPID private-key storage.
- Expiring, revocable and single-use administrator invitations.
- Administrator system diagnostics and ``bin/psm`` commands for version,
  additive migrations and health checks.
- Transactional signed updater restricted to stable HS releases from
  ``RicRey1988/phpservermon``.

### Changed
- Product version is now ``4.1.0-hs`` and all runtime repository identifiers
  point to the Hosting Supremo fork.
- Server, user and log indexes use native Hope UI cards instead of DataTables.
- Email, Telegram, Web Push and the other channels share the persistent
  incident delivery pipeline.
- Release packages require a canonical manifest, detached RSA-SHA256 signature,
  GitHub asset digest and an internal per-file hash manifest.

### Fixed
- Manual refresh no longer races cron or waits for stale browser polling before
  changing online/offline colours.
- Oversized and malformed server images can no longer break dashboard layout.
- SMTP TLS selection, Telegram request handling, retry diagnostics and
  localized date literals work correctly on PHP 8.5.
- Production exceptions create a safe reference and log entry instead of a
  blank authenticated page.
- System diagnostics ignore older unsigned legacy releases before requiring
  signed updater assets, so an already newer HS installation reports no update.
- Apache serves the PWA manifest with its correct MIME type, prevents stale
  service-worker caching and uses reachable icons in Web Push payloads.

### Security
- Dynamic authenticated pages and secrets are excluded from PWA caches.
- Web Push private keys, invitation tokens and updater signatures are handled
  without storing reusable plaintext credentials.
- The updater rejects arbitrary URLs, prereleases, wrong repositories, unsafe
  ZIP paths, symlinks, duplicate paths, oversized packages and unsigned files.
- Updates use a global lock, maintenance gate, protected-path allowlist and
  rollback on installation failure.

### Upgrade Notes
- Preserve ``config.php``, ``logs/``, uploaded server images and runtime update
  data during a manual deployment.
- Run ``php bin/psm migrate`` followed by ``php bin/psm health`` after replacing
  files, or finish the additive migration through ``install.php``.
- Configure CLI cron every minute for timely monitoring, incident delivery and
  recovery alerts. Generate VAPID keys before enabling browser push.
- This commit is intentionally not a GitHub Release; the signed updater acts
  only when a newer published HS release exists.

### Known Requirements
- PHP 8.5 or later, MySQL/MariaDB, HTTPS for PWA/Web Push, Composer 2 for source
  installs, and the PHP extensions declared in ``composer.json`` including GD,
  Intl, PDO MySQL and ZIP.

## [4.0.2-hs] - Unreleased

### Fixed
- Manual status refresh now shares the cron lock, waits for an active cron run
  and displays its completed result instead of racing it with conflicting writes.
- Server images use a fixed 64 x 64 box and the custom stylesheet URL is
  versioned so month-long browser caches cannot retain the old unbounded layout.
- SMTP submission on port 587 automatically uses STARTTLS (and port 465 uses
  implicit TLS) when no encryption mode was saved.
- Telegram transient errors identify safe HTTP/network diagnostics and retry
  counts without exposing the bot token.
- Localized dates preserve literal language text instead of interpreting it as
  PHP date tokens such as the server timezone.

## [4.0.1-hs] - Unreleased

### Fixed
- Restored the authenticated Status and Servers pages by declaring PHP `intl`
  as a required Composer and installer extension.
- Production errors are now logged with a reference identifier and return a
  safe HTTP 500 page instead of an unexplained blank response.

## [4.0.0-hs] - Unreleased

### Added
- Secure administrator-only PHP environment diagnostics under Configuration,
  without environment variables, request headers, cookies or raw `phpinfo()` output.
- A PHP 8.5 CI workflow with PHPUnit 12 and PHPStan 2.
- Atomic cron locking, explicit web-cron authorization and per-server failure isolation.
- A shared notification dispatcher and channel adapters for email, SMS, Discord,
  webhook, Pushover and Telegram.
- An additive, idempotent `4.0.0-hs` database migration.

### Changed
- Minimum runtime is PHP 8.5; later PHP versions are accepted.
- Updated PHPMailer to 7.1.1, Symfony components to 7.4.14
  (Filesystem 7.4.11) and Twig to 3.28.0.
- Updated development tools to PHPUnit 12.5.31 and PHPStan 2.2.5.
- Installer and cron entry points now enforce the runtime and extension requirements.
- Telegram uses POST requests, splits messages at Telegram's 4096-character
  limit and sanitizes transport errors. Pushover now uses its API directly.
- Configuration notification tests use the same production delivery paths and
  identify test messages with `[PRUEBA 4.0.0-hs]`.
- Integrated applicable upstream `develop` improvements while retaining the HS
  dark interface and per-server image support.

### Fixed
- Notification, Telegram profile activation and cron compatibility with PHP 8.5.
- Multiple notification channels can now reuse the complete recipient set.
- Webhooks that do not return JSON are now sent before reporting success.
- Ysmal and PromoSMS no longer fall through to a different SMS gateway.
- Fresh installations now use the correct `log_discord` configuration key.
- Symfony 7 container integration in controllers and the update manager.

### Removed
- Jabber/JAXL and obsolete compatibility packages.
- The bundled `composer.phar`; Composer 2 is now used from the system.

### Security
- Sensitive runtime data and notification credentials are excluded from PHP
  diagnostics and transport error logs.
- Web cron is disabled by default, ignores forwarded-IP headers and requires an
  explicitly configured key or direct source-IP allowlist when enabled.
- Database version markers are not rewritten when older application code sees a
  newer database schema.
- Fresh installations receive an independent remember-me cookie signing key;
  the shared legacy default was removed.
