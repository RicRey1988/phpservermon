<?php

declare(strict_types=1);

namespace Tests\Unit\Ui;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class NativeHopeUiContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    public function testOnlyBundledHopeUiStylesheetsAreLoaded(): void
    {
        $body = $this->read('src/templates/default/main/body.tpl.html');
        $worker = $this->read('service-worker.js');

        self::assertFileDoesNotExist($this->root . '/src/templates/default/static/css/hs-monitor.css');
        foreach (['hope-ui.min.css', 'dark.min.css', 'customizer.min.css'] as $asset) {
            self::assertStringContainsString($asset, $body);
        }
        self::assertStringNotContainsString('hs-monitor.css', $body . $worker);
        self::assertStringNotContainsString('<style', $this->applicationTemplateContents());
        self::assertStringNotContainsString(' style=', $this->applicationTemplateContents());
    }

    public function testShellUsesNativeSidebarSearchAndThemeContracts(): void
    {
        $body = $this->read('src/templates/default/main/body.tpl.html');
        $navbar = $this->read('src/templates/default/main/app-navbar.tpl.html');
        $menu = $this->read('src/templates/default/main/menu.tpl.html');
        $javascript = $this->read('src/templates/default/static/js/app-shell.js');

        self::assertStringContainsString('sidebar sidebar-default sidebar-white sidebar-base', $body);
        self::assertStringContainsString('class="sidebar-toggle" data-toggle="sidebar"', $body);
        self::assertStringContainsString('input-group search-input', $navbar);
        self::assertStringContainsString('data-theme-icon="dark"', $navbar);
        self::assertStringContainsString('data-theme-icon="light"', $navbar);
        self::assertStringNotContainsString('sidebar-user', $menu);
        self::assertStringNotContainsString('url_profile', $menu);
        self::assertStringNotContainsString('url_logout', $menu);
        self::assertStringContainsString("classList.add('sidebar-mini')", $javascript);
        self::assertStringContainsString("event.key === 'Escape'", $javascript);
        self::assertStringContainsString("classList.toggle('d-none'", $javascript);
        self::assertStringNotContainsString('sidebar-open', $body . $navbar . $javascript);
    }

    public function testApplicationTemplatesUseNoClassesOwnedByRemovedStylesheet(): void
    {
        $contents = $this->applicationTemplateContents();
        $removed = [
            'hope-icon', 'hope-icon-lg', 'skip-link', 'place-items-center',
            'brand-mark', 'brand-logo', 'brand-logo--auth', 'auth-visual-logo',
            'navbar-avatar', 'identity-preview', 'identity-preview--avatar',
            'app-content', 'app-footer', 'module-actions', 'theme-icon-dark',
            'theme-icon-light', 'btn-setting', 'hope-settings', 'settings-section',
            'setting-choice', 'preview-choice', 'accent-choice', 'accent-swatch',
            'accent-grid', 'grid-cols-6', 'appearance-options', 'appearance-card',
            'push-device-card', 'auth-page-content', 'auth-messages', 'auth-layout',
            'auth-form-pane', 'auth-card', 'auth-visual', 'auth-visual-brand',
            'auth-layout-single', 'auth-brand-panel', 'brand-mark-lg', 'user-card',
            'server-admin-card', 'status-card', 'user-contact', 'timeline-list',
            'timeline-item', 'timeline-marker', 'dropzone-field', 'dropzone-preview',
            'dropzone-copy', 'min-w-0', 'server-media-frame', 'server-media',
            'server-media--compact', 'status-indicator', 'status-banner',
            'status-banner-icon', 'status-pulse', 'status-grid', 'server-image-box',
            'status-card-title', 'dashboard-stat-card', 'stat-icon', 'dashboard-chart',
            'notification-count', 'notification-dropdown', 'notification-dropdown-list',
            'notification-unread', 'server-facts', 'channel-chips', 'channel-chip',
            'server-detail-grid', 'server-detail-image', 'server-detail-tabs',
            'history-panel', 'history-graph', 'chart-container',
            'history-range-controls', 'output-panel-content', 'config-layout',
            'config-tabs', 'config-content', 'install-shell', 'install-stepper',
            'form-row', 'input-group-prepend', 'custom-select', 'search_input',
            'searchbar',
        ];

        foreach ($removed as $class) {
            self::assertDoesNotMatchRegularExpression(
                "/class=[\"'][^\"']*\\b" . preg_quote($class, '/') . "\\b[^\"']*[\"']/",
                $contents,
                'Custom presentation class remains: ' . $class,
            );
        }
    }

    public function testJavaScriptUsesDataHooksRatherThanRemovedPresentationClasses(): void
    {
        $javascript = implode("\n", [
            $this->read('src/templates/default/static/js/app-shell.js'),
            $this->read('src/templates/default/static/js/status.js'),
            $this->read('src/templates/default/static/js/dashboard.js'),
            $this->read('src/templates/default/static/js/history.js'),
            $this->read('src/templates/default/static/js/notifications.js'),
        ]);

        foreach (['sidebar-open', 'status-banner', 'status-badge--', 'history-panel', 'notification-unread', 'show-modal'] as $selector) {
            self::assertStringNotContainsString($selector, $javascript);
        }
        foreach (['data-status-board', 'data-server-card', 'data-notification-item', 'data-modal-trigger'] as $hook) {
            self::assertStringContainsString($hook, $this->applicationTemplateContents() . $javascript);
        }
    }

    private function applicationTemplateContents(): string
    {
        $contents = '';
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root . '/src/templates/default'),
        );
        foreach ($iterator as $file) {
            assert($file instanceof SplFileInfo);
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.tpl.html')) {
                continue;
            }
            $contents .= "\n" . (string) file_get_contents($file->getPathname());
        }

        return $contents;
    }

    private function read(string $path): string
    {
        $contents = @file_get_contents($this->root . '/' . $path);
        self::assertIsString($contents, 'Missing file: ' . $path);

        return $contents;
    }
}
