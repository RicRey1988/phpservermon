<?php

declare(strict_types=1);

namespace psm\Notification\Channel;

use psm\Notification\DeliveryResult;
use psm\Notification\Http\HttpTransportResult;
use psm\Notification\Http\RetryingHttpTransport;
use psm\Notification\NotificationChannelInterface;
use psm\Notification\NotificationMessage;
use psm\Notification\Recipient;

final readonly class TelegramChannel implements NotificationChannelInterface
{
    public function __construct(private RetryingHttpTransport $http, private string $token)
    {
    }

    public function name(): string
    {
        return 'telegram';
    }

    public function send(NotificationMessage $message, Recipient $recipient): DeliveryResult
    {
        $chatId = trim((string) $recipient->value('telegram_id'));
        if (trim($this->token) === '' || $chatId === '') {
            return DeliveryResult::skipped('Telegram is not configured for this recipient.');
        }

        $text = htmlspecialchars($message->body(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($message->url() !== null && trim($message->url()) !== '') {
            $text .= "\n" . htmlspecialchars($message->url(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $chunks = $this->split($text, 4096);
        $url = 'https://api.telegram.org/bot' . rawurlencode($this->token) . '/sendMessage';

        foreach ($chunks as $chunk) {
            $httpResult = $this->http->post($url, [
                'body' => [
                    'chat_id' => $chatId,
                    'text' => $chunk,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => 'true',
                ],
            ]);
            $delivery = $this->mapTransportResult($httpResult);
            if (!$delivery->isSuccess()) {
                return $delivery;
            }

            if (($httpResult->data()['ok'] ?? false) !== true) {
                return DeliveryResult::permanentFailure('Telegram rejected the notification.');
            }
        }

        return DeliveryResult::success('Telegram notification delivered.');
    }

    /** @return list<string> */
    private function split(string $text, int $length): array
    {
        if ($text === '') {
            return [''];
        }

        $chunks = [];
        for ($offset = 0, $size = mb_strlen($text); $offset < $size; $offset += $length) {
            $chunks[] = mb_substr($text, $offset, $length);
        }

        return $chunks;
    }

    private function mapTransportResult(HttpTransportResult $result): DeliveryResult
    {
        if ($result->isSuccess()) {
            return DeliveryResult::success();
        }

        if ($result->isTemporaryFailure()) {
            return DeliveryResult::temporaryFailure('Telegram is temporarily unavailable.');
        }

        return DeliveryResult::permanentFailure('Telegram rejected the notification.');
    }
}
