HS configuration
================

Notifications and incidents
----------------------------

Configure Email, Telegram and the other transports under **Configuration** and
use their test action before assigning them to servers and users. Test messages
use the same adapter as production. Transport errors are sanitized before they
are shown or written to the log.

Each offline transition opens one incident. The delivery queue creates at most
one down message and one recovery message for each recipient and channel. A
temporary failure is retried with backoff; a permanent failure appears in the
notification centre and system diagnostics. Run the CLI cron at least every
minute so checks and queued notifications are processed promptly.

PWA and Web Push
----------------

Serve the monitor over HTTPS, open the PWA/Web Push configuration and generate
VAPID credentials once. The private key is encrypted before storage and is not
shown again. Users subscribe or remove each browser separately from their
profile. Browser permission is never requested until the user presses the
subscribe control.

The service worker caches only the static application shell. Authenticated
HTML, API responses, configuration, diagnostics and updater routes are always
network-only and are removed from old caches during activation.

Invitations
-----------

Administrators can create an invitation for a specific email address with an
expiry of at most seven days. Only the token-bearing registration route is
public. Tokens are stored as hashes, may be revoked, and are consumed in the
same database transaction that creates the user.

Diagnostics
-----------

The **System** page reports only safe operational state: PHP and required
extensions, database connectivity, writable paths, disk space, cron freshness,
last job runs, notification queue state and PWA configuration. It deliberately
does not render raw ``phpinfo()``, environment variables, request headers,
cookies, database credentials or notification secrets.

The equivalent CLI checks are::

    php bin/psm version
    php bin/psm migrate
    php bin/psm health

Signed updater
--------------

The updater reads only stable HS releases from
``RicRey1988/phpservermon``. An administrator must type the exact target
version and confirm the maintenance operation. There is no arbitrary package
URL field.

Before replacing any file, the updater verifies the pinned RSA public key,
detached signature over the exact canonical manifest, GitHub SHA-256 asset
digest, external and internal package hashes and all ZIP paths. Configuration,
logs, uploaded images, Git metadata and updater runtime files are protected.
Concurrent updates are rejected by a global lock.

Version ``4.3.0-hs`` is published through the signed release channel and is
offered by the updater to administrators running an older HS version. Signed
artifacts are created only through the manual release workflow documented in
``releasing-hs.rst``.
