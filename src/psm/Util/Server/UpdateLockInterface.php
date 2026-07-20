<?php

declare(strict_types=1);

namespace psm\Util\Server;

interface UpdateLockInterface
{
    public function acquire(): bool;

    public function release(): void;
}
