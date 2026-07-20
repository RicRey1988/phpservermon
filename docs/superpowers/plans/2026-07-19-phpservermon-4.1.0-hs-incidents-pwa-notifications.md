# PHP Server Monitor 4.1.0-hs Incidents, PWA, and Notifications Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn confirmed server transitions into persistent incidents, exactly-once channel deliveries, in-app alerts, and installable PWA Web Push notifications with automatic recovery messages.

**Architecture:** Record an incident only when `StatusUpdater` changes the thresholded status. Enqueue one delivery row per incident, transition, user, and channel under a database unique key; a dispatcher claims and retries pending rows, so a network failure does not lose the alert and repeated cron runs do not duplicate it. Add per-device Web Push subscriptions using VAPID and a safe service worker that caches only public static assets.

**Tech Stack:** PHP 8.5+, PDO MySQL, `minishlink/web-push` 10.1, OpenSSL/VAPID, Twig, native Service Worker/Push/Notifications APIs, PHPUnit 12.

## Global Constraints

- A down incident opens only after the server's existing `warning_threshold` is reached.
- Send at most one down and one recovery delivery per incident/user/channel; temporary failures may retry the same delivery row.
- Never send a recovery without an open incident and never send repeated down alerts while it remains open.
- Preserve Email, SMS, Discord, Webhook, Pushover, and Telegram behavior and configuration.
- Web Push is per signed-in device, requires HTTPS in production, and stores no notification payload in browser caches.
- Service workers never cache POST responses, configuration, PHP info, updater output, authenticated HTML, or JSON snapshots.
- The PWA remains usable as the normal web app when install/push permission is declined.
- This plan does not deploy independently; it joins the single `4.1.0-hs` production deployment.

---

## File and Interface Map

**Existing integration points**

- `src/psm/Util/Server/UpdateManager.php`: emits the old/new thresholded state.
- `src/psm/Util/Server/Updater/StatusNotifier.php`: current message composition and sending.
- `src/psm/Notification/*`: channel registry, recipients, messages, and delivery results.
- `src/psm/Module/Config/Controller/ConfigController.php`: channel configuration and tests.
- `src/psm/Module/User/Controller/ProfileController.php`: per-user delivery destinations.
- `cron/status.cron.php`: recurring dispatcher entry point through `UpdateManager`.
- `manifest.json`, `service-worker.js`: legacy PWA files to replace.

**New interfaces**

- `src/psm/Service/Incident/IncidentRepository.php`: open/resolve/query incident rows.
- `src/psm/Service/Incident/IncidentManager.php`: transactional transition rules.
- `src/psm/Service/Incident/IncidentTransition.php`: immutable `down|recovery` event.
- `src/psm/Service/Notification/DeliveryRepository.php`: unique outbox rows and leases.
- `src/psm/Service/Notification/IncidentRecipientRepository.php`: assigned users and channel destinations.
- `src/psm/Service/Notification/IncidentNotificationDispatcher.php`: enqueue, claim, send, retry.
- `src/psm/Notification/Channel/WebPushChannel.php`: `NotificationChannelInterface` adapter.
- `src/psm/Service/Push/PushSubscriptionRepository.php`: per-device subscriptions.
- `src/psm/Module/User/Controller/PushController.php`: subscribe/unsubscribe/test endpoints.
- `src/psm/Module/User/Controller/NotificationController.php`: notification center/read endpoints.

### Task 1: Add incident, outbox, in-app notification, and push schemas

**Files:**
- Modify: `composer.json`
- Modify: `composer.lock`
- Modify: `src/psm/Util/Install/Installer.php`
- Modify: `tests/Unit/Util/Install/InstallerSchemaTest.php`

- [ ] **Step 1: Write failing schema assertions**

Assert fresh installs and `upgrade410hs()` create these tables and unique keys:

```sql
incidents(
  incident_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  server_id INT UNSIGNED NOT NULL,
  opened_at DATETIME NOT NULL,
  resolved_at DATETIME NULL,
  opening_error VARCHAR(255) NULL,
  recovery_message VARCHAR(255) NULL,
  INDEX server_state(server_id, resolved_at)
)

notification_deliveries(
  delivery_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  incident_id BIGINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  channel VARCHAR(32) NOT NULL,
  transition ENUM('down','recovery') NOT NULL,
  state ENUM('pending','sending','delivered','permanent_failure') NOT NULL DEFAULT 'pending',
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  available_at DATETIME NOT NULL,
  leased_at DATETIME NULL,
  delivered_at DATETIME NULL,
  last_error VARCHAR(255) NULL,
  UNIQUE incident_delivery(incident_id,user_id,channel,transition)
)

user_notifications(
  notification_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  incident_id BIGINT UNSIGNED NOT NULL,
  transition ENUM('down','recovery') NOT NULL,
  title VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  read_at DATETIME NULL,
  UNIQUE user_incident_transition(user_id,incident_id,transition)
)

push_subscriptions(
  subscription_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  endpoint TEXT NOT NULL,
  endpoint_hash CHAR(64) NOT NULL,
  public_key VARCHAR(255) NOT NULL,
  auth_token VARCHAR(255) NOT NULL,
  content_encoding VARCHAR(32) NOT NULL DEFAULT 'aes128gcm',
  device_name VARCHAR(120) NOT NULL,
  user_agent VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  last_seen_at DATETIME NOT NULL,
  UNIQUE endpoint_hash(endpoint_hash)
)
```

- [ ] **Step 2: Run focused schema tests**

Run: `vendor/bin/phpunit tests/Unit/Util/Install/InstallerSchemaTest.php`

Expected: failures for all four absent tables.

- [ ] **Step 3: Add the Web Push dependency and idempotent tables**

Run: `composer require minishlink/web-push:^10.1 --no-interaction`

Extend the existing `upgrade410hs()` with `CREATE TABLE IF NOT EXISTS` statements and add identical definitions to `installTables()`. Add config keys through `INSERT IGNORE`: `webpush_status=0`, `webpush_vapid_subject=''`, `webpush_vapid_public_key=''`, and `webpush_vapid_private_key=''`.

- [ ] **Step 4: Validate dependency and schema**

Run: `composer validate --strict`

Run: `composer audit`

Run: `vendor/bin/phpunit tests/Unit/Util/Install/InstallerSchemaTest.php`

Expected: no known dependency advisories and schema tests pass.

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock src/psm/Util/Install/Installer.php tests/Unit/Util/Install/InstallerSchemaTest.php
git commit -m "feat: add incident and push persistence"
```

### Task 2: Implement transactional incident lifecycle rules

**Files:**
- Create: `src/psm/Service/Incident/IncidentTransition.php`
- Create: `src/psm/Service/Incident/IncidentTransitionType.php`
- Create: `src/psm/Service/Incident/IncidentRepository.php`
- Create: `src/psm/Service/Incident/IncidentManager.php`
- Modify: `src/config/services.xml`
- Create: `tests/Unit/Incident/IncidentManagerTest.php`

- [ ] **Step 1: Write failing transition tests**

Cover online→online/no event, warning attempt/no event, online→offline/open down, offline→offline/no event, offline→online/resolve recovery, online→offline→online/two transitions, recovery without an open incident/no event, and repeated calls/no duplicate rows.

- [ ] **Step 2: Run and confirm missing incident classes**

Run: `vendor/bin/phpunit tests/Unit/Incident/IncidentManagerTest.php`

Expected: missing class errors.

- [ ] **Step 3: Implement the immutable transition and transaction**

```php
enum IncidentTransitionType: string
{
    case Down = 'down';
    case Recovery = 'recovery';
}

final readonly class IncidentTransition
{
    public function __construct(
        public int $incidentId,
        public int $serverId,
        public IncidentTransitionType $type,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
```

`IncidentManager::record(int $serverId, bool $old, bool $new, ?string $error)` returns `null` when states match. Within a DB transaction it selects the latest unresolved incident `FOR UPDATE`, inserts on true→false only when none is open, and resolves on false→true only when one is open. Sanitize persisted errors to 255 characters and never include credentials or response bodies.

- [ ] **Step 4: Run incident tests and PHPStan**

Run: `vendor/bin/phpunit tests/Unit/Incident/IncidentManagerTest.php`

Run: `vendor/bin/phpstan analyse`

Expected: all incident cases pass and PHPStan exits 0.

- [ ] **Step 5: Commit**

```bash
git add src/psm/Service/Incident src/config/services.xml tests/Unit/Incident
git commit -m "feat: persist confirmed server incidents"
```

### Task 3: Replace fire-and-forget alerts with a deduplicated delivery outbox

**Files:**
- Create: `src/psm/Service/Notification/DeliveryRepository.php`
- Create: `src/psm/Service/Notification/IncidentRecipientRepository.php`
- Create: `src/psm/Service/Notification/IncidentMessageComposer.php`
- Create: `src/psm/Service/Notification/IncidentNotificationDispatcher.php`
- Modify: `src/psm/Util/Server/Updater/StatusNotifier.php`
- Modify: `src/psm/Util/Server/UpdateManager.php`
- Modify: `src/config/services.xml`
- Create: `tests/Unit/Notification/IncidentNotificationDispatcherTest.php`
- Modify: `tests/Unit/Notification/StatusNotifierTest.php`
- Modify: `tests/Unit/Server/UpdateManagerTest.php`

- [ ] **Step 1: Write failing outbox/deduplication tests**

Assert one row per incident/user/channel/transition, retry delays of 1, 5, 15, and 60 minutes, reset of stale `sending` leases after 5 minutes, permanent failures not retried, temporary failures retried, and successful rows never sent again. Cover combined notifications by grouping pending rows for the same user/channel/transition while marking each row delivered.

- [ ] **Step 2: Run and confirm the old notifier sends immediately**

Run: `vendor/bin/phpunit tests/Unit/Notification/IncidentNotificationDispatcherTest.php tests/Unit/Notification/StatusNotifierTest.php tests/Unit/Server/UpdateManagerTest.php`

Expected: failures because no persistent delivery records exist.

- [ ] **Step 3: Implement enqueue/claim/send/complete**

`enqueue(IncidentTransition)` resolves assigned users and enabled server/global channels, inserts delivery rows with `INSERT IGNORE`, and inserts one `user_notifications` row per recipient. `flush(100)` atomically claims due rows, uses the existing `ChannelRegistry`, and maps `DeliveryResult` to delivered, rescheduled pending, or permanent failure. Error text is sanitized and truncated.

Refactor `StatusNotifier` into the message-format compatibility layer used by `IncidentMessageComposer`; channel network calls live only in the dispatcher. Preserve all existing message templates, URL options, per-server channel switches, and `combine_notifications` behavior.

Change the core loop to:

```php
$statusOld = $server['status'] === 'on';
$statusNew = (bool) $updater->update($serverId);
$transition = $incidentManager->record($serverId, $statusOld, $statusNew, (string) $updater->error);
if ($transition !== null) {
    $dispatcher->enqueue($transition);
}
```

Call `$dispatcher->flush()` once after all server updates and also when there are no new transitions, so previously failed deliveries retry.

- [ ] **Step 4: Run notification and update tests**

Run: `vendor/bin/phpunit tests/Unit/Notification tests/Unit/Server/UpdateManagerTest.php`

Expected: all existing channels plus dedup/retry tests pass with mocked transports.

- [ ] **Step 5: Commit**

```bash
git add src/psm/Service/Notification src/psm/Util/Server/Updater/StatusNotifier.php src/psm/Util/Server/UpdateManager.php src/config/services.xml tests/Unit/Notification tests/Unit/Server/UpdateManagerTest.php
git commit -m "feat: deliver incident alerts through persistent outbox"
```

### Task 4: Add the in-app notification center

**Files:**
- Create: `src/psm/Module/User/Controller/NotificationController.php`
- Modify: `src/psm/Module/User/UserModule.php`
- Modify: `src/templates/default/main/app-navbar.tpl.html`
- Create: `src/templates/default/module/user/notification/index.tpl.html`
- Create: `src/templates/default/static/js/notifications.js`
- Create: `tests/Unit/Notification/NotificationCenterTest.php`

- [ ] **Step 1: Write failing access/read-state tests**

Assert authenticated users see only their records, unread count is capped visually at `99+`, `markRead` and `markAllRead` require POST+CSRF, incident links respect server assignments, and notification body is escaped.

- [ ] **Step 2: Run and confirm the module route is absent**

Run: `vendor/bin/phpunit tests/Unit/Notification/NotificationCenterTest.php`

Expected: missing controller/route failures.

- [ ] **Step 3: Implement navbar bell, dropdown, and card feed**

The navbar renders the latest five alerts and an unread badge. The full page uses status-colored cards with server, transition, occurrence time, read state, and permitted detail link. Native fetch marks a single record read; no polling faster than the configured dashboard refresh interval.

- [ ] **Step 4: Run notification-center and UI tests**

Run: `vendor/bin/phpunit tests/Unit/Notification/NotificationCenterTest.php tests/Unit/Ui/ModernViewContractTest.php`

Expected: all focused tests pass and the feed contains no table markup.

- [ ] **Step 5: Commit**

```bash
git add src/psm/Module/User src/templates/default/main/app-navbar.tpl.html src/templates/default/module/user/notification src/templates/default/static/js/notifications.js tests/Unit/Notification/NotificationCenterTest.php
git commit -m "feat: add incident notification center"
```

### Task 5: Make the application an installable, safe PWA

**Files:**
- Delete: `manifest.json`
- Modify: `service-worker.js`
- Create: `manifest.webmanifest`
- Create: `offline.html`
- Create: `src/templates/default/static/images/pwa/icon-192.png`
- Create: `src/templates/default/static/images/pwa/icon-512.png`
- Create: `src/templates/default/static/images/pwa/icon-maskable-512.png`
- Create: `src/templates/default/static/js/pwa.js`
- Modify: `src/templates/default/main/body.tpl.html`
- Create: `tests/Unit/Pwa/PwaAssetTest.php`

- [ ] **Step 1: Write failing manifest and cache-policy tests**

Assert valid JSON, standalone display, start URL within scope, 192/512/maskable icons, `theme_color`, and `background_color`. Assert the service worker caches only versioned CSS/JS/icons/offline shell, ignores non-GET requests, uses network-only for `index.php`, `install.php`, `public.php`, JSON endpoints, Config, PHP Info, and Updater, and handles `CLEAR_PRIVATE_CACHES`.

- [ ] **Step 2: Run and confirm legacy PWA assets fail policy**

Run: `vendor/bin/phpunit tests/Unit/Pwa/PwaAssetTest.php`

Expected: failures for legacy manifest/service-worker policy.

- [ ] **Step 3: Implement the manifest and service worker**

Use cache name `psm-static-4.1.0-hs`. `install` pre-caches only the offline document and versioned static assets. `fetch` uses cache-first only for same-origin paths under `/src/templates/default/static/` and PWA icons; navigation uses network-first and returns `offline.html` on network failure without storing authenticated responses. `activate` deletes all older `psm-static-*` caches.

`pwa.js` registers only under HTTPS or localhost. On logout/session loss it sends `CLEAR_PRIVATE_CACHES`; on an explicit user action it exposes the browser install prompt. Never force a permission dialog on page load.

- [ ] **Step 4: Run PWA asset tests**

Run: `vendor/bin/phpunit tests/Unit/Pwa/PwaAssetTest.php tests/Unit/TemplateAssetTest.php`

Expected: focused tests pass.

- [ ] **Step 5: Commit**

```bash
git add manifest.webmanifest service-worker.js offline.html src/templates/default/static/images/pwa src/templates/default/static/js/pwa.js src/templates/default/main/body.tpl.html tests/Unit/Pwa/PwaAssetTest.php
git rm manifest.json
git commit -m "feat: add secure installable PWA shell"
```

### Task 6: Add per-device VAPID Web Push

**Files:**
- Create: `src/psm/Service/Push/PushSubscriptionRepository.php`
- Create: `src/psm/Notification/Channel/WebPushChannel.php`
- Modify: `src/psm/Notification/ChannelRegistryFactory.php`
- Create: `src/psm/Module/User/Controller/PushController.php`
- Modify: `src/psm/Module/User/UserModule.php`
- Modify: `src/psm/Module/Config/Controller/ConfigController.php`
- Modify: `src/templates/default/module/config/config.tpl.html`
- Modify: `src/templates/default/module/user/profile.tpl.html`
- Modify: `src/templates/default/static/js/pwa.js`
- Create: `tests/Unit/Notification/WebPushChannelTest.php`
- Create: `tests/Unit/Push/PushSubscriptionTest.php`

- [ ] **Step 1: Write failing channel/subscription tests**

Cover valid subscribe/upsert, ownership-protected unsubscribe, endpoint hash uniqueness, invalid origin/key rejection, CSRF, permission-denied state, expired HTTP 404/410 subscription deletion, temporary retry, payload escaping, same-origin click URL, and one push per subscribed device.

- [ ] **Step 2: Run and confirm Web Push is not registered**

Run: `vendor/bin/phpunit tests/Unit/Notification/WebPushChannelTest.php tests/Unit/Push/PushSubscriptionTest.php`

Expected: missing class/channel failures.

- [ ] **Step 3: Implement VAPID configuration and device controls**

Add `webpush_status` to Config checkboxes, public key/subject to normal fields, and private key to `encryptedFields`. Generate keys only through an explicit admin POST action using `VAPID::createVapidKeys()`; display the public key but never return the private key after save.

`PushController` accepts JSON shaped exactly as:

```json
{
  "endpoint": "https://push.example/subscription",
  "keys": {"p256dh": "base64url", "auth": "base64url"},
  "contentEncoding": "aes128gcm",
  "deviceName": "Chrome en Windows"
}
```

`WebPushChannel` builds subscriptions from the recipient's devices and sends a payload containing `title`, `body`, `icon`, `badge`, `tag` (`incident-{id}-{transition}`), and same-origin `url`. The service worker calls `showNotification`; `notificationclick` validates origin before focusing/opening the URL.

- [ ] **Step 4: Run push and complete notification tests**

Run: `vendor/bin/phpunit tests/Unit/Notification tests/Unit/Push tests/Unit/Pwa`

Expected: all notification/PWA tests pass with mocked push reports.

- [ ] **Step 5: Commit**

```bash
git add src/psm/Service/Push src/psm/Notification/Channel/WebPushChannel.php src/psm/Notification/ChannelRegistryFactory.php src/psm/Module/User src/psm/Module/Config src/templates/default/module/config src/templates/default/module/user/profile.tpl.html src/templates/default/static/js/pwa.js tests/Unit/Notification/WebPushChannelTest.php tests/Unit/Push
git commit -m "feat: add per-device Web Push alerts"
```

### Task 7: Verify the complete incident-to-device path

**Files:**
- Modify if needed: notification/PWA files touched above.

- [ ] **Step 1: Run all subsystem tests**

Run: `vendor/bin/phpunit tests/Unit/Incident tests/Unit/Notification tests/Unit/Push tests/Unit/Pwa tests/Unit/Server/UpdateManagerTest.php`

Expected: all tests pass.

- [ ] **Step 2: Run global verification**

Run: `composer audit`

Run: `vendor/bin/phpunit`

Run: `vendor/bin/phpstan analyse`

Expected: no advisories, full suite green, PHPStan exit 0.

- [ ] **Step 3: Perform one end-to-end local transition test**

Using a controlled disposable endpoint and mocked external transports, run enough failures to cross the configured threshold. Confirm one incident, one in-app alert, and one delivery per enabled channel/device. Run cron again while down and confirm counts do not increase. Restore the endpoint and confirm exactly one recovery per channel/device. Force one temporary delivery failure and confirm the same row is retried rather than duplicated.

- [ ] **Step 4: Inspect browser PWA behavior**

Under HTTPS/localhost, install the PWA, subscribe two browser profiles, confirm device cards, test a push, check icon/badge/click navigation, deny permission in a third profile without breaking the web app, and inspect Cache Storage to confirm no authenticated HTML/JSON is present.

- [ ] **Step 5: Commit only verified corrections**

```bash
git add src/psm/Service/Incident src/psm/Service/Notification src/psm/Service/Push src/psm/Notification src/templates/default/static/js/pwa.js service-worker.js
git commit -m "fix: complete incident and PWA notification verification"
```

## Plan Completion Criteria

- Threshold-confirmed down and recovery transitions create one incident lifecycle.
- Email, SMS, Discord, Webhook, Pushover, Telegram, Web Push, and in-app deliveries are deduplicated and retryable.
- Repeated cron/manual checks do not duplicate alerts.
- PWA install works over HTTPS, and each user can manage individual browser devices.
- Declining push permission does not reduce ordinary web functionality.
- No authenticated response or secret is cached by the service worker.
- Full PHPUnit, Composer audit, and PHPStan checks pass before integration.
