<?php

declare(strict_types=1);

namespace psm\Notification\Channel;

use psm\Notification\DeliveryResult;
use psm\Notification\Http\RetryingHttpTransport;
use psm\Notification\NotificationChannelInterface;
use psm\Notification\NotificationMessage;
use psm\Notification\Recipient;

final readonly class WebhookChannel implements NotificationChannelInterface
{
    public function __construct(private RetryingHttpTransport $http)
    {
    }

    public function name(): string
    {
        return 'webhook';
    }

    public function send(NotificationMessage $message, Recipient $recipient): DeliveryResult
    {
        $url = trim((string) $recipient->value('webhook_url'));
        if (!$this->isHttpUrl($url)) {
            return DeliveryResult::skipped('No valid webhook URL is configured for this recipient.');
        }

        $template = trim((string) $recipient->value('webhook_json'));
        if ($template === '') {
            return DeliveryResult::skipped('No webhook JSON template is configured for this recipient.');
        }

        $replacements = ['#message' => trim(strip_tags(str_replace(['<br>', '<br/>'], "\n", $message->body()))), '#subject' => $message->subject()];
        foreach ($message->context() as $key => $value) {
            $replacements['#' . $key] = (string) ($value ?? '');
        }
        $json = str_replace(array_keys($replacements), array_values($replacements), $template);
        try {
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return DeliveryResult::permanentFailure('Webhook JSON template is invalid.');
        }
        if (!is_array($payload)) {
            return DeliveryResult::permanentFailure('Webhook JSON template is invalid.');
        }

        $result = $this->http->post($url, ['json' => $payload, 'psm_expect_json' => false]);
        if ($result->isTemporaryFailure()) {
            return DeliveryResult::temporaryFailure('Webhook endpoint is temporarily unavailable.');
        }
        if (!$result->isSuccess()) {
            return DeliveryResult::permanentFailure('Webhook endpoint rejected the notification.');
        }

        return DeliveryResult::success('Webhook notification delivered.');
    }

    private function isHttpUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
