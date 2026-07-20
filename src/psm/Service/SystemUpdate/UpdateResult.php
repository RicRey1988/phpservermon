<?php

declare(strict_types=1);

namespace psm\Service\SystemUpdate;

final readonly class UpdateResult
{
    public function __construct(public string $version, public int $changedFiles, public bool $databaseUpgradeRequired)
    {
    }
}
