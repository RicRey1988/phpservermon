<?php

declare(strict_types=1);

namespace psm\Service\Notification;

use DateTimeImmutable;
use psm\Service\Database;
use psm\Service\Incident\IncidentTransition;
use Throwable;

class DeliveryRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    public function enqueueDelivery(IncidentTransition $transition, int $userId, string $channel): void
    {
        $this->database->execute(
            'INSERT IGNORE INTO `' . $this->table('notification_deliveries') . '` '
            . '(incident_id, user_id, channel, transition, available_at) '
            . 'VALUES (:incident_id, :user_id, :channel, :transition, :available_at)',
            [
                'incident_id' => $transition->incidentId,
                'user_id' => $userId,
                'channel' => $channel,
                'transition' => $transition->type->value,
                'available_at' => $transition->occurredAt->format('Y-m-d H:i:s'),
            ],
            false,
        );
    }

    public function enqueueUserNotification(
        IncidentTransition $transition,
        int $userId,
        string $title,
        string $body,
    ): void {
        $this->database->execute(
            'INSERT IGNORE INTO `' . $this->table('user_notifications') . '` '
            . '(user_id, incident_id, transition, title, body, created_at) '
            . 'VALUES (:user_id, :incident_id, :transition, :title, :body, :created_at)',
            [
                'user_id' => $userId,
                'incident_id' => $transition->incidentId,
                'transition' => $transition->type->value,
                'title' => mb_substr($title, 0, 255),
                'body' => $body,
                'created_at' => $transition->occurredAt->format('Y-m-d H:i:s'),
            ],
            false,
        );
    }

    /** @return list<array<string, mixed>> */
    public function claimDue(int $limit, DateTimeImmutable $now): array
    {
        $this->database->execute(
            'UPDATE `' . $this->table('notification_deliveries') . '` '
            . "SET state = 'pending', leased_at = NULL "
            . "WHERE state = 'sending' AND leased_at < :stale_lease",
            ['stale_lease' => $now->modify('-5 minutes')->format('Y-m-d H:i:s')],
            false,
        );

        $pdo = $this->database->pdo();
        $pdo->beginTransaction();
        try {
            $rows = $this->database->execute(
                'SELECT d.*, i.server_id FROM `' . $this->table('notification_deliveries') . '` AS d '
                . 'INNER JOIN `' . $this->table('incidents') . '` AS i ON i.incident_id = d.incident_id '
                . "WHERE d.state = 'pending' AND d.available_at <= :now "
                . 'ORDER BY d.available_at, d.delivery_id LIMIT ' . max(1, min($limit, 500)) . ' FOR UPDATE',
                ['now' => $now->format('Y-m-d H:i:s')],
            );
            foreach ($rows as &$row) {
                $this->database->execute(
                    'UPDATE `' . $this->table('notification_deliveries') . '` '
                    . "SET state = 'sending', leased_at = :leased_at, attempts = attempts + 1 "
                    . "WHERE delivery_id = :delivery_id AND state = 'pending'",
                    ['leased_at' => $now->format('Y-m-d H:i:s'), 'delivery_id' => $row['delivery_id']],
                    false,
                );
                $row['attempts'] = (int) $row['attempts'] + 1;
            }
            unset($row);
            $pdo->commit();

            return $rows;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    /** @param list<int> $deliveryIds */
    public function markDelivered(array $deliveryIds, DateTimeImmutable $at): void
    {
        $this->updateMany($deliveryIds, [
            'state' => 'delivered',
            'delivered_at' => $at->format('Y-m-d H:i:s'),
            'leased_at' => null,
            'last_error' => null,
        ]);
    }

    /** @param list<int> $deliveryIds */
    public function markPermanentFailure(array $deliveryIds, string $error): void
    {
        $this->updateMany($deliveryIds, [
            'state' => 'permanent_failure',
            'leased_at' => null,
            'last_error' => mb_substr($error, 0, 255),
        ]);
    }

    /** @param list<int> $deliveryIds */
    public function reschedule(
        array $deliveryIds,
        DateTimeImmutable $availableAt,
        string $error,
        DateTimeImmutable $now,
    ): void {
        $this->updateMany($deliveryIds, [
            'state' => 'pending',
            'available_at' => $availableAt->format('Y-m-d H:i:s'),
            'leased_at' => null,
            'last_error' => mb_substr($error, 0, 255),
        ]);
    }

    /** @param list<int> $deliveryIds @param array<string, mixed> $data */
    private function updateMany(array $deliveryIds, array $data): void
    {
        foreach ($deliveryIds as $deliveryId) {
            $this->database->save(
                $this->table('notification_deliveries'),
                $data,
                ['delivery_id' => $deliveryId],
            );
        }
    }

    private function table(string $name): string
    {
        $prefix = defined('PSM_DB_PREFIX') ? (string) constant('PSM_DB_PREFIX') : 'psm_';

        return $prefix . $name;
    }
}
