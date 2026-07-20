<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TemplateAssetTest extends TestCase
{
    public function testOnlyBundledHopeUiCssIsVersionedAndServerImagesAreBounded(): void
    {
        $root = dirname(__DIR__, 2);
        $body = file_get_contents($root . '/src/templates/default/main/body.tpl.html');
        $cards = file_get_contents($root . '/src/templates/default/module/server/status/cards.tpl.html');
        $editor = file_get_contents($root . '/src/templates/default/module/server/server/update.tpl.html');

        self::assertIsString($body);
        self::assertIsString($cards);
        self::assertIsString($editor);
        self::assertFileDoesNotExist($root . '/src/templates/default/static/css/hs-monitor.css');
        foreach (['hope-ui.min.css', 'dark.min.css', 'customizer.min.css'] as $asset) {
            self::assertStringContainsString($asset . '?v={{ version|url_encode }}', $body);
        }
        self::assertStringNotContainsString('hs-monitor.css', $body);
        self::assertStringContainsString('width="96" height="96"', $cards);
        self::assertStringContainsString('class="img-fluid"', $cards);
        self::assertStringContainsString('overflow-hidden', $cards);
        self::assertStringContainsString('width="112" height="112"', $editor);
        self::assertStringContainsString('class="img-thumbnail"', $editor);
    }

    public function testHistoryRuntimeIsNativeAndVersionedToEvictLegacyJqueryCache(): void
    {
        $root = dirname(__DIR__, 2);
        $template = file_get_contents($root . '/src/templates/default/module/server/history.tpl.html');
        $runtime = file_get_contents($root . '/src/templates/default/static/js/history.js');

        self::assertIsString($template);
        self::assertIsString($runtime);
        self::assertStringContainsString(
            "history.js?v={{ constant('PSM_VERSION')|url_encode }}",
            $template
        );
        self::assertStringNotContainsString('$(document)', $runtime);
        self::assertStringNotContainsString('jQuery', $runtime);
    }
}
