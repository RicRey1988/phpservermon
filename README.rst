PHP Server Monitor HS
=====================

Version 4.3.4-hs — latest signed Hosting Supremo release

PHP Server Monitor HS checks websites and network services and presents their
availability, latency and history in a modern web dashboard. This Hosting
Supremo edition is maintained at
https://github.com/RicRey1988/phpservermon-Redesigned-by-hostingsupremo and preserves attribution to the
original PHP Server Monitor project.

Download
--------

Download the signed ``4.3.4-hs`` package and review its release notes at
https://github.com/RicRey1988/phpservermon-Redesigned-by-hostingsupremo/releases/tag/v4.3.4-hs. The Release
includes the production ZIP, its canonical SHA-256 manifest and a detached
RSA-SHA256 signature used by the built-in updater.

Highlights
----------

* Authentic Hope UI 2.0 dashboard with accessible cards, the original visual
  hierarchy and redesigned login, registration, recovery, configuration and
  installer screens. DataTables is not loaded.
* No project-specific presentation stylesheet is loaded. The complete interface
  is composed from the bundled ``hope-ui.min.css``, ``dark.min.css`` and
  ``customizer.min.css`` assets plus native Hope UI/Bootstrap utilities.
* A quick theme toggle in the top bar switches light/dark immediately. The
  settings gear opens the complete customizer for Auto/Dark/Light, accent,
  LTR/RTL, sidebar and navbar styles; authenticated preferences are saved per
  user and anonymous preferences remain local to the browser.
* The top search and icon controls share the native Hope UI alignment. The
  responsive sidebar uses the upstream ``sidebar-mini`` state, places and
  rotates its arrow correctly, opens on mobile and closes after navigation,
  Escape or an outside click. User session actions live only in the top menu.
* Estado is operational-first and shows server cards immediately; the separate Statistics
  page contains availability/latency KPIs and charts.
* Responsive contracts cover widths 360, 390, 768, 1024, 1366 and 1600 in
  both light and dark modes without page overflow.
* Immediate manual status refresh, uptime and latency statistics, incident
  history, channel delivery state and prominent, accessible online/offline
  banners that refresh immediately.
* Persistent one-year authenticated sessions with rotating secure tokens;
  explicit logout still revokes access. Password and light/dark controls keep
  visible Hope UI icons in both themes.
* Administrators can upload a custom system logo and each user can upload a
  profile photo. Both are normalized to deterministic WebP files and fall back
  to the HS mark or user initial.
* Normalized server images uploaded with drag and drop, a safe fixed display
  size and a bundled generic fallback when no image exists.
* Email, Telegram, Web Push, SMS, Discord, Webhook and Pushover notifications.
  A down incident opens once, sends one alert per configured recipient and
  channel, and sends a recovery notification when service returns.
* Installable PWA with an offline shell, per-device Web Push subscriptions and
  VAPID key management. The service worker shows automatic down and recovery
  notifications even when the monitor is not the active browser tab. Dynamic
  authenticated responses are never cached.
* Administrator invitation links with expiry, revocation and single-use
  registration.
* Grouped safe PHP runtime, limit, OPcache and compatibility information plus
  diagnostics for extensions, database, disk, permissions, cron, delivery
  queue and jobs. Permission failures identify the PHP user, affected path and
  corrective command without exposing credentials or request data.
* A signed updater restricted to newer ``-hs`` releases from the Hosting
  Supremo fork. It verifies the pinned RSA signature, GitHub asset digest,
  package hash and safe archive paths before entering maintenance mode.

Monitoring
----------

Services are checked by opening the configured host and port. Websites are
checked with cURL and may also require a regular-expression match. Schedule
``cron/status.cron.php`` at least once per minute for timely automatic checks
and down/recovery notifications. An operating-system lock prevents overlapping
runs, and one failing check does not stop the remaining servers.

Each server controls its own recipients and channels. Use the test action in
Configuration to validate Email or Telegram through the same delivery path as
production. The notification centre reports outages, permanent delivery
failures and a newer signed application version.

Requirements
------------

* Apache, nginx or another PHP-capable web server.
* MySQL or MariaDB.
* PHP 8.5 or later.
* PHP extensions ``ext-ctype``, ``ext-curl``, ``ext-filter``, ``ext-gd``,
  ``ext-hash``, ``ext-intl``, ``ext-json``, ``ext-libxml``, ``ext-mbstring``,
  ``ext-openssl``, ``ext-pdo``, ``ext-pdo_mysql``, ``ext-xml`` and ``ext-zip``.
* Composer 2 when installing directly from source.
* HTTPS for PWA installation, browser notifications and Web Push outside
  localhost.

Install
-------

For a packaged release, extract the archive, point the web server at its root
and open ``install.php``. For a Git checkout, install production dependencies
with PHP 8.5 or later::

    composer install --no-dev --classmap-authoritative

Then open ``install.php`` and follow the guided setup. Keep ``config.php``
outside version control and configure the CLI cron job, for example::

    * * * * * /usr/bin/php /var/www/phpservermon/cron/status.cron.php

After installation, an administrator can generate VAPID credentials in the
PWA/Web Push configuration and each user can subscribe the current device from
their profile. The generator accepts a plain email or ``mailto:`` contact and
creates the key pair without requiring a public key first.

How Web Push works
------------------

Web Push is configured once by an administrator with a VAPID contact and key
pair. The public key identifies this installation; the private key stays
encrypted on the server. On HTTPS, each user explicitly enables notifications
from Profile. The browser then creates an endpoint and encryption keys for that
specific browser/device, which PHP Server Monitor stores as a subscription.

When ``cron/status.cron.php`` detects a real transition to down or recovered,
the incident queue creates one delivery for each subscribed recipient. The
server encrypts the message and sends it to the browser vendor's push service.
The bundled service worker receives it and displays the operating-system
notification; selecting it opens the monitor. Every browser/device must be
subscribed separately. Regenerating the VAPID keys invalidates existing
subscriptions, so users must enable them again. Installing the PWA is optional,
but HTTPS, the service worker, permission from the user and a running cron are
required for reliable Web Push.

Upgrade
-------

Before a manual upgrade, confirm the documented platform requirements. Replace
application files while preserving ``config.php``, ``logs/``, uploaded server
images and runtime update data, then run::

    php bin/psm migrate
    php bin/psm health

The web installer can also apply additive database migrations. The in-app
updater accepts no arbitrary URL: it can install only a newer stable HS release
whose three release assets and detached signature pass verification. Version
``4.3.4-hs`` is published through that signed channel and can be installed from
the System page by an administrator running an older HS version.

More documentation
------------------

See ``docs/install.rst`` for deployment and cron details,
``docs/configuration-hs.rst`` for notifications, PWA and updater operation,
and ``docs/releasing-hs.rst`` for the signed release process.

License and attribution
-----------------------

PHP Server Monitor HS is free software distributed under GPL-3.0-or-later.
It is based on PHP Server Monitor by Pepijn Over and contributors. Hosting
Supremo maintains this fork and its ``-hs`` product line.
