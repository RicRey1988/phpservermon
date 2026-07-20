<?php

declare(strict_types=1);

namespace Tests\Unit\Pwa;

use PHPUnit\Framework\TestCase;

final class PwaAssetTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    public function testManifestIsInstallableAndProvidesStandardAndMaskableIcons(): void
    {
        $json = file_get_contents($this->root . '/manifest.webmanifest');
        self::assertIsString($json);
        $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('standalone', $manifest['display']);
        self::assertSame('./index.php', $manifest['start_url']);
        self::assertSame('./', $manifest['scope']);
        self::assertNotEmpty($manifest['theme_color']);
        self::assertNotEmpty($manifest['background_color']);

        $icons = [];
        foreach ($manifest['icons'] as $icon) {
            $icons[$icon['sizes'] . ':' . ($icon['purpose'] ?? 'any')] = $icon['src'];
        }
        foreach ([
            '192x192:any' => [192, 192],
            '512x512:any' => [512, 512],
            '512x512:maskable' => [512, 512],
        ] as $key => $dimensions) {
            self::assertArrayHasKey($key, $icons);
            $path = $this->root . '/' . ltrim($icons[$key], './');
            self::assertFileExists($path);
            $size = getimagesize($path);
            self::assertIsArray($size);
            self::assertSame($dimensions, [$size[0], $size[1]]);
        }
    }

    public function testServiceWorkerCachesOnlyPublicStaticAssetsAndNeverPrivateResponses(): void
    {
        $worker = $this->read('service-worker.js');

        self::assertStringContainsString("psm-static-4.2.2-hs", $worker);
        foreach (['hope-ui.min.css', 'customizer.min.css', 'hope-ui.js', 'plugins/setting.js', 'status.js', 'dashboard.js'] as $asset) {
            self::assertStringContainsString($asset, $worker);
        }
        self::assertStringContainsString("request.method !== 'GET'", $worker);
        self::assertStringContainsString('/src/templates/default/static/', $worker);
        self::assertStringContainsString('offline.html', $worker);
        foreach (['index.php', 'install.php', 'public.php', 'server_status', 'server_update', 'phpInfo'] as $privatePath) {
            self::assertStringContainsString($privatePath, $worker);
        }
        self::assertStringContainsString('CLEAR_PRIVATE_CACHES', $worker);
        self::assertStringNotContainsString('cache.put(request', $worker);
        self::assertStringNotContainsString("'index.php',", $worker);
    }

    public function testRegistrationRequiresSecureContextAndNeverRequestsPermissionOnLoad(): void
    {
        $javascript = $this->read('src/templates/default/static/js/pwa.js');
        $body = $this->read('src/templates/default/main/body.tpl.html');

        self::assertStringContainsString("location.protocol === 'https:'", $javascript);
        self::assertStringContainsString("location.hostname === 'localhost'", $javascript);
        self::assertStringContainsString('beforeinstallprompt', $javascript);
        self::assertStringContainsString('[data-pwa-install]', $javascript);
        self::assertStringNotContainsString('Notification.requestPermission()', $javascript);
        self::assertStringContainsString('manifest.webmanifest', $body);
        self::assertStringContainsString('pwa.js?v={{ version|url_encode }}', $body);
    }

    public function testApacheServesManifestWithCorrectMimeAndNeverLongCachesTheWorker(): void
    {
        $htaccess = $this->read('.htaccess');

        self::assertStringContainsString('AddType application/manifest+json .webmanifest', $htaccess);
        self::assertStringContainsString('<Files "service-worker.js">', $htaccess);
        self::assertStringContainsString('Cache-Control "no-cache, no-store, must-revalidate"', $htaccess);
    }

    private function read(string $path): string
    {
        $contents = @file_get_contents($this->root . '/' . $path);
        self::assertIsString($contents, 'Missing file: ' . $path);

        return $contents;
    }
}
