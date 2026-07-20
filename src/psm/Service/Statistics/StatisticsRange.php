<?php

declare(strict_types=1);

namespace psm\Service\Statistics;

use DateTimeImmutable;

enum StatisticsRange: string
{
    case Day = '24h';
    case Week = '7d';
    case Month = '30d';
    case Quarter = '90d';

    public function startsAt(DateTimeImmutable $now): DateTimeImmutable
    {
        return match ($this) {
            self::Day => $now->modify('-24 hours'),
            self::Week => $now->modify('-7 days'),
            self::Month => $now->modify('-30 days'),
            self::Quarter => $now->modify('-90 days'),
        };
    }
}
