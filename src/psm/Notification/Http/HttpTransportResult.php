<?php

declare(strict_types=1);

namespace psm\Notification\Http;

final readonly class HttpTransportResult
{
    private const SUCCESS = 'success';
    private const TEMPORARY_FAILURE = 'temporary_failure';
    private const PERMANENT_FAILURE = 'permanent_failure';

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(
        private string $status,
        private string $message,
        private int $attempts,
        private array $data = [],
        private ?int $statusCode = null
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function success(array $data, int $attempts, int $statusCode): self
    {
        return new self(self::SUCCESS, 'Delivered', $attempts, $data, $statusCode);
    }

    public static function temporaryFailure(string $message, int $attempts, ?int $statusCode = null): self
    {
        return new self(self::TEMPORARY_FAILURE, $message, $attempts, [], $statusCode);
    }

    public static function permanentFailure(string $message, int $attempts, ?int $statusCode = null): self
    {
        return new self(self::PERMANENT_FAILURE, $message, $attempts, [], $statusCode);
    }

    public function isSuccess(): bool
    {
        return $this->status === self::SUCCESS;
    }

    public function isTemporaryFailure(): bool
    {
        return $this->status === self::TEMPORARY_FAILURE;
    }

    public function isPermanentFailure(): bool
    {
        return $this->status === self::PERMANENT_FAILURE;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return $this->data;
    }

    public function statusCode(): ?int
    {
        return $this->statusCode;
    }
}
