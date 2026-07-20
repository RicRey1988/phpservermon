<?php

declare(strict_types=1);

namespace psm\Service\ServerImage;

final readonly class ProcessedImage
{
    public function __construct(
        public string $bytes,
        public string $extension,
        public int $width,
        public int $height,
    ) {
    }
}
