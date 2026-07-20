<?php

declare(strict_types=1);

namespace psm\Service\Notification;

use psm\Notification\NotificationMessage;
use psm\Service\Database;
use psm\Service\Incident\IncidentTransition;
use psm\Service\Incident\IncidentTransitionType;

class IncidentMessageComposer
{
    public function __construct(private readonly Database $database)
    {
    }

    /** @param array<string, mixed> $delivery */
    public function compose(array $delivery): NotificationMessage
    {
        $server = $this->server((int) $delivery['server_id']);
        $online = ($delivery['transition'] ?? 'down') === IncidentTransitionType::Recovery->value;
        $channel = (string) $delivery['channel'];
        $subject = match ($channel) {
            'email' => psm_parse_msg($online, 'email_subject', $server),
            'pushover' => psm_parse_msg($online, 'pushover_title', $server),
            'webhook' => psm_parse_msg($online, 'webhook_title', $server),
            default => (string) ($server['label'] ?? 'Server Monitor'),
        };
        $template = match ($channel) {
            'email' => 'email_body',
            'sms' => 'sms',
            'discord' => 'discord_message',
            'webhook' => 'webhook_message',
            'pushover' => 'pushover_message',
            'telegram' => 'telegram_message',
            default => 'sms',
        };
        $body = psm_parse_msg($online, $template, $server);
        $url = in_array($channel, ['pushover', 'telegram'], true) ? psm_build_url() : null;

        return new NotificationMessage($subject, $body, $url, !$online, [
            'incident_id' => (int) $delivery['incident_id'],
            'server_id' => (int) $delivery['server_id'],
            'transition' => (string) $delivery['transition'],
            'server_ip' => $server['ip'] ?? null,
            'server_label' => $server['label'] ?? null,
            'server_error' => $server['error'] ?? null,
            'status' => $online ? 'online' : 'offline',
        ]);
    }

    /** @param list<array<string, mixed>> $deliveries */
    public function composeMany(array $deliveries): NotificationMessage
    {
        $messages = array_map(fn (array $delivery): NotificationMessage => $this->compose($delivery), $deliveries);
        $critical = false;
        $bodies = [];
        foreach ($messages as $message) {
            $critical = $critical || $message->isCritical();
            $bodies[] = $message->body();
        }

        return new NotificationMessage(
            count($messages) . ' server notifications',
            implode(PHP_EOL . PHP_EOL, $bodies),
            psm_build_url(),
            $critical,
            ['combined_count' => count($messages)],
        );
    }

    /** @return array{title: string, body: string} */
    public function inApp(IncidentTransition $transition): array
    {
        $server = $this->server($transition->serverId);
        $label = (string) ($server['label'] ?? 'Server');
        $down = $transition->type === IncidentTransitionType::Down;

        return [
            'title' => $down ? $label . ' is offline' : $label . ' recovered',
            'body' => $down
                ? (string) ($server['error'] ?? 'The server is not responding.')
                : 'The server is responding again.',
        ];
    }

    /** @return array<string, mixed> */
    private function server(int $serverId): array
    {
        return $this->database->selectRow(
            $this->table('servers'),
            ['server_id' => $serverId],
            ['server_id', 'ip', 'port', 'label', 'error', 'last_online', 'last_offline', 'last_offline_duration'],
        );
    }

    private function table(string $name): string
    {
        $prefix = defined('PSM_DB_PREFIX') ? (string) constant('PSM_DB_PREFIX') : 'psm_';

        return $prefix . $name;
    }
}
