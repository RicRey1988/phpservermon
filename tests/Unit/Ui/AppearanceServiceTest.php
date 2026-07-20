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
        ]);

        self::assertSame([
            'scheme' => 'auto',
            'resolved_scheme' => 'light',
            'accent' => 'blue',
            'direction' => 'ltr',
            'sidebar' => 'default',
        ], $appearance->toArray());
    }

    public function testAcceptedValuesArePreserved(): void
    {
        $appearance = Appearance::fromPreferences([
            'ui_scheme' => 'dark',
            'ui_accent' => 'purple',
            'ui_direction' => 'rtl',
            'ui_sidebar' => 'dark',
        ]);

        self::assertSame('dark', $appearance->resolvedScheme());
        self::assertSame('purple', $appearance->accent);
        self::assertSame('rtl', $appearance->direction);
        self::assertSame('dark', $appearance->sidebar);
    }

    public function testServiceReadsAndNormalizesCurrentUserPreferences(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getUserPref')->willReturnMap([
            ['ui_scheme', 'auto', 'dark'],
            ['ui_accent', 'blue', 'pink'],
            ['ui_direction', 'ltr', 'ltr'],
            ['ui_sidebar', 'default', 'dark'],
        ]);

        $appearance = (new AppearanceService($user))->forCurrentUser();

        self::assertSame('dark', $appearance->scheme);
        self::assertSame('pink', $appearance->accent);
        self::assertSame('dark', $appearance->sidebar);
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
        ]);

        self::assertSame([
            'ui_scheme' => 'dark',
            'ui_accent' => 'blue',
            'ui_direction' => 'rtl',
            'ui_sidebar' => 'dark',
        ], $stored);
        self::assertSame('blue', $appearance->accent);
    }
}
