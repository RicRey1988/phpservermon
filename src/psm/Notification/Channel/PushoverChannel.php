<?php

declare(strict_types=1);

namespace psm\Notification\Channel;

use psm\Notification\DeliveryResult;
use psm\Notification\Http\RetryingHttpTransport;
use psm\Notification\NotificationChannelInterface;
use psm\Notification\NotificationMessage;
use psm\Notification\Recipient;

final readonly class PushoverChannel implements NotificationChannelInterface
{
    private const ENDPOINT = 'https://api.pushover.net/1/messages.json';

    public function __construct(private RetryingHttpTransport $http, private string $token)
    {
    }

    public function name(): string
    {
        return 'pushover';
    }

    public function send(NotificationMessage $message, Recipient $recipient): DeliveryResult
    {
        $userKey = trim((string) $recipient->value('pushover_key'));
        if (trim($this->token) === '' || $userKey === '') {
            return DeliveryResult::skipped('Pushover is not configured for this recipient.');
        }

        $form = [
            'token' => $this->token,
            'user' => $userKey,
            'message' => $message->body(),
            'title' => $message->subject(),
        ];
        $device = trim((string) $recipient->value('pushover_device'));
        if ($device !== '') {
            $form['device'] = $device;
        }
        if ($message->url() !== null && trim($message->url()) !== '') {
            $form['url'] = $message->url();
        }
        if ($message->isCritical()) {
            $form['priority'] = 2;
            $form['retry'] = 300;
            $form['expire'] = 3600;
        }

        $result = $this->http->post(self::ENDPOINT, ['body' => $form]);
        if ($result->isTemporaryFailure()) {
            return DeliveryResult::temporaryFailure('Pushover is temporarily unavailable.');
        }
        if (!$result->isSuccess() || ($result->data()['status'] ?? 0) !== 1) {
            return DeliveryResult::permanentFailure('Pushover rejected the notification.');
        }

        return DeliveryResult::success('Pushover notification delivered.');
    }
}
