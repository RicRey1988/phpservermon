<?php

declare(strict_types=1);

namespace Tests\Unit\Statistics;

use PHPUnit\Framework\TestCase;

final class StatisticsControllerContractTest extends TestCase
{
    public function testServerModuleAndMenuExposeStatisticsAfterStatus(): void
    {
        $root = dirname(__DIR__, 3);
        $module = file_get_contents($root . '/src/psm/Module/Server/ServerModule.php');
        $controller = file_get_contents($root . '/src/psm/Module/Server/Controller/StatisticsController.php');
        $abstract = file_get_contents($root . '/src/psm/Module/AbstractController.php');

        self::assertIsString($module);
        self::assertIsString($controller);
        self::assertIsString($abstract);
        self::assertStringContainsString("'statistics' =>", $module);
        self::assertStringContainsString("'server_status', 'server_statistics'", $abstract);
        self::assertStringContainsString("'server_statistics' => 'chart-line'", $abstract);
        self::assertStringContainsString('StatisticsRange::tryFrom', $controller);
        self::assertStringContainsString("'Cache-Control', 'no-store, private'", $controller);
    }
}
