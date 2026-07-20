<?php

declare(strict_types=1);

namespace psm\Notification\Channel;

final readonly class WebPushSendResult
{
    private function __construct(
        public bool $successful,
        public bool $expired,
        public bool $temporary,
        public string $reason,
    ) {
    }

    public static function success(): self
    {
        return new self(true, false, false, 'Delivered');
    }

    public static function expired(string $reason): self
    {
        return new self(false, true, false, $reason);
    }

    public static function temporaryFailure(string $reason): self
    {
        return new self(false, false, true, $reason);
    }

    public static function permanentFailure(string $reason): self
    {
        return new self(false, false, false, $reason);
    }
}
