<?php

declare(strict_types=1);

namespace psm\Service\SystemUpdate;

final readonly class ReleaseAsset
{
    public function __construct(
        public string $name,
        public string $downloadUrl,
        public string $sha256,
        public int $size,
    ) {
    }
}
