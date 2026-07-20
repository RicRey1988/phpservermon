<?php

declare(strict_types=1);

namespace Tests\Unit\User;

use PHPUnit\Framework\TestCase;

final class PersistentSessionContractTest extends TestCase
{
    public function testAuthenticationUsesDurableSessionAndPersistentToken(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/src/psm/Service/User.php');
        $config = file_get_contents(dirname(__DIR__, 3) . '/src/includes/psmconfig.inc.php');
        $login = file_get_contents(dirname(__DIR__, 3) . '/src/templates/default/module/user/login/login.tpl.html');

        self::assertStringContainsString('NativeSessionStorage', $source);
        self::assertStringContainsString("'cookie_lifetime' => PSM_LOGIN_COOKIE_RUNTIME", $source);
        self::assertStringContainsString("'gc_maxlifetime' => PSM_LOGIN_COOKIE_RUNTIME", $source);
        self::assertMatchesRegularExpression('/setUserLoggedIn\(\$user->user_id, true\);\s*\$this->newRememberMeCookie\(\);/s', $source);
        self::assertStringContainsString("define('PSM_LOGIN_COOKIE_RUNTIME', 31536000);", $config);
        self::assertStringContainsString('La sesión permanecerá iniciada', $login);
        self::assertStringNotContainsString('name="user_rememberme"', $login);
    }
}
