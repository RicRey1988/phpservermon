<?php

declare(strict_types=1);

namespace psm\Service\SystemUpdate;

final readonly class VerifiedPackage
{
    /** @param list<string> $files @param list<string> $delete */
    public function __construct(
        public string $directory,
        public string $version,
        public array $files,
        public array $delete,
    ) {
    }
}
