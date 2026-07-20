<?php

declare(strict_types=1);

namespace Tests\Unit\Notification;

use PHPUnit\Framework\TestCase;
use psm\Notification\Channel\PushoverChannel;
use psm\Notification\Http\RetryingHttpTransport;
use psm\Notification\NotificationMessage;
use psm\Notification\Recipient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PushoverChannelTest extends TestCase
{
    public function testPostsExpectedFieldsAndCriticalPriority(): void
    {
        $form = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$form): MockResponse {
            $form = self::form($options);
            return new MockResponse('{"status":1,"request":"id"}', ['http_code' => 200]);
        });
        $channel = new PushoverChannel(new RetryingHttpTransport($client, 1), 'app-token');

        $result = $channel->send(
            new NotificationMessage('Down', 'Server down', 'https://monitor.test', true),
            new Recipient(8, ['pushover_key' => 'user-key', 'pushover_device' => 'phone'])
        );

        self::assertTrue($result->isSuccess());
        self::assertSame('app-token', $form['token']);
        self::assertSame('user-key', $form['user']);
        self::assertSame('Server down', $form['message']);
        self::assertSame('Down', $form['title']);
        self::assertSame('phone', $form['device']);
        self::assertSame('2', $form['priority']);
        self::assertSame('300', $form['retry']);
        self::assertSame('3600', $form['expire']);
    }

    public function testApiStatusZeroIsPermanentFailure(): void
    {
        $client = new MockHttpClient(new MockResponse('{"status":0,"errors":["bad token"]}', ['http_code' => 200]));

        $result = (new PushoverChannel(new RetryingHttpTransport($client, 1), 'token'))->send(
            new NotificationMessage('Test', 'Test'),
            new Recipient(1, ['pushover_key' => 'user'])
        );

        self::assertSame('permanent_failure', $result->status());
        self::assertStringNotContainsString('token', $result->message());
    }

    public function testMissingConfigurationIsSkipped(): void
    {
        $transport = new RetryingHttpTransport(new MockHttpClient(), 1);

        self::assertSame('skipped', (new PushoverChannel($transport, ''))->send(
            new NotificationMessage('', 'test'),
            new Recipient(1, ['pushover_key' => 'user'])
        )->status());
        self::assertSame('skipped', (new PushoverChannel($transport, 'token'))->send(
            new NotificationMessage('', 'test'),
            new Recipient(1)
        )->status());
    }

    /** @param array<string, mixed> $options
     *  @return array<string, string>
     */
    private static function form(array $options): array
    {
        if (is_array($options['body'])) {
            return array_map('strval', $options['body']);
        }

        parse_str((string) $options['body'], $form);
        return array_map('strval', $form);
    }
}
