<?php

declare(strict_types=1);

namespace psm\Service\SystemUpdate;

use RuntimeException;

final readonly class MaintenanceMode
{
    public function __construct(private string $root)
    {
    }

    public function enable(): string
    {
        $directory = $this->directory();
        if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
            throw new RuntimeException('Maintenance mode could not be enabled.');
        }
        $requestId = 'UPDATE-' . gmdate('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $payload = json_encode([
            'message' => 'PHP Server Monitor se está actualizando. Intenta de nuevo en unos minutos.',
            'request_id' => $requestId,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $temporary = $this->marker() . '.new';
        if (file_put_contents($temporary, $payload, LOCK_EX) === false || !rename($temporary, $this->marker())) {
            @unlink($temporary);
            throw new RuntimeException('Maintenance mode could not be enabled.');
        }

        return $requestId;
    }

    public function disable(): void
    {
        if (is_file($this->marker())) {
            @unlink($this->marker());
        }
    }

    private function directory(): string
    {
        return rtrim($this->root, '/\\') . DIRECTORY_SEPARATOR . '.psm-update';
    }

    private function marker(): string
    {
        return $this->directory() . DIRECTORY_SEPARATOR . 'maintenance.json';
    }
}
