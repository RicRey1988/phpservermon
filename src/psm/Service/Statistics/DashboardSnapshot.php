<?php

declare(strict_types=1);

namespace psm\Service\Statistics;

final readonly class DashboardSnapshot
{
    /**
     * @param array<string, int|float|null> $summary
     * @param list<array{x: string, y: float}> $uptime
     * @param list<array{x: string, min: float, avg: float, max: float}> $latency
     */
    public function __construct(
        public string $range,
        public string $generatedAt,
        public array $summary,
        public array $uptime,
        public array $latency,
    ) {
    }

    /**
     * @return array{
     *     range: string,
     *     generated_at: string,
     *     summary: array<string, int|float|null>,
     *     uptime: list<array{x: string, y: float}>,
     *     latency: list<array{x: string, min: float, avg: float, max: float}>
     * }
     */
    public function toArray(): array
    {
        return [
            'range' => $this->range,
            'generated_at' => $this->generatedAt,
            'summary' => $this->summary,
            'uptime' => $this->uptime,
            'latency' => $this->latency,
        ];
    }
}
