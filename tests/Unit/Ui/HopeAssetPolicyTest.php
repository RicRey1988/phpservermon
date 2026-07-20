<?php

declare(strict_types=1);

namespace Tests\Unit\Ui;

use PHPUnit\Framework\TestCase;

final class HopeAssetPolicyTest extends TestCase
{
    public function testSlimHopeRuntimeAndNoticesArePresent(): void
    {
        $root = dirname(__DIR__, 3);

        self::assertFileExists($root . '/src/templates/default/static/hope/css/hope-ui.min.css');
        self::assertFileExists($root . '/src/templates/default/static/hope/css/dark.min.css');
        self::assertFileExists($root . '/src/templates/default/static/hope/js/bootstrap.bundle.min.js');
        self::assertFileExists($root . '/src/templates/default/static/hope/js/apexcharts.min.js');
        self::assertFileExists($root . '/THIRD_PARTY_NOTICES.md');
    }

    public function testTemplatesDoNotInitializeDataTables(): void
    {
        $root = dirname(__DIR__, 3) . '/src/templates/default';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'html') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents);
            self::assertStringNotContainsString('data-toggle="data-table"', $contents);
            self::assertStringNotContainsString('.DataTable(', $contents);
        }
    }
}
