<?php

declare(strict_types=1);

namespace psm\Service\Incident;

use DateTimeImmutable;

final readonly class IncidentManager
{
    public function __construct(private IncidentRepository $repository)
    {
    }

    public function record(int $serverId, bool $old, bool $new, ?string $error): ?IncidentTransition
    {
        if ($serverId < 1 || $old === $new) {
            return null;
        }

        $occurredAt = new DateTimeImmutable();

        return $this->repository->transaction(function () use ($serverId, $old, $new, $error, $occurredAt) {
            $open = $this->repository->findOpenForUpdate($serverId);
            if ($old && !$new) {
                if ($open !== null) {
                    return null;
                }
                $incidentId = $this->repository->open($serverId, $occurredAt, $this->sanitize($error));

                return new IncidentTransition(
                    $incidentId,
                    $serverId,
                    IncidentTransitionType::Down,
                    $occurredAt,
                );
            }

            if (!$old && $new && $open !== null) {
                $incidentId = (int) $open['incident_id'];
                $this->repository->resolve($incidentId, $serverId, $occurredAt, 'Server recovered.');

                return new IncidentTransition(
                    $incidentId,
                    $serverId,
                    IncidentTransitionType::Recovery,
                    $occurredAt,
                );
            }

            return null;
        });
    }

    private function sanitize(?string $error): ?string
    {
        if ($error === null || trim($error) === '') {
            return null;
        }

        $clean = strip_tags($error);
        $clean = (string) preg_replace('~://[^/@\s]+@~u', '://[redacted]@', $clean);
        $clean = (string) preg_replace(
            '/\b(token|password|passwd|authorization)\s*[:=]\s*[^\s&]+/iu',
            '$1=[redacted]',
            $clean,
        );
        $clean = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $clean);
        $clean = trim((string) preg_replace('/\s+/u', ' ', $clean));

        return mb_substr($clean, 0, 255);
    }
}
