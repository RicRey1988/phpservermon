<?php

declare(strict_types=1);

namespace psm\Service\Statistics;

use DateTimeImmutable;
use psm\Service\Database;

final readonly class DashboardStatistics
{
    public function __construct(private Database $database)
    {
    }

    public function snapshot(
        StatisticsRange $range,
        DateTimeImmutable $now,
        int $userId,
        bool $isAdmin,
    ): DashboardSnapshot {
        $servers = $this->currentServers($userId, $isAdmin);
        $live = $this->liveSamples($range, $now, $userId, $isAdmin);
        $history = $range === StatisticsRange::Day
            ? []
            : $this->historySamples($range, $now, $userId, $isAdmin);

        $states = $this->summarizeStates($servers);
        $buckets = [];
        foreach ($live as $sample) {
            $bucket = $this->bucketKey((string) $sample['date'], $range);
            $this->addToBucket(
                $buckets,
                $bucket,
                1,
                (int) $sample['status'] === 0 ? 1 : 0,
                $sample['latency'] === null ? null : (float) $sample['latency'],
                $sample['latency'] === null ? 0 : 1,
            );
        }
        foreach ($history as $sample) {
            $this->addToBucket(
                $buckets,
                (string) $sample['date'],
                (int) $sample['checks_total'],
                (int) $sample['checks_failed'],
                (float) $sample['latency_avg'],
                (int) $sample['checks_total'],
                (float) $sample['latency_min'],
                (float) $sample['latency_max'],
            );
        }
        ksort($buckets);

        $checks = 0;
        $failures = 0;
        $latencyCount = 0;
        $latencySum = 0.0;
        $latencyMin = null;
        $latencyMax = null;
        $uptimeSeries = [];
        $latencySeries = [];

        foreach ($buckets as $bucket => $data) {
            $checks += $data['checks'];
            $failures += $data['failures'];
            $latencyCount += $data['latency_count'];
            $latencySum += $data['latency_sum'];
            $latencyMin = $this->lower($latencyMin, $data['latency_min']);
            $latencyMax = $this->higher($latencyMax, $data['latency_max']);

            $uptimeSeries[] = [
                'x' => $bucket,
                'y' => $this->percentage($data['checks'], $data['failures']) ?? 0.0,
            ];
            if ($data['latency_count'] > 0 && $data['latency_min'] !== null && $data['latency_max'] !== null) {
                $latencySeries[] = [
                    'x' => $bucket,
                    'min' => $this->milliseconds($data['latency_min']),
                    'avg' => $this->milliseconds($data['latency_sum'] / $data['latency_count']),
                    'max' => $this->milliseconds($data['latency_max']),
                ];
            }
        }

        $summary = $states + [
            'checks' => $checks,
            'failures' => $failures,
            'uptime_percentage' => $this->percentage($checks, $failures),
            'latency_min' => $latencyMin === null ? null : $this->milliseconds($latencyMin),
            'latency_avg' => $latencyCount === 0 ? null : $this->milliseconds($latencySum / $latencyCount),
            'latency_max' => $latencyMax === null ? null : $this->milliseconds($latencyMax),
        ];

        return new DashboardSnapshot(
            $range->value,
            $now->format(DATE_ATOM),
            $summary,
            $uptimeSeries,
            $latencySeries,
        );
    }

    /** @return list<array<string, mixed>> */
    private function currentServers(int $userId, bool $isAdmin): array
    {
        [$join, $where, $parameters] = $this->scope($userId, $isAdmin, 's');
        $sql = 'SELECT s.status, s.active, s.warning_threshold_counter, '
            . 's.ssl_cert_expired_time, s.ssl_cert_expiry_days '
            . 'FROM `' . $this->table('servers') . '` AS s ' . $join . $where;

        /** @var list<array<string, mixed>> */
        return $this->database->execute($sql, $parameters);
    }

    /** @return list<array<string, mixed>> */
    private function liveSamples(
        StatisticsRange $range,
        DateTimeImmutable $now,
        int $userId,
        bool $isAdmin,
    ): array {
        [$join, $scopeWhere, $scopeParameters] = $this->scope($userId, $isAdmin, 's');
        $archiveExclusion = $range === StatisticsRange::Day ? '' : ' AND NOT EXISTS ('
            . 'SELECT 1 FROM `' . $this->table('servers_history') . '` AS hx '
            . 'WHERE hx.server_id = u.server_id AND hx.date = DATE(u.date))';
        $sql = 'SELECT u.date, u.status, u.latency '
            . 'FROM `' . $this->table('servers_uptime') . '` AS u '
            . 'INNER JOIN `' . $this->table('servers') . '` AS s ON s.server_id = u.server_id '
            . $join
            . 'WHERE u.date >= :starts_at' . $archiveExclusion
            . ($scopeWhere === '' ? '' : ' AND ' . substr($scopeWhere, 7))
            . ' ORDER BY u.date ASC';
        $parameters = ['starts_at' => $range->startsAt($now)->format('Y-m-d H:i:s')] + $scopeParameters;

        /** @var list<array<string, mixed>> */
        return $this->database->execute($sql, $parameters);
    }

    /** @return list<array<string, mixed>> */
    private function historySamples(
        StatisticsRange $range,
        DateTimeImmutable $now,
        int $userId,
        bool $isAdmin,
    ): array {
        [$join, $scopeWhere, $scopeParameters] = $this->scope($userId, $isAdmin, 's');
        $sql = 'SELECT h.date, h.latency_min, h.latency_avg, h.latency_max, h.checks_total, h.checks_failed '
            . 'FROM `' . $this->table('servers_history') . '` AS h '
            . 'INNER JOIN `' . $this->table('servers') . '` AS s ON s.server_id = h.server_id '
            . $join
            . 'WHERE h.date >= :starts_on'
            . ($scopeWhere === '' ? '' : ' AND ' . substr($scopeWhere, 7))
            . ' ORDER BY h.date ASC';
        $parameters = ['starts_on' => $range->startsAt($now)->format('Y-m-d')] + $scopeParameters;

        /** @var list<array<string, mixed>> */
        return $this->database->execute($sql, $parameters);
    }

    /**
     * @return array{string, string, array<string, int>}
     */
    private function scope(int $userId, bool $isAdmin, string $serverAlias): array
    {
        if ($isAdmin) {
            return ['', '', []];
        }

        return [
            'INNER JOIN `' . $this->table('users_servers') . '` AS us ON us.server_id = '
                . $serverAlias . '.server_id ',
            ' WHERE us.user_id = :user_id',
            ['user_id' => $userId],
        ];
    }

    /**
     * @param list<array<string, mixed>> $servers
     * @return array{online: int, warning: int, offline: int, paused: int, active_incidents: int}
     */
    private function summarizeStates(array $servers): array
    {
        $states = ['online' => 0, 'warning' => 0, 'offline' => 0, 'paused' => 0, 'active_incidents' => 0];
        foreach ($servers as $server) {
            if (($server['active'] ?? 'no') !== 'yes') {
                ++$states['paused'];
                continue;
            }

            $warning = ($server['status'] ?? 'off') === 'on' && (
                (int) ($server['warning_threshold_counter'] ?? 0) > 0
                || (($server['ssl_cert_expired_time'] ?? null) !== null
                    && (int) ($server['ssl_cert_expiry_days'] ?? 0) > 0)
            );
            if (($server['status'] ?? 'off') === 'off') {
                ++$states['offline'];
                ++$states['active_incidents'];
            } elseif ($warning) {
                ++$states['warning'];
                ++$states['active_incidents'];
            } else {
                ++$states['online'];
            }
        }

        return $states;
    }

    /**
     * @param array<string, array{checks: int, failures: int, latency_count: int, latency_sum: float, latency_min: ?float, latency_max: ?float}> $buckets
     */
    private function addToBucket(
        array &$buckets,
        string $key,
        int $checks,
        int $failures,
        ?float $latencyAverage,
        int $latencyCount,
        ?float $latencyMin = null,
        ?float $latencyMax = null,
    ): void {
        $buckets[$key] ??= [
            'checks' => 0,
            'failures' => 0,
            'latency_count' => 0,
            'latency_sum' => 0.0,
            'latency_min' => null,
            'latency_max' => null,
        ];
        $buckets[$key]['checks'] += $checks;
        $buckets[$key]['failures'] += $failures;
        if ($latencyAverage === null || $latencyCount < 1) {
            return;
        }

        $buckets[$key]['latency_count'] += $latencyCount;
        $buckets[$key]['latency_sum'] += $latencyAverage * $latencyCount;
        $buckets[$key]['latency_min'] = $this->lower($buckets[$key]['latency_min'], $latencyMin ?? $latencyAverage);
        $buckets[$key]['latency_max'] = $this->higher($buckets[$key]['latency_max'], $latencyMax ?? $latencyAverage);
    }

    private function bucketKey(string $date, StatisticsRange $range): string
    {
        $time = new DateTimeImmutable($date);

        return $range === StatisticsRange::Day ? $time->format('Y-m-d H:00:00') : $time->format('Y-m-d');
    }

    private function percentage(int $checks, int $failures): ?float
    {
        return $checks === 0 ? null : round((($checks - $failures) / $checks) * 100, 2);
    }

    private function milliseconds(float $seconds): float
    {
        return round($seconds * 1000, 3);
    }

    private function lower(?float $current, ?float $candidate): ?float
    {
        return $candidate === null ? $current : ($current === null ? $candidate : min($current, $candidate));
    }

    private function higher(?float $current, ?float $candidate): ?float
    {
        return $candidate === null ? $current : ($current === null ? $candidate : max($current, $candidate));
    }

    private function table(string $name): string
    {
        $prefix = defined('PSM_DB_PREFIX') ? (string) constant('PSM_DB_PREFIX') : 'psm_';

        return $prefix . $name;
    }
}
