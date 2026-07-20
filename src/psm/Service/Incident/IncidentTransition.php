<?php

declare(strict_types=1);

namespace psm\Service\Incident;

use DateTimeImmutable;

final readonly class IncidentTransition
{
    public function __construct(
        public int $incidentId,
        public int $serverId,
        public IncidentTransitionType $type,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
