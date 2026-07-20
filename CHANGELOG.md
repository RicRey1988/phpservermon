# Changelog

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
