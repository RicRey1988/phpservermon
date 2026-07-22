<?php

declare(strict_types=1);

namespace Tests\Unit\Ui;

use PHPUnit\Framework\TestCase;

final class AppShellTest extends TestCase
{
    public function testShellUsesHopeUiBootstrapFiveAndAccessibleLandmarks(): void
    {
        $root = dirname(__DIR__, 3);
        $body = file_get_contents($root . '/src/templates/default/main/body.tpl.html');
        self::assertIsString($body);

        self::assertStringContainsString('data-bs-theme="{{ appearance.resolved_scheme }}"', $body);
        self::assertStringContainsString('href="#main-content"', $body);
        self::assertStringContainsString('<aside class="sidebar', $body);
        self::assertStringContainsString('<main class="main-content" id="main-content"', $body);
        self::assertStringContainsString('static/hope/css/hope-ui.min.css?v={{ version|url_encode }}', $body);
        self::assertStringContainsString('static/hope/js/bootstrap.bundle.min.js?v={{ version|url_encode }}', $body);
        self::assertStringNotContainsString('bootstrap-select', $body);
        self::assertStringNotContainsString('jquery-3.5.1', $body);
    }

    public function testShellUsesAuthenticHopeUiHierarchyAndControls(): void
    {
        $root = dirname(__DIR__, 3) . '/src/templates/default/main/';
        $body = file_get_contents($root . 'body.tpl.html');
        $navbar = file_get_contents($root . 'app-navbar.tpl.html');
        $customizer = file_get_contents($root . 'appearance-customizer.tpl.html');

        self::assertIsString($body);
        self::assertIsString($navbar);
        self::assertIsString($customizer);
        self::assertStringContainsString('sidebar sidebar-default sidebar-base navs-rounded-all', $body);
        self::assertStringContainsString('<main class="main-content"', $body);
        self::assertStringContainsString('iq-navbar-header', $body);
        self::assertStringContainsString('content-inner mt-n5 py-0', $body);
        self::assertStringContainsString('data-theme-quick-toggle', $navbar);
        self::assertStringContainsString('data-bs-target="#hope-ui-settings"', $navbar);
        foreach (['auto', 'dark', 'light'] as $scheme) {
            self::assertStringContainsString('data-value="' . $scheme . '"', $customizer);
        }
    }

    public function testSidebarUsesHopeUiNativeMiniMarkupAndSingleController(): void
    {
        $root = dirname(__DIR__, 3) . '/src/templates/default/';
        $menu = file_get_contents($root . 'main/menu.tpl.html');
        $body = file_get_contents($root . 'main/body.tpl.html');
        $navbar = file_get_contents($root . 'main/app-navbar.tpl.html');
        $javascript = file_get_contents($root . 'static/js/app-shell.js');
        $styles = file_get_contents($root . 'static/css/hs-monitor.css');

        self::assertIsString($menu);
        self::assertIsString($body);
        self::assertIsString($navbar);
        self::assertIsString($javascript);
        self::assertIsString($styles);
        self::assertStringContainsString('<i class="icon">', $menu);
        self::assertStringNotContainsString('<span class="icon">', $menu);
        self::assertStringContainsString('<i class="icon">', $body);
        self::assertStringContainsString('data-sidebar-backdrop', $body);
        self::assertStringContainsString('data-sidebar-primary-toggle', $body);
        self::assertStringContainsString('data-sidebar-mobile-toggle', $navbar);
        self::assertStringContainsString('function initializeSidebar()', $javascript);
        self::assertStringContainsString("event.key === 'Escape'", $javascript);
        self::assertStringContainsString('event.stopImmediatePropagation()', $javascript);
        self::assertStringContainsString("sidebar.classList.toggle('sidebar-mini')", $javascript);
        self::assertStringContainsString("'[data-sidebar-backdrop]'", $javascript);
        self::assertStringContainsString("'#sidebar-menu a'", $javascript);
        self::assertStringNotContainsString('sidebar-user', $menu);
        self::assertStringNotContainsString('body.sidebar-open', $styles);
        self::assertStringNotContainsString('sidebar-collapsed', $javascript);
    }

    public function testNavbarUsesHopeAlignedThemeAndSearchComponents(): void
    {
        $root = dirname(__DIR__, 3) . '/src/templates/default/';
        $navbar = file_get_contents($root . 'main/app-navbar.tpl.html');
        $styles = file_get_contents($root . 'static/css/hs-monitor.css');

        self::assertIsString($navbar);
        self::assertIsString($styles);
        self::assertStringContainsString('hope-search', $navbar);
        self::assertGreaterThanOrEqual(2, substr_count($navbar, 'topbar-icon-button'));
        self::assertStringContainsString('.topbar-icon-button', $styles);
        self::assertStringContainsString('width: 2.75rem', $styles);
        self::assertStringContainsString('height: 2.75rem', $styles);
        self::assertStringContainsString('.hope-search', $styles);
        self::assertStringContainsString('html[data-bs-theme="light"] .hope-search', $styles);
        self::assertStringContainsString('html[data-bs-theme="dark"] .hope-search', $styles);
    }

    public function testLightNavbarAndAuthenticationHaveExplicitThemeSafeSurfaces(): void
    {
        $styles = file_get_contents(dirname(__DIR__, 3) . '/src/templates/default/static/css/hs-monitor.css');
        self::assertIsString($styles);

        self::assertStringContainsString('html[data-bs-theme="light"] .iq-navbar .nav-link', $styles);
        self::assertStringContainsString('.auth-card.card', $styles);
        self::assertStringContainsString('html[data-bs-theme="dark"] .auth-card.card', $styles);
        self::assertStringContainsString('legend {', $styles);
        self::assertStringContainsString('border-bottom:', $styles);
        self::assertStringContainsString('.push-device-card .card-header > .d-flex', $styles);
    }

    public function testFooterIdentifiesHostingSupremoFork(): void
    {
        $body = file_get_contents(dirname(__DIR__, 3) . '/src/templates/default/main/body.tpl.html');
        self::assertIsString($body);

        self::assertStringContainsString('Mejorado por Hosting Supremo', $body);
        self::assertStringContainsString('https://github.com/RicRey1988/phpservermon', $body);
        self::assertStringNotContainsString('https://github.com/phpservermon/phpservermon/', $body);
    }

    public function testProfileContainsAllAppearanceControls(): void
    {
        $profile = file_get_contents(dirname(__DIR__, 3) . '/src/templates/default/module/user/profile.tpl.html');
        self::assertIsString($profile);

        foreach (['ui_scheme', 'ui_accent', 'ui_direction', 'ui_sidebar', 'ui_sidebar_active', 'ui_navbar', 'appearance_submit'] as $field) {
            self::assertStringContainsString('name="' . $field . '"', $profile);
        }
        self::assertStringContainsString('name="ui_sidebar_types[]"', $profile);
    }

    public function testModalMarkupUsesBootstrapFiveAttributes(): void
    {
        $modal = file_get_contents(dirname(__DIR__, 3) . '/src/templates/default/util/module/modal.tpl.html');
        self::assertIsString($modal);

        self::assertStringContainsString('data-bs-dismiss="modal"', $modal);
        self::assertStringNotContainsString('data-dismiss="modal"', $modal);
    }

    public function testStatusAutoRefreshDoesNotDependOnJquery(): void
    {
        $status = file_get_contents(
            dirname(__DIR__, 3) . '/src/templates/default/module/server/status/index.tpl.html'
        );
        $javascript = file_get_contents(
            dirname(__DIR__, 3) . '/src/templates/default/static/js/status.js'
        );
        self::assertIsString($status);
        self::assertIsString($javascript);

        self::assertStringContainsString('data-auto-refresh-seconds', $status);
        self::assertStringContainsString('return fetch(url.toString()', $javascript);
        self::assertStringContainsString('window.setInterval', $javascript);
        self::assertStringNotContainsString('$.ajax', $status . $javascript);
    }
}
