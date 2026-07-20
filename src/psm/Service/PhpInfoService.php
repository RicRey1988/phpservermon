<?php

declare(strict_types=1);

namespace psm\Service;

use psm\Util\Install\PlatformRequirements;

final readonly class PhpInfoService
{
    public function __construct(private PlatformRequirements $requirements)
    {
    }

    /** @return array<string, mixed> */
    public function collect(): array
    {
        $opcache = ['enabled' => false, 'cache_full' => null];
        if (function_exists('opcache_get_status')) {
            $status = opcache_get_status(false);
            if (is_array($status)) {
                $opcache = [
                    'enabled' => (bool) ($status['opcache_enabled'] ?? false),
                    'cache_full' => (bool) ($status['cache_full'] ?? false),
                ];
            }
        }

        return [
            'application_version' => PSM_VERSION,
            'php_version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'os' => PHP_OS_FAMILY,
            'php_ini' => php_ini_loaded_file() ?: '(none)',
            'memory_limit' => (string) ini_get('memory_limit'),
            'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
            'post_max_size' => (string) ini_get('post_max_size'),
            'max_execution_time' => (string) ini_get('max_execution_time'),
            'timezone' => date_default_timezone_get(),
            'opcache' => $opcache,
            'platform' => $this->requirements->evaluate(),
        ];
    }
}
