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
        $styles = $this->read('static/css/hs-monitor.css');
        $macros = $this->read('main/macros.tpl.html');

        self::assertStringContainsString('[data-card-search]', $javascript);
        self::assertStringContainsString('[data-password-toggle]', $javascript);
        self::assertStringContainsString('formnovalidate', $this->read('module/config/config.tpl.html'));
        self::assertStringContainsString('[data-password-toggle] svg', $styles);
        self::assertStringContainsString('html[data-bs-theme="light"] .theme-icon-dark', $styles);
        self::assertStringContainsString('.auth-layout', $styles);
        self::assertStringContainsString('.user-card', $styles);
        self::assertStringContainsString('.timeline-item', $styles);
        self::assertStringContainsString('.dropzone-field', $styles);
        self::assertStringContainsString('.server-media-frame', $styles);
        self::assertStringContainsString('.config-layout', $styles);
        self::assertStringContainsString('.install-stepper', $styles);
        self::assertStringNotContainsString('Â', $macros);
    }

    public function testServerAndConfigurationViewsUseCardsAndAccessibleTabs(): void
    {
        $servers = $this->read('module/server/server/list.tpl.html');
        $status = $this->read('module/server/status/index.tpl.html') . $this->read('module/server/status/cards.tpl.html');
        $config = $this->read('module/config/config.tpl.html');

        self::assertStringContainsString('<article class="card server-admin-card', $servers);
        self::assertStringContainsString('<article class="card status-card', $status);
        self::assertStringNotContainsString('<table', $servers . $status . $config);
        self::assertStringContainsString('role="tablist"', $config);
        self::assertStringContainsString('aria-orientation="vertical"', $config);
        self::assertStringContainsString('data-bs-toggle="pill"', $config);
        self::assertStringContainsString('class="php-info-grid', $config);
    }

    public function testEstadoRendersServersWithoutHistoricalStatistics(): void
    {
        $status = $this->read('module/server/status/index.tpl.html');
        self::assertStringContainsString('data-status-board', $status);
        self::assertStringNotContainsString('uptime-chart', $status);
        self::assertStringNotContainsString('latency-chart', $status);
        self::assertStringNotContainsString('dashboard-summary', $status);
    }

    public function testStatisticsOwnsKpisAndCharts(): void
    {
        $statistics = $this->read('module/server/statistics/index.tpl.html');
        self::assertStringContainsString('data-statistics-dashboard', $statistics);
        self::assertStringContainsString('uptime-chart', $statistics);
        self::assertStringContainsString('latency-chart', $statistics);
        self::assertStringContainsString('dashboard-summary', $statistics);
    }

    public function testServerFormAndInstallerExposeModernSections(): void
    {
        $server = $this->read('module/server/server/update.tpl.html');
        $installer = $this->read('module/install/main.tpl.html');
        $database = $this->read('module/install/config_new.tpl.html');

        self::assertStringContainsString('class="server-form', $server);
        self::assertGreaterThanOrEqual(6, substr_count($server, 'data-form-section'));
        self::assertStringNotContainsString('custom-select', $server);
        self::assertStringContainsString('data-install-stepper', $installer);
        self::assertStringContainsString('class="form-control"', $database);
    }

    public function testModernViewsDoNotUseBootstrapFourDataAttributes(): void
    {
        $contents = '';
        foreach ([
            'module/config/config.tpl.html',
            'module/server/history.tpl.html',
            'module/server/server/list.tpl.html',
            'module/server/server/view.tpl.html',
            'module/user/profile.tpl.html',
            'util/module/sidebar.tpl.html',
        ] as $template) {
            $contents .= $this->read($template);
        }

        self::assertStringNotContainsString('data-toggle=', $contents);
        self::assertStringNotContainsString('data-dismiss=', $contents);
    }

    public function testInstallerUsesModernCardsAndHsProjectLinks(): void
    {
        $contents = '';
        foreach ([
            'module/install/main.tpl.html',
            'module/install/index.tpl.html',
            'module/install/config_new.tpl.html',
            'module/install/config_new_user.tpl.html',
            'module/install/config_upgrade.tpl.html',
            'module/install/results.tpl.html',
            'module/install/success.tpl.html',
        ] as $template) {
            $contents .= $this->read($template);
        }

        self::assertStringNotContainsString('jumbotron', $contents);
        self::assertStringNotContainsString('img-responsive', $contents);
        self::assertStringNotContainsString('badge-', $contents);
        self::assertStringNotContainsString('phpservermonitor.org', $contents);
        self::assertStringContainsString('github.com/RicRey1988/phpservermon', $contents);
        self::assertStringContainsString('</form>', $this->read('module/install/config_new_user.tpl.html'));
    }

    public function testServerDetailsUseTimelineAndHistoryNeedsNoJquery(): void
    {
        $view = $this->read('module/server/server/view.tpl.html');
        $history = $this->read('module/server/history.tpl.html');
        $historyJavascript = $this->read('static/js/history.js');

        self::assertStringNotContainsString('<table', $view);
        self::assertStringContainsString('class="timeline-item', $view);
        self::assertStringNotContainsString('$.', $history);
        self::assertStringNotContainsString('$(', $historyJavascript);
        self::assertStringContainsString('querySelectorAll', $historyJavascript);
    }

    public function testServerViewsContainNoLegacyFixedWidthOrBootstrapFourUtilities(): void
    {
        foreach (['module/server/server/list.tpl.html', 'module/server/server/update.tpl.html', 'module/server/server/view.tpl.html', 'module/server/history.tpl.html'] as $template) {
            $html = $this->read($template);
            self::assertDoesNotMatchRegularExpression('/width\s*:\s*\d+vw/i', $html, $template);
            self::assertDoesNotMatchRegularExpression('/\b(?:pl|pr|ml|mr|float)-(?:0|auto|left|right)\b/', $html, $template);
        }
        $view = $this->read('module/server/server/view.tpl.html');
        self::assertStringContainsString('server-detail-grid', $view);
        self::assertStringContainsString('server-detail-tabs', $view);
        self::assertStringContainsString('role="tablist"', $view);
    }

    public function testEveryApplicationPageUsesModernHopeUiContracts(): void
    {
        $templates = [
            'module/server/log.tpl.html', 'module/server/server/update.tpl.html',
            'module/user/user/list.tpl.html', 'module/user/user/update.tpl.html', 'module/user/profile.tpl.html',
            'module/user/notification/index.tpl.html', 'module/user/login/login.tpl.html', 'module/user/login/forgot.tpl.html',
            'module/user/login/reset.tpl.html', 'module/user/login/register.tpl.html', 'module/config/config.tpl.html',
            'module/config/system.tpl.html', 'module/config/system-updated.tpl.html', 'module/install/index.tpl.html',
            'module/install/main.tpl.html', 'module/install/config_new.tpl.html', 'module/install/config_new_user.tpl.html',
            'module/install/config_upgrade.tpl.html', 'module/install/results.tpl.html', 'module/install/success.tpl.html',
            'module/error/401.tpl.html', 'util/module/modal.tpl.html', 'util/module/sidebar.tpl.html',
        ];
        foreach ($templates as $template) {
            $html = $this->read($template);
            self::assertStringNotContainsString('<table', $html, $template);
            self::assertStringNotContainsString('data-toggle=', $html, $template);
            self::assertStringNotContainsString('data-dismiss=', $html, $template);
            self::assertDoesNotMatchRegularExpression('/\b(?:pl|pr|ml|mr|float)-(?:0|auto|left|right)\b|\bform-row\b|width\s*:\s*\d+(?:vw|px)/i', $html, $template);
        }
        self::assertStringContainsString('user-editor-card', $this->read('module/user/user/update.tpl.html'));
        self::assertStringContainsString('error-state-card', $this->read('module/error/401.tpl.html'));
    }

    private function read(string $path): string
    {
        $contents = @file_get_contents($this->root . $path);
        self::assertIsString($contents, 'Missing template: ' . $path);

        return $contents;
    }
}
