<?php

declare(strict_types=1);

namespace psm\Service\Notification;

use psm\Notification\Recipient;
use psm\Service\Database;
use psm\Service\Incident\IncidentTransition;

class IncidentRecipientRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /** @return list<IncidentRecipient> */
    public function forTransition(IncidentTransition $transition): array
    {
        $rows = $this->database->execute(
            'SELECT u.user_id, u.name, u.email, u.mobile, u.discord, u.webhook_url, u.webhook_json, '
            . 'u.pushover_key, u.pushover_device, u.telegram_id, '
            . 'EXISTS(SELECT 1 FROM `' . $this->table('push_subscriptions') . '` AS ps WHERE ps.user_id = u.user_id) AS has_webpush, '
            . 's.email AS server_email, s.sms AS server_sms, s.discord AS server_discord, '
            . 's.webhook AS server_webhook, s.pushover AS server_pushover, s.telegram AS server_telegram '
            . 'FROM `' . $this->table('users') . '` AS u '
            . 'INNER JOIN `' . $this->table('users_servers') . '` AS us ON us.user_id = u.user_id '
            . 'INNER JOIN `' . $this->table('servers') . '` AS s ON s.server_id = us.server_id '
            . 'WHERE s.server_id = :server_id',
            ['server_id' => $transition->serverId],
        );
        $recipients = [];
        foreach ($rows as $row) {
            $channels = [];
            foreach (['email', 'sms', 'discord', 'webhook', 'pushover', 'telegram'] as $channel) {
                if ((bool) psm_get_conf($channel . '_status') && ($row['server_' . $channel] ?? 'no') === 'yes') {
                    $channels[] = $channel;
                }
            }
            if ((bool) psm_get_conf('webpush_status') && (bool) ($row['has_webpush'] ?? false)) {
                $channels[] = 'webpush';
            }
            $recipients[] = new IncidentRecipient(
                new Recipient((int) $row['user_id'], $this->recipientAttributes($row)),
                $channels,
            );
        }

        return $recipients;
    }

    public function recipient(int $userId): Recipient
    {
        $rows = $this->database->execute(
            'SELECT user_id, name, email, mobile, discord, webhook_url, webhook_json, '
            . 'pushover_key, pushover_device, telegram_id '
            . 'FROM `' . $this->table('users') . '` WHERE user_id = :user_id LIMIT 1',
            ['user_id' => $userId],
        );
        $row = $rows[0] ?? ['user_id' => $userId];

        return new Recipient($userId, $this->recipientAttributes($row));
    }

    /** @param array<string, mixed> $row @return array<string, scalar|null> */
    private function recipientAttributes(array $row): array
    {
        $attributes = [];
        foreach ([
            'name', 'email', 'mobile', 'discord', 'webhook_url', 'webhook_json',
            'pushover_key', 'pushover_device', 'telegram_id',
        ] as $key) {
            $value = $row[$key] ?? null;
            $attributes[$key] = is_scalar($value) ? $value : null;
        }

        return $attributes;
    }

    private function table(string $name): string
    {
        $prefix = defined('PSM_DB_PREFIX') ? (string) constant('PSM_DB_PREFIX') : 'psm_';

        return $prefix . $name;
    }
}
