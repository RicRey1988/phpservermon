<?php

declare(strict_types=1);

namespace Tests\Unit\Ui;

use PHPUnit\Framework\TestCase;

final class BrandingContractTest extends TestCase
{
    public function testLogoAndAvatarAreConfigurableAndRenderedWithFallbacks(): void
    {
        $root = dirname(__DIR__, 3);
        $body = file_get_contents($root . '/src/templates/default/main/body.tpl.html');
        $navbar = file_get_contents($root . '/src/templates/default/main/app-navbar.tpl.html');
        $config = file_get_contents($root . '/src/templates/default/module/config/config.tpl.html');
        $profile = file_get_contents($root . '/src/templates/default/module/user/profile.tpl.html');

        self::assertStringContainsString('site_logo_url', $body);
        self::assertStringContainsString('site_logo_url', $navbar);
        self::assertStringContainsString('navbar_avatar_url', $navbar);
        self::assertStringContainsString('enctype="multipart/form-data"', $config);
        self::assertStringContainsString('name="site_logo"', $config);
        self::assertStringContainsString('enctype="multipart/form-data"', $profile);
        self::assertStringContainsString('name="profile_avatar"', $profile);
    }
}
