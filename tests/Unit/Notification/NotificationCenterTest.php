<?php

declare(strict_types=1);

namespace Tests\Unit\Notification;

use PHPUnit\Framework\TestCase;

final class NotificationCenterTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    public function testModuleAndControllerScopeEveryOperationToCurrentUser(): void
    {
        $module = $this->read('src/psm/Module/User/UserModule.php');
        $controller = $this->read('src/psm/Module/User/Controller/NotificationController.php');
        $repository = $this->read('src/psm/Service/Notification/UserNotificationRepository.php');

        self::assertStringContainsString("'notification'", $module);
        self::assertStringContainsString("setCSRFKey('notifications')", $controller);
        self::assertStringContainsString("'markRead'", $controller);
        self::assertStringContainsString("'markAllRead'", $controller);
        self::assertStringContainsString('REQUEST_METHOD', $controller);
        self::assertStringContainsString('getUserId()', $controller);
        self::assertStringContainsString('user_id = :user_id', $repository);
        self::assertStringContainsString('notification_id = :notification_id', $repository);
        self::assertStringContainsString('users_servers', $repository);
    }

    public function testNavbarCapsUnreadCountAndRendersLatestFive(): void
    {
        $navbar = $this->read('src/templates/default/main/app-navbar.tpl.html');
        $controller = $this->read('src/psm/Module/AbstractController.php');

        self::assertStringContainsString('notification-navbar', $navbar);
        self::assertStringContainsString('99+', $navbar);
        self::assertStringContainsString('notification_navbar.latest', $navbar);
        self::assertStringContainsString("latestForUser(", $controller);
        self::assertStringContainsString(', 5)', $controller);
    }

    public function testNotificationFeedUsesEscapedCardsAndNativeReadActions(): void
    {
        $template = $this->read('src/templates/default/module/user/notification/index.tpl.html');
        $javascript = $this->read('src/templates/default/static/js/notifications.js');

        self::assertStringContainsString('<article class="card notification-card', $template);
        self::assertStringContainsString('{{ notification.body }}', $template);
        self::assertStringNotContainsString('notification.body|raw', $template);
        self::assertStringNotContainsString('<table', $template);
        self::assertStringContainsString('data-notification-read', $template);
        self::assertStringContainsString('data-notifications-read-all', $template);
        self::assertStringContainsString("method: 'POST'", $javascript);
        self::assertStringContainsString('X-Requested-With', $javascript);
    }

    private function read(string $path): string
    {
        $contents = @file_get_contents($this->root . '/' . $path);
        self::assertIsString($contents, 'Missing file: ' . $path);

        return $contents;
    }
}
