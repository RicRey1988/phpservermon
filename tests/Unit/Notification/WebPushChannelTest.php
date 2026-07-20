<?php

declare(strict_types=1);

namespace Tests\Unit\Notification;

use PHPUnit\Framework\TestCase;
use psm\Notification\Channel\WebPushChannel;
use psm\Notification\Channel\WebPushSendResult;
use psm\Notification\NotificationMessage;
use psm\Notification\Recipient;
use psm\Service\Push\PushSubscriptionRepository;

final class WebPushChannelTest extends TestCase
{
    public function testSendsOneSafeJsonNotificationToEveryRegisteredDevice(): void
    {
        $repository = new MemoryPushSubscriptionRepository([
            $this->subscription('one'),
            $this->subscription('two'),
        ]);
        $sent = [];
        $channel = new WebPushChannel(
            $repository,
            ['subject' => 'mailto:admin@example.test', 'publicKey' => 'public', 'privateKey' => 'private'],
            static function (array $subscription, string $payload) use (&$sent): WebPushSendResult {
                $sent[] = [$subscription, json_decode($payload, true, 512, JSON_THROW_ON_ERROR)];
                return WebPushSendResult::success();
            },
            'https://monitor.example.test/',
        );

        $result = $channel->send(
            new NotificationMessage(
                'Servidor caído',
                '<b>No disponible</b>',
                'https://evil.example/phish',
                true,
                ['incident_id' => 55, 'transition' => 'down'],
            ),
            new Recipient(7),
        );

        self::assertTrue($result->isSuccess());
        self::assertCount(2, $sent);
        self::assertSame('https://monitor.example.test/index.php?mod=server_status', $sent[0][1]['url']);
        self::assertSame('<b>No disponible</b>', $sent[0][1]['body']);
        self::assertSame('incident-55-down', $sent[0][1]['tag']);
    }

    public function testDeletesExpiredDevicesAndSurfacesTemporaryFailures(): void
    {
        $repository = new MemoryPushSubscriptionRepository([
            $this->subscription('expired'),
            $this->subscription('temporary'),
        ]);
        $channel = new WebPushChannel(
            $repository,
            ['subject' => 'mailto:admin@example.test', 'publicKey' => 'public', 'privateKey' => 'private'],
            static fn (array $subscription): WebPushSendResult => str_contains($subscription['endpoint'], 'expired')
                ? WebPushSendResult::expired('Gone')
                : WebPushSendResult::temporaryFailure('Push service unavailable'),
            'https://monitor.example.test/',
        );

        $result = $channel->send(new NotificationMessage('Alert', 'Body'), new Recipient(4));

        self::assertSame('temporary_failure', $result->status());
        self::assertSame([hash('sha256', 'https://push.example.test/expired')], $repository->deleted);
    }

    /** @return array<string, mixed> */
    private function subscription(string $device): array
    {
        return [
            'endpoint' => 'https://push.example.test/' . $device,
            'endpoint_hash' => hash('sha256', 'https://push.example.test/' . $device),
            'public_key' => str_repeat('A', 87),
            'auth_token' => str_repeat('b', 22),
            'content_encoding' => 'aes128gcm',
        ];
    }
}

final class MemoryPushSubscriptionRepository extends PushSubscriptionRepository
{
    /** @var list<string> */
    public array $deleted = [];

    /** @param list<array<string, mixed>> $subscriptions */
    public function __construct(private readonly array $subscriptions)
    {
    }

    public function forUser(int $userId): array
    {
        return $this->subscriptions;
    }

    public function deleteByHash(string $endpointHash): void
    {
        $this->deleted[] = $endpointHash;
    }
}
