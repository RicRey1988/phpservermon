<?php

declare(strict_types=1);

namespace psm\Service\Push;

use InvalidArgumentException;
use psm\Service\Database;

class PushSubscriptionRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /** @param array<string, mixed> $subscription */
    public function upsert(int $userId, array $subscription, string $userAgent): void
    {
        $endpoint = trim((string) ($subscription['endpoint'] ?? ''));
        $parts = parse_url($endpoint);
        if (
            filter_var($endpoint, FILTER_VALIDATE_URL) === false
            || !is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
        ) {
            throw new InvalidArgumentException('A secure Web Push endpoint is required.');
        }

        $keys = $subscription['keys'] ?? null;
        $publicKey = is_array($keys) ? trim((string) ($keys['p256dh'] ?? '')) : '';
        $authToken = is_array($keys) ? trim((string) ($keys['auth'] ?? '')) : '';
        if (!$this->validKey($publicKey, 40, 255) || !$this->validKey($authToken, 16, 255)) {
            throw new InvalidArgumentException('The Web Push encryption keys are invalid.');
        }

        $contentEncoding = trim((string) ($subscription['contentEncoding'] ?? 'aes128gcm'));
        if (!in_array($contentEncoding, ['aes128gcm', 'aesgcm'], true)) {
            throw new InvalidArgumentException('The Web Push content encoding is not supported.');
        }

        $deviceName = $this->plainText((string) ($subscription['deviceName'] ?? 'Navegador'), 120);
        $userAgent = $this->plainText($userAgent, 255);
        $now = date('Y-m-d H:i:s');
        $this->database->execute(
            'INSERT INTO `' . $this->table('push_subscriptions') . '` '
            . '(user_id, endpoint, endpoint_hash, public_key, auth_token, content_encoding, device_name, user_agent, created_at, last_seen_at) '
            . 'VALUES (:user_id, :endpoint, :endpoint_hash, :public_key, :auth_token, :content_encoding, :device_name, :user_agent, :created_at, :last_seen_at) '
            . 'ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), endpoint = VALUES(endpoint), '
            . 'public_key = VALUES(public_key), auth_token = VALUES(auth_token), content_encoding = VALUES(content_encoding), '
            . 'device_name = VALUES(device_name), user_agent = VALUES(user_agent), last_seen_at = VALUES(last_seen_at)',
            [
                'user_id' => $userId,
                'endpoint' => $endpoint,
                'endpoint_hash' => hash('sha256', $endpoint),
                'public_key' => $publicKey,
                'auth_token' => $authToken,
                'content_encoding' => $contentEncoding,
                'device_name' => $deviceName === '' ? 'Navegador' : $deviceName,
                'user_agent' => $userAgent,
                'created_at' => $now,
                'last_seen_at' => $now,
            ],
            false,
        );
    }

    /** @return list<array<string, mixed>> */
    public function forUser(int $userId): array
    {
        $rows = $this->database->execute(
            'SELECT subscription_id, endpoint, endpoint_hash, public_key, auth_token, content_encoding, '
            . 'device_name, user_agent, created_at, last_seen_at FROM `' . $this->table('push_subscriptions') . '` '
            . 'WHERE user_id = :user_id ORDER BY last_seen_at DESC',
            ['user_id' => $userId],
        );

        return is_array($rows) ? array_values($rows) : [];
    }

    public function deleteOwned(string $endpoint, int $userId): void
    {
        $this->database->execute(
            'DELETE FROM `' . $this->table('push_subscriptions') . '` '
            . 'WHERE endpoint_hash = :endpoint_hash AND user_id = :user_id',
            ['endpoint_hash' => hash('sha256', trim($endpoint)), 'user_id' => $userId],
            false,
        );
    }

    public function deleteByHash(string $endpointHash): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $endpointHash)) {
            return;
        }
        $this->database->execute(
            'DELETE FROM `' . $this->table('push_subscriptions') . '` WHERE endpoint_hash = :endpoint_hash',
            ['endpoint_hash' => $endpointHash],
            false,
        );
    }

    private function validKey(string $value, int $minimum, int $maximum): bool
    {
        $length = strlen($value);
        return $length >= $minimum && $length <= $maximum && preg_match('/^[A-Za-z0-9_-]+={0,2}$/', $value) === 1;
    }

    private function plainText(string $value, int $maximum): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', trim(strip_tags($value))) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';
        return mb_substr($value, 0, $maximum);
    }

    private function table(string $name): string
    {
        return (defined('PSM_DB_PREFIX') ? (string) PSM_DB_PREFIX : 'psm_') . $name;
    }
}
