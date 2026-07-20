<?php

declare(strict_types=1);

namespace psm\Util\Server;

final readonly class UpdateSummary
{
    /** @param array<int, string> $errors */
    public function __construct(
        private int $processed,
        private int $failed,
        private array $errors = []
    ) {
    }

    public function processed(): int
    {
        return $this->processed;
    }

    public function failed(): int
    {
        return $this->failed;
    }

    /** @return array<int, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function isSuccess(): bool
    {
        return $this->failed === 0;
    }
}
