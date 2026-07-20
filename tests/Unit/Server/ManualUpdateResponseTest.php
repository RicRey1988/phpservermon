<?php

declare(strict_types=1);

namespace Tests\Unit\Server;

use PHPUnit\Framework\TestCase;

final class ManualUpdateResponseTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    public function testRunActionIsPostOnlyCsrfScopedAndReturnsDetailedJson(): void
    {
        $controller = $this->read('src/psm/Module/Server/Controller/UpdateController.php');

        self::assertStringContainsString("setCSRFKey('status')", $controller);
        self::assertStringContainsString("array('index', 'run')", $controller);
        self::assertStringContainsString('REQUEST_METHOD', $controller);
        self::assertStringContainsString('JsonResponse', $controller);
        self::assertStringContainsString('Response::HTTP_METHOD_NOT_ALLOWED', $controller);
        self::assertStringContainsString('Response::HTTP_CONFLICT', $controller);
        self::assertStringContainsString('207', $controller);
        foreach (['processed', 'failed', 'busy', 'checked_at', 'cards', 'summary', 'errors'] as $key) {
            self::assertStringContainsString("'" . $key . "'", $controller);
        }
        self::assertStringContainsString('users_servers', $controller);
        self::assertStringContainsString('service.dashboard_statistics', $controller);
    }

    public function testSnapshotIsReadOnlyAndNeverInvokesTheUpdateManager(): void
    {
        $controller = $this->read('src/psm/Module/Server/Controller/StatusController.php');
        $snapshot = substr($controller, (int) strpos($controller, 'executeSnapshot'));

        self::assertStringContainsString("'snapshot'", $controller);
        self::assertStringContainsString('executeSnapshot', $controller);
        self::assertStringContainsString('HTTP_METHOD_NOT_ALLOWED', $snapshot);
        self::assertStringContainsString("'html'", $snapshot);
        self::assertStringNotContainsString('updatemanager', $snapshot);
    }

    public function testDashboardButtonPostsThenRefreshesRenderedCardsImmediately(): void
    {
        $header = $this->read('src/templates/default/module/server/status/header.tpl.html');
        $javascript = $this->read('src/templates/default/static/js/status.js');

        self::assertStringContainsString('data-run-update', $header);
        self::assertStringContainsString('data-update-url', $header);
        self::assertStringContainsString("method: 'POST'", $javascript);
        self::assertStringContainsString('X-Requested-With', $javascript);
        self::assertStringNotContainsString('applyCards', $javascript);
        self::assertStringContainsString('refreshStatus()', $javascript);
        self::assertStringContainsString('replaceStatus', $javascript);
        self::assertStringContainsString('new DOMParser()', $javascript);
        self::assertStringContainsString("querySelector('[data-status-board]')", $javascript);
        self::assertStringContainsString('current.replaceWith(fresh)', $javascript);
        self::assertStringContainsString("cache: 'no-store'", $javascript);
    }

    private function read(string $path): string
    {
        $contents = @file_get_contents($this->root . '/' . $path);
        self::assertIsString($contents, 'Missing file: ' . $path);

        return $contents;
    }
}
