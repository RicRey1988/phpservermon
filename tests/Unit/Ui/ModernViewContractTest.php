<?php

declare(strict_types=1);

namespace Tests\Unit\Ui;

use PHPUnit\Framework\TestCase;

final class ModernViewContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3) . '/src/templates/default/';
    }

    public function testReusableComponentLibraryExists(): void
    {
        $components = $this->read('main/components.tpl.html');
        foreach (['card', 'text', 'password', 'select', 'switch', 'textarea', 'dropzone', 'status_badge', 'empty_state'] as $macro) {
            self::assertStringContainsString('{% macro ' . $macro . '(', $components);
        }
    }

    public function testAuthenticationFormsUseVisibleAccessibleControls(): void
    {
        $login = $this->read('module/user/login/login.tpl.html');
        $forgot = $this->read('module/user/login/forgot.tpl.html');
        $reset = $this->read('module/user/login/reset.tpl.html');

        self::assertStringContainsString('class="auth-card card"', $login);
        self::assertStringContainsString('autocomplete="username"', $login);
        self::assertStringContainsString('autocomplete="current-password"', $login);
        self::assertStringContainsString('aria-live="polite"', $login);
        self::assertStringNotContainsString('class="sr-only"', $login . $forgot . $reset);
        self::assertStringContainsString('autocomplete="new-password"', $reset);
    }

    public function testUserCollectionAndLogsUseCardsRatherThanTables(): void
    {
        $users = $this->read('module/user/user/list.tpl.html');
        $logs = $this->read('module/server/log.tpl.html');

        self::assertStringContainsString('<article class="card user-card', $users);
        self::assertStringContainsString('data-card-search', $users);
        self::assertStringContainsString('data-search-text=', $users);
        self::assertStringNotContainsString('<table', $users);
        self::assertStringContainsString('class="timeline-item', $logs);
        self::assertStringNotContainsString('<table', $logs);
        self::assertStringContainsString('data-bs-toggle="tab"', $logs);
    }

    public function testApplicationShellProvidesModernFormInteractions(): void
    {
        $javascript = $this->read('static/js/app-shell.js');
        $styles = $this->read('static/css/app-shell.css');
        $macros = $this->read('main/macros.tpl.html');

        self::assertStringContainsString('[data-card-search]', $javascript);
        self::assertStringContainsString('[data-password-toggle]', $javascript);
        self::assertStringContainsString('.auth-layout', $styles);
        self::assertStringContainsString('.user-card', $styles);
        self::assertStringContainsString('.timeline-item', $styles);
        self::assertStringContainsString('.dropzone-field', $styles);
        self::assertStringNotContainsString('Â', $macros);
    }

    private function read(string $path): string
    {
        $contents = @file_get_contents($this->root . $path);
        self::assertIsString($contents, 'Missing template: ' . $path);

        return $contents;
    }
}
