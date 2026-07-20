<?php

declare(strict_types=1);

namespace psm\Service\Incident;

enum IncidentTransitionType: string
{
    case Down = 'down';
    case Recovery = 'recovery';
}
