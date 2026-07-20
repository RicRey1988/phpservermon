<?php

declare(strict_types=1);

namespace psm\Service\Notification;

use DateTimeImmutable;
use psm\Service\Database;

final readonly class UserNotificationRepository
{
    public function __construct(private Database $database)
    {
    }

    /** @return list<array<string, mixed>> */
    public function latestForUser(int $userId, int $limit = 5): array
    {
        return $this->rowsForUser($userId, true, $limit);
    }

    /** @return list<array<string, mixed>> */
    public function allForUser(int $userId, bool $isAdmin): array
    {
        return $this->rowsForUser($userId, $isAdmin, 100);
    }

    public function unreadCount(int $userId): int
    {
        $rows = $this->database->execute(
            'SELECT COUNT(*) AS unread FROM `' . $this->table('user_notifications') . '` '
            . 'WHERE user_id = :user_id AND read_at IS NULL',
            ['user_id' => $userId],
        );

        return (int) ($rows[0]['unread'] ?? 0);
    }

    public function markRead(int $notificationId, int $userId): void
    {
        $this->database->execute(
            'UPDATE `' . $this->table('user_notifications') . '` SET read_at = :read_at '
            . 'WHERE notification_id = :notification_id AND user_id = :user_id AND read_at IS NULL',
            [
                'read_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                'notification_id' => $notificationId,
                'user_id' => $userId,
            ],
            false,
        );
    }

    public function markAllRead(int $userId): void
    {
        $this->database->execute(
            'UPDATE `' . $this->table('user_notifications') . '` SET read_at = :read_at '
            . 'WHERE user_id = :user_id AND read_at IS NULL',
            ['read_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'), 'user_id' => $userId],
            false,
        );
    }

    /** @return list<array<string, mixed>> */
    private function rowsForUser(int $userId, bool $isAdmin, int $limit): array
    {
        $rows = $this->database->execute(
            'SELECT n.notification_id, n.user_id, n.incident_id, n.transition, n.title, n.body, '
            . 'n.created_at, n.read_at, i.server_id, s.label AS server_label '
            . 'FROM `' . $this->table('user_notifications') . '` AS n '
            . 'INNER JOIN `' . $this->table('incidents') . '` AS i ON i.incident_id = n.incident_id '
            . 'INNER JOIN `' . $this->table('servers') . '` AS s ON s.server_id = i.server_id '
            . 'WHERE n.user_id = :user_id ORDER BY n.created_at DESC, n.notification_id DESC '
            . 'LIMIT ' . max(1, min($limit, 100)),
            ['user_id' => $userId],
        );
        $assigned = $isAdmin ? null : $this->assignedServerIds($userId);
        foreach ($rows as &$row) {
            $canView = $assigned === null || in_array((int) $row['server_id'], $assigned, true);
            $row['can_view'] = $canView;
            $row['url_view'] = $canView
                ? psm_build_url(['mod' => 'server', 'action' => 'view', 'id' => (int) $row['server_id']])
                : null;
        }
        unset($row);

        return $rows;
    }

    /** @return list<int> */
    private function assignedServerIds(int $userId): array
    {
        $rows = $this->database->execute(
            'SELECT server_id FROM `' . $this->table('users_servers') . '` WHERE user_id = :user_id',
            ['user_id' => $userId],
        );

        return array_map(static fn (array $row): int => (int) $row['server_id'], $rows);
    }

    private function table(string $name): string
    {
        $prefix = defined('PSM_DB_PREFIX') ? (string) constant('PSM_DB_PREFIX') : 'psm_';

        return $prefix . $name;
    }
}
