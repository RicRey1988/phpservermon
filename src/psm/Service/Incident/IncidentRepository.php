<?php

declare(strict_types=1);

namespace psm\Service\Incident;

use Closure;
use DateTimeImmutable;
use psm\Service\Database;
use Throwable;

class IncidentRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    public function transaction(Closure $operation): mixed
    {
        $pdo = $this->database->pdo();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $result = $operation();
            if ($ownsTransaction) {
                $pdo->commit();
            }

            return $result;
        } catch (Throwable $exception) {
            if ($ownsTransaction) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array{incident_id: int|string, server_id: int|string}|null */
    public function findOpenForUpdate(int $serverId): ?array
    {
        $rows = $this->database->execute(
            'SELECT incident_id, server_id FROM `' . $this->table() . '` '
            . 'WHERE server_id = :server_id AND resolved_at IS NULL '
            . 'ORDER BY incident_id DESC LIMIT 1 FOR UPDATE',
            ['server_id' => $serverId],
        );

        return $rows[0] ?? null;
    }

    public function open(int $serverId, DateTimeImmutable $openedAt, ?string $error): int
    {
        $this->database->execute(
            'INSERT INTO `' . $this->table() . '` (server_id, opened_at, opening_error) '
            . 'VALUES (:server_id, :opened_at, :opening_error)',
            [
                'server_id' => $serverId,
                'opened_at' => $openedAt->format('Y-m-d H:i:s'),
                'opening_error' => $error,
            ],
            false,
        );

        return (int) $this->database->getLastInsertedId();
    }

    public function resolve(int $incidentId, int $serverId, DateTimeImmutable $resolvedAt, string $message): void
    {
        $this->database->execute(
            'UPDATE `' . $this->table() . '` SET resolved_at = :resolved_at, recovery_message = :message '
            . 'WHERE incident_id = :incident_id AND server_id = :server_id AND resolved_at IS NULL',
            [
                'resolved_at' => $resolvedAt->format('Y-m-d H:i:s'),
                'message' => $message,
                'incident_id' => $incidentId,
                'server_id' => $serverId,
            ],
            false,
        );
    }

    private function table(): string
    {
        $prefix = defined('PSM_DB_PREFIX') ? (string) constant('PSM_DB_PREFIX') : 'psm_';

        return $prefix . 'incidents';
    }
}
