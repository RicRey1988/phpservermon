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

    public function testControllersSeparateLiveStatusFromScopedStatistics(): void
    {
        $statusController = $this->read('src/psm/Module/Server/Controller/StatusController.php');
        $statisticsController = $this->read('src/psm/Module/Server/Controller/StatisticsController.php');

        self::assertStringNotContainsString('service.dashboard_statistics', $statusController);
        self::assertStringContainsString('service.server_image.storage', $statusController);
        self::assertStringContainsString("['image_url']", $statusController);
        self::assertStringContainsString("['status_label']", $statusController);
        self::assertStringContainsString("['status_tone']", $statusController);
        self::assertStringContainsString('StatisticsRange::tryFrom(', $statisticsController);
        self::assertStringContainsString('StatisticsRange::Day', $statisticsController);
        self::assertStringContainsString('service.dashboard_statistics', $statisticsController);
        self::assertStringContainsString('getUserId()', $statisticsController);
        self::assertStringContainsString('getUserLevel() === PSM_USER_ADMIN', $statisticsController);
        self::assertStringContainsString('JSON_HEX_TAG', $statisticsController);
    }

    public function testDashboardUsesSummaryChartsAccessibleCardsAndNoTables(): void
    {
        $index = $this->read('src/templates/default/module/server/status/index.tpl.html');
        $statistics = $this->read('src/templates/default/module/server/statistics/index.tpl.html');
        $cards = $this->read('src/templates/default/module/server/status/cards.tpl.html');
        $header = $this->read('src/templates/default/module/server/statistics/header.tpl.html');

        self::assertStringContainsString('data-status-board', $index);
        self::assertStringNotContainsString('dashboard-summary', $index);
        self::assertStringContainsString('dashboard-summary', $statistics);
        self::assertStringContainsString('uptime-chart', $statistics);
        self::assertStringContainsString('latency-chart', $statistics);
        self::assertStringContainsString('type="application/json"', $statistics);
        self::assertStringContainsString('dashboard_json', $statistics);
        self::assertStringContainsString('server-image-box', $cards);
        self::assertStringContainsString('src="{{ server.image_url }}"', $cards);
        self::assertStringContainsString('width="96" height="96"', $cards);
        self::assertStringContainsString('status-card-title', $cards);
        self::assertStringNotContainsString('text-truncate', $cards);
        self::assertStringContainsString('aria-label="{{ server.status_label }}"', $cards);
        self::assertStringContainsString('data-server-id="{{ server.server_id }}"', $cards);
        self::assertStringContainsString('name="range"', $header);
        foreach (['24h', '7d', '30d', '90d'] as $range) {
            self::assertStringContainsString('value="' . $range . '"', $header);
        }
        self::assertStringNotContainsString('<table', $index . $statistics . $cards . $header);
        self::assertStringNotContainsString('DataTable', $index . $statistics . $cards . $header);
    }

    public function testDashboardAssetsEnforceFixedImagesAndSafeJsonParsing(): void
    {
        $styles = $this->read('src/templates/default/static/css/hs-monitor.css');
        $javascript = $this->read('src/templates/default/static/js/dashboard.js');
        $body = $this->read('src/templates/default/main/body.tpl.html');

        self::assertMatchesRegularExpression('/\.server-image-box\s*\{[^}]*width:\s*6rem;[^}]*height:\s*6rem;/s', $styles);
        self::assertMatchesRegularExpression('/object-fit:\s*contain/', $styles);
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
