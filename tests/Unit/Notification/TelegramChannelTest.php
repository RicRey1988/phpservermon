<?php

declare(strict_types=1);

namespace Tests\Unit\Notification;

use PHPUnit\Framework\TestCase;
use psm\Notification\Channel\TelegramChannel;
use psm\Notification\Http\RetryingHttpTransport;
use psm\Notification\NotificationMessage;
use psm\Notification\Recipient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TelegramChannelTest extends TestCase
{
    public function testPostsTelegramFormAndParsesSuccess(): void
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$method, $url, $options];
            return new MockResponse('{"ok":true,"result":{"message_id":1}}', ['http_code' => 200]);
        });
        $channel = new TelegramChannel(new RetryingHttpTransport($client, 1), 'token:secret');

        $result = $channel->send(
            new NotificationMessage('Subject', '<unsafe> & body'),
            new Recipient(7, ['telegram_id' => '12345'])
        );

        self::assertTrue($result->isSuccess());
        self::assertSame('POST', $requests[0][0]);
        self::assertStringContainsString('/sendMessage', $requests[0][1]);
        $form = self::form($requests[0][2]);
        self::assertSame('12345', $form['chat_id']);
        self::assertSame('HTML', $form['parse_mode']);
        self::assertSame('&lt;unsafe&gt; &amp; body', $form['text']);
    }

    public function testMissingTokenOrChatIdIsSkipped(): void
    {
        $transport = new RetryingHttpTransport(new MockHttpClient(), 1);

        self::assertSame('skipped', (new TelegramChannel($transport, ''))->send(
            new NotificationMessage('', 'test'),
            new Recipient(1, ['telegram_id' => '1'])
        )->status());
        self::assertSame('skipped', (new TelegramChannel($transport, 'token'))->send(
            new NotificationMessage('', 'test'),
            new Recipient(1)
        )->status());
    }

    public function testLongMessagesAreSplitWithoutContentLoss(): void
    {
        $texts = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$texts): MockResponse {
            $texts[] = self::form($options)['text'];
            return new MockResponse('{"ok":true}', ['http_code' => 200]);
        });
        $body = str_repeat('á', 5000);

        $result = (new TelegramChannel(new RetryingHttpTransport($client, 1), 'token'))->send(
            new NotificationMessage('', $body),
            new Recipient(1, ['telegram_id' => '1'])
        );

        self::assertTrue($result->isSuccess());
        self::assertGreaterThan(1, count($texts));
        self::assertSame($body, implode('', $texts));
        foreach ($texts as $text) {
            self::assertLessThanOrEqual(4096, mb_strlen($text));
        }
    }

    public function testFetchesBotUsernameThroughSharedTransport(): void
    {
        $client = new MockHttpClient(new MockResponse(
            '{"ok":true,"result":{"username":"server_monitor_bot"}}',
            ['http_code' => 200]
        ));
        $channel = new TelegramChannel(new RetryingHttpTransport($client, 1), 'token');

        self::assertSame('server_monitor_bot', $channel->botUsername());
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
