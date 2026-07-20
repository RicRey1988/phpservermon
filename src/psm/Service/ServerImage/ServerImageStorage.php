<?php

declare(strict_types=1);

namespace psm\Service\ServerImage;

use InvalidArgumentException;
use RuntimeException;

final class ServerImageStorage
{
    public function __construct(
        private readonly string $directory,
        private readonly string $publicPrefix,
        private readonly string $genericUrl,
    ) {
    }

    public function store(int $serverId, ProcessedImage $image): string
    {
        if ($serverId < 1 || $image->extension !== 'webp') {
            throw new InvalidArgumentException('Invalid server image target.');
        }
        if (!is_dir($this->directory) && !mkdir($this->directory, 0750, true) && !is_dir($this->directory)) {
            throw new RuntimeException('The server image directory could not be created.');
        }

        $fileName = $this->fileNameFor($serverId);
        $target = $this->directory . DIRECTORY_SEPARATOR . $fileName;
        $temporary = tempnam($this->directory, '.server-image-');
        if ($temporary === false) {
            throw new RuntimeException('A temporary server image could not be created.');
        }

        try {
            if (file_put_contents($temporary, $image->bytes, LOCK_EX) !== strlen($image->bytes)) {
                throw new RuntimeException('The server image could not be written.');
            }
            @chmod($temporary, 0640);
            if (!@rename($temporary, $target)) {
                throw new RuntimeException('The server image could not be activated.');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }

        return $fileName;
    }

    public function urlFor(?string $databaseValue): string
    {
        if ($databaseValue === null || !preg_match('/^server-[1-9][0-9]*\.webp$/D', $databaseValue)) {
            return $this->genericUrl;
        }
        if (!is_file($this->directory . DIRECTORY_SEPARATOR . $databaseValue)) {
            return $this->genericUrl;
        }

        return rtrim($this->publicPrefix, '/') . '/' . $databaseValue;
    }

    public function delete(int $serverId): void
    {
        if ($serverId < 1) {
            return;
        }
        $path = $this->directory . DIRECTORY_SEPARATOR . $this->fileNameFor($serverId);
        if (is_file($path) && !@unlink($path)) {
            throw new RuntimeException('The server image could not be deleted.');
        }
    }

    public function fileNameFor(int $serverId): string
    {
        if ($serverId < 1) {
            throw new InvalidArgumentException('Server ID must be positive.');
        }

        return 'server-' . $serverId . '.webp';
    }
}
