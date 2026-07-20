<?php

declare(strict_types=1);

namespace psm\Service\UserMedia;

use InvalidArgumentException;
use psm\Service\ServerImage\ProcessedImage;
use RuntimeException;

final class UserMediaStorage
{
    public function __construct(private readonly string $directory, private readonly string $publicPrefix)
    {
    }

    public function storeLogo(ProcessedImage $image): string
    {
        return $this->store('site-logo.webp', $image);
    }

    public function storeAvatar(int $userId, ProcessedImage $image): string
    {
        return $this->store($this->avatarFileName($userId), $image);
    }

    public function logoUrl(?string $value): ?string
    {
        return $this->urlFor($value, 'site-logo.webp');
    }

    public function avatarUrl(int $userId, ?string $value): ?string
    {
        return $this->urlFor($value, $this->avatarFileName($userId));
    }

    public function deleteLogo(): void
    {
        $this->delete('site-logo.webp');
    }

    public function deleteAvatar(int $userId): void
    {
        $this->delete($this->avatarFileName($userId));
    }

    private function store(string $fileName, ProcessedImage $image): string
    {
        if ($image->extension !== 'webp') {
            throw new InvalidArgumentException('Identity images must be WebP.');
        }
        if (!is_dir($this->directory) && !mkdir($this->directory, 0750, true) && !is_dir($this->directory)) {
            throw new RuntimeException('No se pudo crear el directorio de imágenes de identidad.');
        }
        $temporary = tempnam($this->directory, '.identity-');
        if ($temporary === false) {
            throw new RuntimeException('No se pudo preparar la imagen.');
        }
        $target = $this->directory . DIRECTORY_SEPARATOR . $fileName;
        try {
            if (file_put_contents($temporary, $image->bytes, LOCK_EX) !== strlen($image->bytes)) {
                throw new RuntimeException('No se pudo escribir la imagen.');
            }
            @chmod($temporary, 0640);
            if (!@rename($temporary, $target)) {
                throw new RuntimeException('No se pudo activar la imagen.');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
        return $fileName;
    }

    private function urlFor(?string $value, string $expected): ?string
    {
        if ($value !== $expected || !is_file($this->directory . DIRECTORY_SEPARATOR . $expected)) {
            return null;
        }
        return rtrim($this->publicPrefix, '/') . '/' . $expected;
    }

    private function delete(string $fileName): void
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . $fileName;
        if (is_file($path) && !@unlink($path)) {
            throw new RuntimeException('No se pudo eliminar la imagen.');
        }
    }

    private function avatarFileName(int $userId): string
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('User ID must be positive.');
        }
        return 'avatar-' . $userId . '.webp';
    }
}
