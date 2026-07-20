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
        self::assertStringContainsString('<main class="main-content min-vh-100 d-flex flex-column" id="main-content"', $body);
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
        self::assertStringContainsString('sidebar sidebar-default sidebar-white sidebar-base navs-rounded-all', $body);
        self::assertStringContainsString('<main class="main-content', $body);
        self::assertStringContainsString('iq-navbar-header', $body);
        self::assertStringContainsString('content-inner mt-n5 py-0', $body);
        self::assertStringContainsString('input-group search-input', $navbar);
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
        $javascript = file_get_contents($root . 'static/js/app-shell.js');

        self::assertIsString($menu);
        self::assertIsString($body);
        self::assertIsString($javascript);
        self::assertStringContainsString('<i class="icon">', $menu);
        self::assertStringNotContainsString('<span class="icon">', $menu);
        self::assertStringContainsString('<i class="icon">', $body);
        self::assertStringContainsString("classList.add('sidebar-mini')", $javascript);
        self::assertStringContainsString("event.key === 'Escape'", $javascript);
        self::assertStringNotContainsString('function initializeSidebar()', $javascript);
        self::assertStringNotContainsString('sidebar-collapsed', $javascript);
        self::assertStringNotContainsString('sidebar-open', $body . $javascript);
    }

    public function testLightNavbarAndAuthenticationUseNativeThemeSafeSurfaces(): void
    {
        $root = dirname(__DIR__, 3);
        $body = file_get_contents($root . '/src/templates/default/main/body.tpl.html');
        $navbar = file_get_contents($root . '/src/templates/default/main/app-navbar.tpl.html');
        $login = file_get_contents($root . '/src/templates/default/module/user/login/login.tpl.html');
        $profile = file_get_contents($root . '/src/templates/default/module/user/profile.tpl.html');

        self::assertIsString($body);
        self::assertIsString($navbar);
        self::assertIsString($login);
        self::assertIsString($profile);
        self::assertFileDoesNotExist($root . '/src/templates/default/static/css/hs-monitor.css');
        foreach (['hope-ui.min.css', 'dark.min.css', 'customizer.min.css'] as $asset) {
            self::assertStringContainsString($asset, $body);
        }
        self::assertStringNotContainsString('hs-monitor.css', $body);
        self::assertStringContainsString('d-inline-flex align-items-center justify-content-center', $navbar);
        self::assertStringContainsString('data-theme-icon="dark"', $navbar);
        self::assertStringContainsString('data-theme-icon="light"', $navbar);
        self::assertStringContainsString('class="card border-0 shadow-none bg-transparent"', $login);
        self::assertStringContainsString('class="col-lg-6 d-none d-lg-flex align-items-center justify-content-center bg-primary', $login);
        self::assertStringContainsString('class="card mb-4"', $profile);
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
