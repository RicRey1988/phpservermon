<?php

declare(strict_types=1);

namespace psm\Notification;

final readonly class NotificationMessage
{
    public function __construct(
        private string $subject,
        private string $body,
        private ?string $url = null,
        private bool $critical = false
    ) {
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function url(): ?string
    {
        return $this->url;
    }

    public function isCritical(): bool
    {
        return $this->critical;
    }
}
