<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use psm\Service\PhpInfoService;
use psm\Util\Install\PlatformRequirements;

final class PhpInfoServiceTest extends TestCase
{
    public function testCollectReturnsOnlySafeKeys(): void
    {
        $data = (new PhpInfoService(new PlatformRequirements()))->collect();

        self::assertSame([
            'application_version',
            'php_version',
            'sapi',
            'os',
            'php_ini',
            'memory_limit',
            'upload_max_filesize',
            'post_max_size',
            'max_execution_time',
            'timezone',
            'opcache',
            'platform',
        ], array_keys($data));
        self::assertArrayNotHasKey('environment', $data);
        self::assertStringNotContainsString('DB_', json_encode($data, JSON_THROW_ON_ERROR));
        self::assertArrayNotHasKey('variables', $data);
    }
}
