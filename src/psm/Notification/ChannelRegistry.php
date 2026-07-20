<?php

declare(strict_types=1);

namespace psm\Notification;

final class ChannelRegistry
{
    /** @var array<string, NotificationChannelInterface> */
    private array $channels = [];

    /** @param iterable<NotificationChannelInterface> $channels */
    public function __construct(iterable $channels = [])
    {
        foreach ($channels as $channel) {
            $this->channels[$channel->name()] = $channel;
        }
    }

    public function get(string $name): NotificationChannelInterface
    {
        if (!isset($this->channels[$name])) {
            throw new \InvalidArgumentException('Unknown notification channel: ' . $name);
        }

        return $this->channels[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->channels[$name]);
    }
}
