<?php

declare(strict_types=1);

namespace Tests\Unit\Cron;

use PHPUnit\Framework\TestCase;
use psm\Util\Cron\WebCronAuthorizer;

final class WebCronAuthorizerTest extends TestCase
{
    public function testDisabledWebCronAlwaysFails(): void
    {
        self::assertFalse((new WebCronAuthorizer(false, 'secret', ['127.0.0.1']))->isAllowed('127.0.0.1', 'secret'));
    }

    public function testEmptyOrWrongKeysFailOutsideAllowlist(): void
    {
        $authorizer = new WebCronAuthorizer(true, 'secret', []);
        self::assertFalse($authorizer->isAllowed('203.0.113.10', null));
        self::assertFalse($authorizer->isAllowed('203.0.113.10', ''));
        self::assertFalse($authorizer->isAllowed('203.0.113.10', 'wrong'));
    }

    public function testCorrectKeyOrExplicitRemoteAddressIsAllowed(): void
    {
        $authorizer = new WebCronAuthorizer(true, 'secret', ['192.0.2.50']);
        self::assertTrue($authorizer->isAllowed('203.0.113.10', 'secret'));
        self::assertTrue($authorizer->isAllowed('192.0.2.50', null));
        self::assertFalse($authorizer->isAllowed('198.51.100.20', null));
    }
}
