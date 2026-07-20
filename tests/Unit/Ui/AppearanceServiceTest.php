<?php

declare(strict_types=1);

namespace Tests\Unit\Ui;

use PHPUnit\Framework\TestCase;
use psm\Service\Ui\Appearance;
use psm\Service\Ui\AppearanceService;
use psm\Service\User;

final class AppearanceServiceTest extends TestCase
{
    public function testInvalidStoredValuesFallBackToSafeDefaults(): void
    {
        $appearance = Appearance::fromPreferences([
            'ui_scheme' => 'javascript:alert(1)',
            'ui_accent' => '#fff;}',
            'ui_direction' => 'sideways',
            'ui_sidebar' => 'hidden',
            'ui_sidebar_types' => ['unknown'],
            'ui_sidebar_active' => 'square',
            'ui_navbar' => 'javascript:alert(1)',
        ]);

        self::assertSame([
            'scheme' => 'auto',
            'resolved_scheme' => 'light',
            'accent' => 'blue',
            'direction' => 'ltr',
            'sidebar' => 'default',
            'sidebar_types' => [],
            'sidebar_active' => 'rounded-one-side',
            'navbar' => 'default',
            'body_classes' => 'auto theme-color-blue',
            'sidebar_classes' => 'sidebar-white navs-rounded',
            'navbar_classes' => '',
        ], $appearance->toArray());
    }

    public function testAcceptedValuesArePreserved(): void
    {
        $appearance = Appearance::fromPreferences([
            'ui_scheme' => 'dark',
            'ui_accent' => 'purple',
            'ui_direction' => 'rtl',
            'ui_sidebar' => 'dark',
            'ui_sidebar_types' => ['mini', 'boxed'],
            'ui_sidebar_active' => 'pill-all',
            'ui_navbar' => 'glass',
        ]);

        self::assertSame('dark', $appearance->resolvedScheme());
        self::assertSame('purple', $appearance->accent);
        self::assertSame('rtl', $appearance->direction);
        self::assertSame('dark', $appearance->sidebar);
        self::assertSame(['mini', 'boxed'], $appearance->sidebarTypes);
        self::assertSame('pill-all', $appearance->sidebarActive);
        self::assertSame('glass', $appearance->navbar);
    }

    public function testServiceReadsAndNormalizesCurrentUserPreferences(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getUserPref')->willReturnMap([
            ['ui_scheme', 'auto', 'dark'],
            ['ui_accent', 'blue', 'pink'],
            ['ui_direction', 'ltr', 'ltr'],
            ['ui_sidebar', 'default', 'dark'],
            ['ui_sidebar_types', '', 'mini,hover'],
            ['ui_sidebar_active', 'rounded-one-side', 'rounded-all'],
            ['ui_navbar', 'default', 'sticky'],
        ]);

        $appearance = (new AppearanceService($user))->forCurrentUser();

        self::assertSame('dark', $appearance->scheme);
        self::assertSame('pink', $appearance->accent);
        self::assertSame('dark', $appearance->sidebar);
        self::assertSame(['mini', 'hover'], $appearance->sidebarTypes);
        self::assertSame('sticky', $appearance->navbar);
    }

    public function testServiceStoresOnlyNormalizedValues(): void
    {
        $stored = [];
        $user = $this->createStub(User::class);
        $user->method('setUserPref')->willReturnCallback(
            static function (string $key, string $value) use (&$stored): void {
                $stored[$key] = $value;
            }
        );

        $appearance = (new AppearanceService($user))->saveForCurrentUser([
            'ui_scheme' => 'dark',
            'ui_accent' => 'invalid',
            'ui_direction' => 'rtl',
            'ui_sidebar' => 'dark',
            'ui_sidebar_types' => ['mini', 'invalid'],
            'ui_sidebar_active' => 'pill-one-side',
            'ui_navbar' => 'transparent',
        ]);

        self::assertSame([
            'ui_scheme' => 'dark',
            'ui_accent' => 'blue',
            'ui_direction' => 'rtl',
            'ui_sidebar' => 'dark',
            'ui_sidebar_types' => 'mini',
            'ui_sidebar_active' => 'pill-one-side',
            'ui_navbar' => 'transparent',
        ], $stored);
        self::assertSame('blue', $appearance->accent);
    }
}
