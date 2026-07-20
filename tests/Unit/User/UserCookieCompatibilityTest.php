<?php

declare(strict_types=1);

namespace Tests\Unit\User;

use PHPUnit\Framework\TestCase;
use psm\Service\User;

final class UserCookieCompatibilityTest extends TestCase
{
    public function testNullCookieDomainIsOmittedFromPhp85Options(): void
    {
        $user = (new \ReflectionClass(User::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(User::class, 'rememberMeCookieOptions');
        $options = $method->invoke($user, 1234567890);

        self::assertSame('/', $options['path']);
        self::assertTrue($options['secure']);
        self::assertTrue($options['httponly']);
        self::assertArrayNotHasKey('domain', $options);
    }
}
