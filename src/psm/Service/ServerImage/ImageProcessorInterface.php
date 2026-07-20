<?php

declare(strict_types=1);

namespace psm\Service\ServerImage;

interface ImageProcessorInterface
{
    public function process(string $temporaryPath): ProcessedImage;
}
