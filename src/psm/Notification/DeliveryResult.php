<?php

declare(strict_types=1);

namespace psm\Notification;

final readonly class DeliveryResult
{
    public const SUCCESS = 'success';
    public const SKIPPED = 'skipped';
    public const TEMPORARY_FAILURE = 'temporary_failure';
    public const PERMANENT_FAILURE = 'permanent_failure';

    private function __construct(private string $status, private string $message)
    {
    }

    public static function success(string $message = 'Delivered'): self
    {
        return new self(self::SUCCESS, $message);
    }

    public static function skipped(string $message): self
    {
        return new self(self::SKIPPED, $message);
    }

    public static function temporaryFailure(string $message): self
    {
        return new self(self::TEMPORARY_FAILURE, $message);
    }

    public static function permanentFailure(string $message): self
    {
        return new self(self::PERMANENT_FAILURE, $message);
    }

    public function status(): string
    {
        return $this->status;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function isSuccess(): bool
    {
        return $this->status === self::SUCCESS;
    }
}
