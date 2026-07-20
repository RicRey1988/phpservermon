<?php

declare(strict_types=1);

namespace Tests\Unit\Cron;

use PHPUnit\Framework\TestCase;
use psm\Util\Cron\CronLock;

final class CronLockTest extends TestCase
{
    public function testLockIsExclusiveAndCanBeReacquiredAfterRelease(): void
    {
        $path = sys_get_temp_dir() . '/psm-cron-' . bin2hex(random_bytes(6)) . '.lock';
        $first = new CronLock($path);
        $second = new CronLock($path);

        try {
            self::assertTrue($first->acquire());
            self::assertFalse($second->acquire());
            $first->release();
            self::assertTrue($second->acquire());
            $second->release();
            $second->release();
        } finally {
            $first->release();
            $second->release();
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
