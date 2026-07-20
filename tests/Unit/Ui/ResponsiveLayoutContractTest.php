<?php

declare(strict_types=1);

namespace Tests\Unit\Ui;

use PHPUnit\Framework\TestCase;

final class ResponsiveLayoutContractTest extends TestCase
{
    public function testStylesFixOverflowAtTheComponentInsteadOfHidingIt(): void
    {
        $css = file_get_contents(dirname(__DIR__, 3) . '/src/templates/default/static/css/app-shell.css');
        self::assertIsString($css);
        self::assertDoesNotMatchRegularExpression('/body\s*\{[^}]*overflow-x\s*:\s*(?:hidden|clip)/s', $css);
        self::assertDoesNotMatchRegularExpression('/(?:width|min-width)\s*:\s*\d+vw/', $css);
        self::assertStringContainsString('min-width: 0', $css);
        self::assertStringContainsString('max-width: 100%', $css);
        self::assertStringContainsString('overflow-wrap:anywhere', str_replace(' ', '', $css));
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
    }
}
