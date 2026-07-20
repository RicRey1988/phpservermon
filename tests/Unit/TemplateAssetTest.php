<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TemplateAssetTest extends TestCase
{
    public function testHsComponentCssIsVersionedAndServerImagesHaveFixedBox(): void
    {
        $root = dirname(__DIR__, 2);
        $body = file_get_contents($root . '/src/templates/default/main/body.tpl.html');
        $css = file_get_contents($root . '/src/templates/default/static/css/hs-monitor.css');

        self::assertIsString($body);
        self::assertIsString($css);
        self::assertStringContainsString('hs-monitor.css?v={{ version|url_encode }}', $body);
        self::assertMatchesRegularExpression('/\.server-image-box\s*\{[^}]*width:\s*6rem;[^}]*height:\s*6rem;/s', $css);
        self::assertStringContainsString('overflow:hidden', str_replace(' ', '', $css));
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
