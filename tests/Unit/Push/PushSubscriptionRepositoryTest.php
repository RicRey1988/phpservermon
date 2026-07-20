<?php

declare(strict_types=1);

namespace Tests\Unit\Push;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use psm\Service\Database;
use psm\Service\Push\PushSubscriptionRepository;

final class PushSubscriptionRepositoryTest extends TestCase
{
    public function testUpsertValidatesAndNormalizesADeviceSubscription(): void
    {
        $database = new RecordingPushDatabase();
        $repository = new PushSubscriptionRepository($database);

        $repository->upsert(7, [
            'endpoint' => 'https://push.example.test/subscriptions/device-1',
            'keys' => ['p256dh' => str_repeat('A', 87), 'auth' => str_repeat('b', 22)],
            'contentEncoding' => 'aes128gcm',
            'deviceName' => "  Oficina\nChrome  ",
        ], 'Browser/1.0');

        self::assertStringContainsString('ON DUPLICATE KEY UPDATE', $database->queries[0][0]);
        self::assertSame(7, $database->queries[0][1]['user_id']);
        self::assertSame(hash('sha256', 'https://push.example.test/subscriptions/device-1'), $database->queries[0][1]['endpoint_hash']);
        self::assertSame('Oficina Chrome', $database->queries[0][1]['device_name']);
    }

    #[DataProvider('invalidSubscriptions')]
    public function testRejectsInvalidSubscriptionMaterial(array $subscription): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new PushSubscriptionRepository(new RecordingPushDatabase()))->upsert(1, $subscription, 'Browser');
    }

    public static function invalidSubscriptions(): iterable
    {
        $valid = [
            'endpoint' => 'https://push.example.test/device',
            'keys' => ['p256dh' => str_repeat('A', 87), 'auth' => str_repeat('b', 22)],
            'contentEncoding' => 'aes128gcm',
            'deviceName' => 'Device',
        ];

        yield 'insecure endpoint' => [array_replace($valid, ['endpoint' => 'http://push.example.test/device'])];
        yield 'invalid public key' => [array_replace_recursive($valid, ['keys' => ['p256dh' => '<script>']])];
        yield 'invalid auth key' => [array_replace_recursive($valid, ['keys' => ['auth' => '***']])];
        yield 'unsupported encoding' => [array_replace($valid, ['contentEncoding' => 'gzip'])];
    }

    public function testDeleteAlwaysScopesEndpointToCurrentOwner(): void
    {
        $database = new RecordingPushDatabase();
        (new PushSubscriptionRepository($database))->deleteOwned('https://push.example.test/device', 91);

        self::assertStringContainsString('user_id = :user_id', $database->queries[0][0]);
        self::assertSame(91, $database->queries[0][1]['user_id']);
    }
}

final class RecordingPushDatabase extends Database
{
    /** @var list<array{0:string,1:array<string, mixed>}> */
    public array $queries = [];

    public function execute($query, $parameters, $fetch = true)
    {
        $this->queries[] = [(string) $query, $parameters];
        return [];
    }
}
