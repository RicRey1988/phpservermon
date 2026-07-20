<?php

declare(strict_types=1);

namespace psm\Notification;

interface NotificationChannelInterface
{
    public function name(): string;

    public function send(NotificationMessage $message, Recipient $recipient): DeliveryResult;
}
