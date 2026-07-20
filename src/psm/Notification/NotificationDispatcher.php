<?php

declare(strict_types=1);

namespace psm\Notification;

final readonly class NotificationDispatcher
{
    public function __construct(private ChannelRegistry $registry)
    {
    }

    /**
     * @param list<string> $channelNames
     * @return array<string, DeliveryResult>
     */
    public function send(array $channelNames, NotificationMessage $message, Recipient $recipient): array
    {
        $results = [];
        foreach ($channelNames as $channelName) {
            try {
                $results[$channelName] = $this->registry->get($channelName)->send($message, $recipient);
            } catch (\Throwable) {
                $results[$channelName] = DeliveryResult::temporaryFailure(
                    ucfirst($channelName) . ' delivery failed unexpectedly.'
                );
            }
        }

        return $results;
    }
}
