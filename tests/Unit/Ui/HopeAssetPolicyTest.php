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

    public function testAuthenticHopeUiCustomizerAssetsArePresent(): void
    {
        $root = dirname(__DIR__, 3) . '/src/templates/default/static/hope';

        self::assertFileExists($root . '/css/customizer.min.css');
        self::assertFileExists($root . '/js/hope-ui.js');
        self::assertFileExists($root . '/js/plugins/setting.js');

        foreach (range(1, 5) as $shape) {
            self::assertFileExists(sprintf('%s/images/shapes/%02d.png', $root, $shape));
        }

        foreach (['light', 'dark'] as $scheme) {
            foreach (range(1, 13) as $preview) {
                self::assertFileExists(sprintf('%s/images/settings/%s/%02d.png', $root, $scheme, $preview));
            }
        }
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

    public function testHopeRuntimeDoesNotDependOnDataTablesOrJquery(): void
    {
        $runtime = file_get_contents(dirname(__DIR__, 3) . '/src/templates/default/static/hope/js/hope-ui.js');

        self::assertIsString($runtime);
        self::assertStringNotContainsString('DataTable', $runtime);
        self::assertStringNotContainsString('$.fn', $runtime);
    }
}
