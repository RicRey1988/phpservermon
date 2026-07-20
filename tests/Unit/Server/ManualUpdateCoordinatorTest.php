<?php

declare(strict_types=1);

namespace Tests\Unit\Server;

use PHPUnit\Framework\TestCase;
use psm\Util\Server\ManualUpdateCoordinator;
use psm\Util\Server\UpdateLockInterface;

final class ManualUpdateCoordinatorTest extends TestCase
{
    public function testRunsUpdateImmediatelyWhenLockIsFree(): void
    {
        $lock = new SequenceLock([true]);
        $runs = 0;
        $coordinator = new ManualUpdateCoordinator($lock, static function () use (&$runs): void {
            $runs++;
        });

        self::assertSame(ManualUpdateCoordinator::UPDATED, $coordinator->run());
        self::assertSame(1, $runs);
        self::assertSame(1, $lock->releases);
    }

    public function testWaitsForCronAndUsesItsFreshResultInsteadOfRacingIt(): void
    {
        $lock = new SequenceLock([false, false, true]);
        $runs = 0;
        $waits = 0;
        $coordinator = new ManualUpdateCoordinator(
            $lock,
            static function () use (&$runs): void {
                $runs++;
            },
            3,
            static function () use (&$waits): void {
                $waits++;
            }
        );

        self::assertSame(ManualUpdateCoordinator::JOINED, $coordinator->run());
        self::assertSame(0, $runs);
        self::assertSame(2, $waits);
        self::assertSame(1, $lock->releases);
    }

    public function testReportsBusyWhenRunningUpdateDoesNotFinishInTime(): void
    {
        $lock = new SequenceLock([false, false, false]);
        $coordinator = new ManualUpdateCoordinator($lock, static function (): void {}, 2, static function (): void {});

        self::assertSame(ManualUpdateCoordinator::BUSY, $coordinator->run());
        self::assertSame(0, $lock->releases);
    }
}

final class SequenceLock implements UpdateLockInterface
{
    /** @var list<bool> */
    private array $results;
    public int $releases = 0;

    /** @param list<bool> $results */
    public function __construct(array $results)
    {
        $this->results = $results;
    }

    public function acquire(): bool
    {
        return array_shift($this->results) ?? false;
    }

    public function release(): void
    {
        $this->releases++;
    }
}
