<?php

declare(strict_types=1);

namespace Tests\Unit\Statistics;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use psm\Service\Database;
use psm\Service\Statistics\DashboardStatistics;
use psm\Service\Statistics\StatisticsRange;

final class DashboardStatisticsTest extends TestCase
{
    #[DataProvider('rangeProvider')]
    public function testRangeStartsAtTheExpectedBoundary(string $value, string $expected): void
    {
        $now = new DateTimeImmutable('2026-07-19 12:00:00');

        self::assertSame($expected, StatisticsRange::from($value)->startsAt($now)->format('Y-m-d H:i:s'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function rangeProvider(): iterable
    {
        yield 'day' => ['24h', '2026-07-18 12:00:00'];
        yield 'week' => ['7d', '2026-07-12 12:00:00'];
        yield 'month' => ['30d', '2026-06-19 12:00:00'];
        yield 'quarter' => ['90d', '2026-04-20 12:00:00'];
    }

    public function testDaySnapshotAggregatesSummaryHourlyUptimeAndLatency(): void
    {
        $database = new StatisticsDatabase(
            servers: [
                ['status' => 'on', 'active' => 'yes', 'warning_threshold_counter' => 0, 'ssl_cert_expired_time' => null, 'ssl_cert_expiry_days' => 0],
                ['status' => 'on', 'active' => 'yes', 'warning_threshold_counter' => 1, 'ssl_cert_expired_time' => null, 'ssl_cert_expiry_days' => 0],
                ['status' => 'off', 'active' => 'yes', 'warning_threshold_counter' => 0, 'ssl_cert_expired_time' => null, 'ssl_cert_expiry_days' => 0],
                ['status' => 'on', 'active' => 'no', 'warning_threshold_counter' => 0, 'ssl_cert_expired_time' => null, 'ssl_cert_expiry_days' => 0],
            ],
            live: [
                ['date' => '2026-07-19 10:05:00', 'status' => 1, 'latency' => 0.1],
                ['date' => '2026-07-19 10:35:00', 'status' => 0, 'latency' => null],
                ['date' => '2026-07-19 11:05:00', 'status' => 1, 'latency' => 0.3],
            ],
        );

        $snapshot = (new DashboardStatistics($database))->snapshot(
            StatisticsRange::Day,
            new DateTimeImmutable('2026-07-19 12:00:00'),
            8,
            true,
        )->toArray();

        self::assertSame([
            'online' => 1,
            'warning' => 1,
            'offline' => 1,
            'paused' => 1,
            'active_incidents' => 2,
            'checks' => 3,
            'failures' => 1,
            'uptime_percentage' => 66.67,
            'latency_min' => 100.0,
            'latency_avg' => 200.0,
            'latency_max' => 300.0,
        ], $snapshot['summary']);
        self::assertSame([
            ['x' => '2026-07-19 10:00:00', 'y' => 50.0],
            ['x' => '2026-07-19 11:00:00', 'y' => 100.0],
        ], $snapshot['uptime']);
        self::assertSame([
            ['x' => '2026-07-19 10:00:00', 'min' => 100.0, 'avg' => 100.0, 'max' => 100.0],
            ['x' => '2026-07-19 11:00:00', 'min' => 300.0, 'avg' => 300.0, 'max' => 300.0],
        ], $snapshot['latency']);
        self::assertSame('24h', $snapshot['range']);
        self::assertCount(2, $database->queries);
        self::assertStringNotContainsString('servers_history', $database->queries[1]['sql']);
    }

    public function testLongRangeCombinesLiveAndArchivedDailyBucketsWithoutDoubleCounting(): void
    {
        $database = new StatisticsDatabase(
            servers: [],
            live: [
                ['date' => '2026-07-18 09:00:00', 'status' => 1, 'latency' => 0.2],
                ['date' => '2026-07-19 09:00:00', 'status' => 0, 'latency' => null],
            ],
            history: [
                [
                    'date' => '2026-07-17',
                    'latency_min' => 0.1,
                    'latency_avg' => 0.25,
                    'latency_max' => 0.4,
                    'checks_total' => 4,
                    'checks_failed' => 1,
                ],
            ],
        );

        $snapshot = (new DashboardStatistics($database))->snapshot(
            StatisticsRange::Week,
            new DateTimeImmutable('2026-07-19 12:00:00'),
            12,
            false,
        )->toArray();

        self::assertSame(6, $snapshot['summary']['checks']);
        self::assertSame(2, $snapshot['summary']['failures']);
        self::assertSame(66.67, $snapshot['summary']['uptime_percentage']);
        self::assertSame([
            ['x' => '2026-07-17', 'y' => 75.0],
            ['x' => '2026-07-18', 'y' => 100.0],
            ['x' => '2026-07-19', 'y' => 0.0],
        ], $snapshot['uptime']);

        self::assertCount(3, $database->queries);
        self::assertStringContainsString('NOT EXISTS', $database->queries[1]['sql']);
        self::assertStringContainsString('users_servers', $database->queries[0]['sql']);
        self::assertStringContainsString('users_servers', $database->queries[1]['sql']);
        self::assertStringContainsString('users_servers', $database->queries[2]['sql']);
        self::assertSame(12, $database->queries[0]['parameters']['user_id']);
        self::assertSame('2026-07-12 12:00:00', $database->queries[1]['parameters']['starts_at']);
        self::assertSame('2026-07-12', $database->queries[2]['parameters']['starts_on']);
    }

    public function testEmptyChecksProduceNullAvailabilityAndLatency(): void
    {
        $snapshot = (new DashboardStatistics(new StatisticsDatabase()))->snapshot(
            StatisticsRange::Month,
            new DateTimeImmutable('2026-07-19 12:00:00'),
            1,
            true,
        )->toArray();

        self::assertNull($snapshot['summary']['uptime_percentage']);
        self::assertNull($snapshot['summary']['latency_min']);
        self::assertNull($snapshot['summary']['latency_avg']);
        self::assertNull($snapshot['summary']['latency_max']);
        self::assertSame([], $snapshot['uptime']);
        self::assertSame([], $snapshot['latency']);
    }
}

final class StatisticsDatabase extends Database
{
    /** @var list<array{sql: string, parameters: array<string, mixed>}> */
    public array $queries = [];

    /**
     * @param list<array<string, mixed>> $servers
     * @param list<array<string, mixed>> $live
     * @param list<array<string, mixed>> $history
     */
    public function __construct(
        private readonly array $servers = [],
        private readonly array $live = [],
        private readonly array $history = [],
    ) {
    }

    public function execute($query, $parameters, $fetch = true)
    {
        $this->queries[] = ['sql' => $query, 'parameters' => $parameters];

        if (str_contains($query, 'servers_uptime')) {
            return $this->live;
        }
        if (str_contains($query, 'servers_history')) {
            return $this->history;
        }

        return $this->servers;
    }
}
