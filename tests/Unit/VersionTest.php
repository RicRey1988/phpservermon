<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class VersionTest extends TestCase
{
    public function testHsVersionIsExposed(): void
    {
        self::assertSame('4.3.1-hs', PSM_VERSION);
    }
}
