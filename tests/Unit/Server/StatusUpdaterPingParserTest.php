<?php

declare(strict_types=1);

namespace Tests\Unit\Server;

use PHPUnit\Framework\TestCase;
use psm\Util\Server\Updater\StatusUpdater;

final class StatusUpdaterPingParserTest extends TestCase
{
    public function testEmptyPingOutputFailsWithoutUndefinedOffsetsOrNullMatches(): void
    {
        $updater = (new \ReflectionClass(StatusUpdater::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(StatusUpdater::class, 'parseNonWindowsPingOutput');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            self::assertFalse($method->invoke($updater, []));
        } finally {
            restore_error_handler();
        }

        self::assertSame('-', $updater->header);
        self::assertSame('Ping command returned no output.', $updater->error);
    }

    public function testSuccessfulPingOutputExtractsAverageLatency(): void
    {
        $updater = (new \ReflectionClass(StatusUpdater::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(StatusUpdater::class, 'parseNonWindowsPingOutput');
        $output = [
            'PING example.test (127.0.0.1) 56(84) bytes of data.',
            '64 bytes from 127.0.0.1: icmp_seq=1 ttl=64 time=7.109 ms',
            '1 packets transmitted, 1 received, 0% packet loss, time 0ms',
            'rtt min/avg/max/mdev = 7.109/8.250/9.391/1.141 ms',
        ];

        self::assertTrue($method->invoke($updater, $output));
        self::assertEqualsWithDelta(0.00825, $updater->rtime, 0.000001);
    }
}
