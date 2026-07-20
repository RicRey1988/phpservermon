<?php

declare(strict_types=1);

namespace psm\Notification;

final readonly class Recipient
{
    /**
     * @param array<string, scalar|null> $attributes
     */
    public function __construct(private int $userId, private array $attributes = [])
    {
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function value(string $key): ?string
    {
        $value = $this->attributes[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }
}
