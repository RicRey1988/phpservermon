<?php

declare(strict_types=1);

namespace Tests\Unit\System;

use PHPUnit\Framework\TestCase;
use psm\Service\System\SystemHealthService;

final class SystemHealthServiceTest extends TestCase
{
    public function testReturnsSafeActionableChecksWithoutEnvironmentSecrets(): void
    {
        $root = sys_get_temp_dir() . '/psm-health-' . bin2hex(random_bytes(4));
        mkdir($root . '/logs', 0777, true);
        file_put_contents($root . '/logs/status.cron.lock', '');

        $checks = (new SystemHealthService($root, $root . '/logs'))->collect();
        $encoded = json_encode($checks, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('runtime', $checks);
        self::assertArrayHasKey('filesystem', $checks);
        self::assertArrayHasKey('cron', $checks);
        self::assertStringNotContainsString('PASSWORD', strtoupper($encoded));
        self::assertStringNotContainsString('TOKEN=', strtoupper($encoded));
        unlink($root . '/logs/status.cron.lock');
        rmdir($root . '/logs');
        rmdir($root);
    }
}
