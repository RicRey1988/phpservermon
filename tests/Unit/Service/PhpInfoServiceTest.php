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

        self::assertSame(['runtime', 'limits', 'acceleration', 'platform'], array_keys($data));
        self::assertSame(PSM_VERSION, $data['runtime']['application_version']);
        self::assertArrayHasKey('php_version', $data['runtime']);
        self::assertArrayHasKey('session_lifetime', $data['limits']);
        self::assertArrayHasKey('loaded_extensions', $data['runtime']);
        self::assertArrayHasKey('opcache', $data['acceleration']);
        self::assertArrayNotHasKey('environment', $data);
        self::assertStringNotContainsString('DB_', json_encode($data, JSON_THROW_ON_ERROR));
        self::assertArrayNotHasKey('variables', $data);
    }
}
