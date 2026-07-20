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

    public function testApplicationLoadsOnlyBundledHopeUiStyles(): void
    {
        $root = dirname(__DIR__, 3);
        $body = file_get_contents($root . '/src/templates/default/main/body.tpl.html');

        self::assertIsString($body);
        self::assertFileDoesNotExist($root . '/src/templates/default/static/css/hs-monitor.css');
        foreach (['hope-ui.min.css', 'dark.min.css', 'customizer.min.css'] as $asset) {
            self::assertStringContainsString('static/hope/css/' . $asset, $body);
        }
        self::assertStringNotContainsString('hs-monitor.css', $body);
        self::assertStringNotContainsString('static/css/custom.css', $body);
        self::assertStringNotContainsString('static/css/app-shell.css', $body);
        self::assertStringNotContainsString('font-awesome', $body);
        self::assertFileDoesNotExist($root . '/src/templates/default/static/css/custom.css');
        self::assertFileDoesNotExist($root . '/src/templates/default/static/css/app-shell.css');
    }

    public function testShellUsesLocalHopeSvgIconLibrary(): void
    {
        $root = dirname(__DIR__, 3) . '/src/templates/default/main/';
        $icons = file_get_contents($root . 'icons.tpl.html');
        $shell = file_get_contents($root . 'body.tpl.html')
            . file_get_contents($root . 'menu.tpl.html')
            . file_get_contents($root . 'app-navbar.tpl.html')
            . file_get_contents($root . 'appearance-customizer.tpl.html');

        self::assertIsString($icons);
        self::assertStringContainsString('{% macro icon(', $icons);
        self::assertStringContainsString('class="icon-20 {{ className|default', $icons);
        self::assertStringContainsString("import 'main/icons.tpl.html'", $shell);
        self::assertDoesNotMatchRegularExpression('/\\bfa(?:s|r|b)?\\s+fa-|\\bfa-[a-z]/', $shell);
    }

    public function testApplicationTemplatesDoNotUseFontAwesomeMarkup(): void
    {
        $root = dirname(__DIR__, 3) . '/src/templates/default';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($files as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.tpl.html')) {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents);
            self::assertDoesNotMatchRegularExpression('/\\bfa(?:s|r|b)?\\s+fa-|\\bfa-[a-z]/', $contents, $file->getPathname());
        }
    }

    public function testHopeRuntimeDoesNotDependOnDataTablesOrJquery(): void
    {
        $runtime = file_get_contents(dirname(__DIR__, 3) . '/src/templates/default/static/hope/js/hope-ui.js');

        self::assertIsString($runtime);
        self::assertStringNotContainsString('DataTable', $runtime);
        self::assertStringNotContainsString('$.fn', $runtime);
        self::assertStringContainsString("typeof window.Scrollbar !== 'undefined'", $runtime);
    }
}
