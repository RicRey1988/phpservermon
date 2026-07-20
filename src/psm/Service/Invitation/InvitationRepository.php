<?php

declare(strict_types=1);

namespace psm\Service\Invitation;

use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use psm\Service\Database;

class InvitationRepository
{
    /** @var Closure(): string */
    private Closure $randomBytes;

    /** @param null|callable(): string $randomBytes */
    public function __construct(private readonly Database $database, ?callable $randomBytes = null)
    {
        $this->randomBytes = $randomBytes === null
            ? static fn (): string => random_bytes(32)
            : Closure::fromCallable($randomBytes);
    }

    public function create(string $email, int $level, int $createdBy, DateTimeImmutable $expiresAt): string
    {
        $email = strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('A valid invitation email is required.');
        }
        if (!in_array($level, [10, 20], true)) {
            throw new InvalidArgumentException('The invitation role is invalid.');
        }
        $nowDate = new DateTimeImmutable();
        if ($expiresAt <= $nowDate || $expiresAt > $nowDate->modify('+7 days')) {
            throw new InvalidArgumentException('The invitation expiry must be in the future.');
        }
        $token = rtrim(strtr(base64_encode(($this->randomBytes)()), '+/', '-_'), '=');
        if (strlen($token) < 40) {
            throw new InvalidArgumentException('The invitation token source is too short.');
        }
        $now = date('Y-m-d H:i:s');
        $this->database->execute(
            'INSERT INTO `' . $this->table('user_invitations') . '` '
            . '(email, token_hash, level, created_by, created_at, expires_at, accepted_at, revoked_at) '
            . 'VALUES (:email, :token_hash, :level, :created_by, :created_at, :expires_at, NULL, NULL)',
            [
                'email' => $email,
                'token_hash' => hash('sha256', $token),
                'level' => $level,
                'created_by' => $createdBy,
                'created_at' => $now,
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            ],
            false,
        );

        return $token;
    }

    /** @return array{invitation_id:int,email:string,level:int}|null */
    public function findValid(string $token, bool $lock = false): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{40,128}$/', $token)) {
            return null;
        }
        $rows = $this->database->execute(
            'SELECT invitation_id, email, level FROM `' . $this->table('user_invitations') . '` '
            . 'WHERE token_hash = :token_hash AND accepted_at IS NULL AND revoked_at IS NULL AND expires_at > :now LIMIT 1'
            . ($lock ? ' FOR UPDATE' : ''),
            ['token_hash' => hash('sha256', $token), 'now' => date('Y-m-d H:i:s')],
        );
        $row = is_array($rows) ? ($rows[0] ?? null) : null;
        if (!is_array($row)) {
            return null;
        }

        return [
            'invitation_id' => (int) $row['invitation_id'],
            'email' => (string) $row['email'],
            'level' => (int) $row['level'],
        ];
    }

    public function markAccepted(int $invitationId, DateTimeImmutable $acceptedAt): void
    {
        $this->database->execute(
            'UPDATE `' . $this->table('user_invitations') . '` SET accepted_at = :accepted_at '
            . 'WHERE invitation_id = :invitation_id AND accepted_at IS NULL',
            ['accepted_at' => $acceptedAt->format('Y-m-d H:i:s'), 'invitation_id' => $invitationId],
            false,
        );
    }

    /** @return list<array<string, mixed>> */
    public function pending(): array
    {
        $rows = $this->database->execute(
            'SELECT invitation_id, email, level, created_at, expires_at FROM `' . $this->table('user_invitations') . '` '
            . 'WHERE accepted_at IS NULL AND revoked_at IS NULL AND expires_at > :now ORDER BY created_at DESC',
            ['now' => date('Y-m-d H:i:s')],
        );
        return is_array($rows) ? array_values($rows) : [];
    }

    public function revoke(int $invitationId): void
    {
        if ($invitationId <= 0) { return; }
        $this->database->execute(
            'UPDATE `' . $this->table('user_invitations') . '` SET revoked_at = :revoked_at '
            . 'WHERE invitation_id = :invitation_id AND accepted_at IS NULL AND revoked_at IS NULL',
            ['revoked_at' => date('Y-m-d H:i:s'), 'invitation_id' => $invitationId],
            false,
        );
    }

    private function table(string $name): string
    {
        return (defined('PSM_DB_PREFIX') ? (string) PSM_DB_PREFIX : 'psm_') . $name;
    }
}
