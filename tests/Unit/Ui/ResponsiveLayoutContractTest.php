<?php

declare(strict_types=1);

namespace Tests\Unit\Ui;

use PHPUnit\Framework\TestCase;

final class ResponsiveLayoutContractTest extends TestCase
{
    public function testTemplatesFixOverflowAtTheComponentWithNativeUtilities(): void
    {
        $root = dirname(__DIR__, 3);
        $body = file_get_contents($root . '/src/templates/default/main/body.tpl.html');
        $status = file_get_contents($root . '/src/templates/default/module/server/status/index.tpl.html');
        $detail = file_get_contents($root . '/src/templates/default/module/server/server/view.tpl.html');
        $config = file_get_contents($root . '/src/templates/default/module/config/config.tpl.html');

        self::assertIsString($body);
        self::assertIsString($status);
        self::assertIsString($detail);
        self::assertIsString($config);
        self::assertFileDoesNotExist($root . '/src/templates/default/static/css/hs-monitor.css');
        self::assertStringContainsString('container-fluid content-inner', $body);
        self::assertStringContainsString('row row-cols-1 row-cols-md-2 row-cols-xl-3', $status);
        self::assertStringContainsString('flex-nowrap overflow-auto', $detail);
        self::assertStringContainsString('text-break', $detail);
        self::assertStringContainsString('img-fluid', $detail);
        self::assertStringContainsString('flex-nowrap gap-2 col-12 col-xl-3 overflow-auto', $config);

        $templates = $body . $status . $detail . $config;
        self::assertDoesNotMatchRegularExpression('/(?:width|min-width)\s*:\s*\d+vw/i', $templates);
        self::assertStringNotContainsString(' style=', $templates);
    }

    public function testResponsiveAuditCoversRequiredWidthsAndThemes(): void
    {
        $script = file_get_contents(dirname(__DIR__, 3) . '/dev/verify-hope-ui-layout.mjs');
        self::assertIsString($script);
        foreach ([360, 390, 768, 1024, 1366, 1600] as $width) {
            self::assertStringContainsString((string) $width, $script);
        }
        foreach (['light', 'dark'] as $theme) {
            self::assertStringContainsString("'{$theme}'", $script);
        }
        self::assertStringContainsString('scrollWidth', $script);
        self::assertStringContainsString('[data-theme-quick-toggle]', $script);
        self::assertStringContainsString('#hope-ui-settings', $script);
        foreach (['auditNavigationChrome', 'visibleThemeIcons', 'duplicateSidebarSession', 'alignedNavbarControls', 'mobileSidebar', 'desktopSidebarArrow'] as $contract) {
            self::assertStringContainsString($contract, $script);
        }
        self::assertStringContainsString('.sidebar-header [data-toggle="sidebar"]', $script);
        self::assertStringContainsString('.iq-navbar [data-toggle="sidebar"]', $script);
        self::assertStringContainsString('PSM_BROWSER_EXECUTABLE', $script);
    }
}
