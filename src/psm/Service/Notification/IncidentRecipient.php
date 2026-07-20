<?php

declare(strict_types=1);

namespace psm\Service\Notification;

use psm\Notification\Recipient;

final readonly class IncidentRecipient
{
    /** @param list<string> $channels */
    public function __construct(public Recipient $recipient, public array $channels)
    {
    }
}
