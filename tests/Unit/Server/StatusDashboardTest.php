<?php

declare(strict_types=1);

namespace Tests\Unit\Server;

use PHPUnit\Framework\TestCase;

final class StatusDashboardTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    public function testControllerBuildsScopedRangeSnapshotAndImageUrls(): void
    {
        $controller = $this->read('src/psm/Module/Server/Controller/StatusController.php');

        self::assertStringContainsString('StatisticsRange::tryFrom(', $controller);
        self::assertStringContainsString('StatisticsRange::Day', $controller);
        self::assertStringContainsString('service.dashboard_statistics', $controller);
        self::assertStringContainsString('service.server_image.storage', $controller);
        self::assertStringContainsString('getUserId()', $controller);
        self::assertStringContainsString('getUserLevel() === PSM_USER_ADMIN', $controller);
        self::assertStringContainsString("['image_url']", $controller);
        self::assertStringContainsString("['status_label']", $controller);
        self::assertStringContainsString("['status_tone']", $controller);
        self::assertStringContainsString('JSON_HEX_TAG', $controller);
    }

    public function testDashboardUsesSummaryChartsAccessibleCardsAndNoTables(): void
    {
        $index = $this->read('src/templates/default/module/server/status/index.tpl.html');
        $cards = $this->read('src/templates/default/module/server/status/cards.tpl.html');
        $header = $this->read('src/templates/default/module/server/status/header.tpl.html');

        self::assertStringContainsString('dashboard-summary', $index);
        self::assertStringContainsString('uptime-chart', $index);
        self::assertStringContainsString('latency-chart', $index);
        self::assertStringContainsString('type="application/json"', $index);
        self::assertStringContainsString('dashboard_json', $index);
        self::assertStringContainsString('server-image-box', $cards);
        self::assertStringContainsString('src="{{ server.image_url }}"', $cards);
        self::assertStringContainsString('width="80" height="80"', $cards);
        self::assertStringContainsString('aria-label="{{ server.status_label }}"', $cards);
        self::assertStringContainsString('data-server-id="{{ server.server_id }}"', $cards);
        self::assertStringContainsString('name="range"', $header);
        foreach (['24h', '7d', '30d', '90d'] as $range) {
            self::assertStringContainsString('value="' . $range . '"', $header);
        }
        self::assertStringNotContainsString('<table', $index . $cards . $header);
        self::assertStringNotContainsString('DataTable', $index . $cards . $header);
    }

    public function testDashboardAssetsEnforceFixedImagesAndSafeJsonParsing(): void
    {
        $styles = $this->read('src/templates/default/static/css/app-shell.css');
        $javascript = $this->read('src/templates/default/static/js/dashboard.js');
        $body = $this->read('src/templates/default/main/body.tpl.html');

        self::assertMatchesRegularExpression('/\.server-image-box\s*\{[^}]*width:\s*6rem;[^}]*height:\s*6rem;/s', $styles);
        self::assertStringContainsString('object-fit: contain', $styles);
        self::assertStringContainsString("getElementById('dashboard-data')", $javascript);
        self::assertStringContainsString('JSON.parse(', $javascript);
        self::assertStringContainsString('new ApexCharts', $javascript);
        self::assertStringContainsString('dashboard.js?v={{ version|url_encode }}', $body);
    }

    private function read(string $path): string
    {
        $contents = @file_get_contents($this->root . '/' . $path);
        self::assertIsString($contents, 'Missing file: ' . $path);

        return $contents;
    }
}
