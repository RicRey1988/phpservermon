<?php

declare(strict_types=1);

namespace psm\Notification\Channel;

use psm\Notification\DeliveryResult;
use psm\Notification\NotificationChannelInterface;
use psm\Notification\NotificationMessage;
use psm\Notification\Recipient;

final class EmailChannel implements NotificationChannelInterface
{
    public function name(): string
    {
        return 'email';
    }

    public function send(NotificationMessage $message, Recipient $recipient): DeliveryResult
    {
        $email = trim((string) $recipient->value('email'));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return DeliveryResult::skipped('No valid email address is configured for this recipient.');
        }

        $mail = psm_build_mail();
        try {
            $mail->Subject = $message->subject();
            $mail->Body = $message->body();
            $mail->AltBody = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>'], "\n", $message->body()))));
            $mail->Priority = $message->isCritical() ? 1 : 3;
            $mail->addAddress($email, (string) $recipient->value('name'));

            if (!$mail->send()) {
                return DeliveryResult::permanentFailure('Email delivery was rejected.');
            }

            return DeliveryResult::success('Email notification delivered.');
        } catch (\Throwable) {
            return DeliveryResult::temporaryFailure('Email delivery failed.');
        } finally {
            $mail->clearAddresses();
        }
    }
}
