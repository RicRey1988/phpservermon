<?php

declare(strict_types=1);

namespace psm\Notification\Channel;

use psm\Notification\DeliveryResult;
use psm\Notification\NotificationChannelInterface;
use psm\Notification\NotificationMessage;
use psm\Notification\Recipient;

final class SmsChannel implements NotificationChannelInterface
{
    public function name(): string
    {
        return 'sms';
    }

    public function send(NotificationMessage $message, Recipient $recipient): DeliveryResult
    {
        $mobile = trim((string) $recipient->value('mobile'));
        if ($mobile === '') {
            return DeliveryResult::skipped('No mobile number is configured for this recipient.');
        }

        $sms = psm_build_sms();
        if ($sms === null) {
            return DeliveryResult::permanentFailure('The configured SMS gateway is unavailable.');
        }

        try {
            $sms->addRecipients($mobile);
            return $sms->sendSMS($message->body())
                ? DeliveryResult::success('SMS notification delivered.')
                : DeliveryResult::permanentFailure('SMS gateway rejected the notification.');
        } catch (\Throwable) {
            return DeliveryResult::temporaryFailure('SMS delivery failed.');
        }
    }
}
