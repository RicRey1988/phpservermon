<?php

declare(strict_types=1);

namespace psm\Notification\Channel;

use psm\Notification\DeliveryResult;
use psm\Notification\Http\RetryingHttpTransport;
use psm\Notification\NotificationChannelInterface;
use psm\Notification\NotificationMessage;
use psm\Notification\Recipient;

final readonly class DiscordChannel implements NotificationChannelInterface
{
    public function __construct(private RetryingHttpTransport $http)
    {
    }

    public function name(): string
    {
        return 'discord';
    }

    public function send(NotificationMessage $message, Recipient $recipient): DeliveryResult
    {
        $url = trim((string) $recipient->value('discord'));
        if (!$this->isHttpUrl($url)) {
            return DeliveryResult::skipped('No valid Discord webhook is configured for this recipient.');
        }

        $content = str_replace(['<b>', '</b>', '<ul>', '</ul>', '<li>', '</li>', '<br>', '<br/>'],
            ['**', '**', '', '', ' * ', "\n", "\n", "\n"], $message->body());
        for ($offset = 0, $length = mb_strlen($content); $offset < max(1, $length); $offset += 1900) {
            $result = $this->http->post($url, [
                'json' => ['content' => mb_substr($content, $offset, 1900)],
                'psm_expect_json' => false,
            ]);
            if ($result->isTemporaryFailure()) {
                return DeliveryResult::temporaryFailure('Discord is temporarily unavailable.');
            }
            if (!$result->isSuccess()) {
                return DeliveryResult::permanentFailure('Discord rejected the notification.');
            }
        }

        return DeliveryResult::success('Discord notification delivered.');
    }

    private function isHttpUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
