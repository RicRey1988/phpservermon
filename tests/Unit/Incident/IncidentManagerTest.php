<?php

declare(strict_types=1);

namespace Tests\Unit\Incident;

use Closure;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use psm\Service\Database;
use psm\Service\Incident\IncidentManager;
use psm\Service\Incident\IncidentRepository;
use psm\Service\Incident\IncidentTransitionType;

final class IncidentManagerTest extends TestCase
{
    public function testUnchangedAndWarningStatesDoNotCreateIncidents(): void
    {
        $repository = new MemoryIncidentRepository();
        $manager = new IncidentManager($repository);

        self::assertNull($manager->record(4, true, true, null));
        self::assertNull($manager->record(4, false, false, 'still down'));
        self::assertSame([], $repository->opened);
        self::assertSame([], $repository->resolved);
    }

    public function testConfirmedDownOpensOnceAndRecoveryResolvesTheSameIncident(): void
    {
        $repository = new MemoryIncidentRepository();
        $manager = new IncidentManager($repository);

        $down = $manager->record(7, true, false, 'Connection refused');
        self::assertNotNull($down);
        self::assertSame(1, $down->incidentId);
        self::assertSame(7, $down->serverId);
        self::assertSame(IncidentTransitionType::Down, $down->type);

        self::assertNull($manager->record(7, true, false, 'duplicate callback'));
        self::assertCount(1, $repository->opened);

        $recovery = $manager->record(7, false, true, null);
        self::assertNotNull($recovery);
        self::assertSame(1, $recovery->incidentId);
        self::assertSame(IncidentTransitionType::Recovery, $recovery->type);
        self::assertSame([1], $repository->resolved);
    }

    public function testRecoveryWithoutAnOpenIncidentCreatesNoEvent(): void
    {
        $manager = new IncidentManager(new MemoryIncidentRepository());

        self::assertNull($manager->record(99, false, true, null));
    }

    public function testSeparateDownRecoveryCyclesCreateSeparateIncidents(): void
    {
        $repository = new MemoryIncidentRepository();
        $manager = new IncidentManager($repository);

        $first = $manager->record(2, true, false, null);
        $manager->record(2, false, true, null);
        $second = $manager->record(2, true, false, null);

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame(1, $first->incidentId);
        self::assertSame(2, $second->incidentId);
    }

    public function testOpeningErrorIsSanitizedAndTruncated(): void
    {
        $repository = new MemoryIncidentRepository();
        $manager = new IncidentManager($repository);
        $secret = 'https://admin:secret@example.test token=abcdef password=hunter2 ' . str_repeat('x', 400);

        $manager->record(3, true, false, $secret);

        $stored = $repository->opened[0]['error'];
        self::assertLessThanOrEqual(255, strlen($stored));
        self::assertStringNotContainsString('secret', $stored);
        self::assertStringNotContainsString('abcdef', $stored);
        self::assertStringNotContainsString('hunter2', $stored);
    }
}

final class MemoryIncidentRepository extends IncidentRepository
{
    /** @var list<array{server_id: int, error: ?string}> */
    public array $opened = [];
    /** @var list<int> */
    public array $resolved = [];
    /** @var array<int, int> */
    private array $openByServer = [];
    private int $nextId = 1;

    public function __construct()
    {
        parent::__construct(new Database());
    }

    public function transaction(Closure $operation): mixed
    {
        return $operation();
    }

    public function findOpenForUpdate(int $serverId): ?array
    {
        return isset($this->openByServer[$serverId])
            ? ['incident_id' => $this->openByServer[$serverId], 'server_id' => $serverId]
            : null;
    }

    public function open(int $serverId, DateTimeImmutable $openedAt, ?string $error): int
    {
        $id = $this->nextId++;
        $this->openByServer[$serverId] = $id;
        $this->opened[] = ['server_id' => $serverId, 'error' => $error];

        return $id;
    }

    public function resolve(int $incidentId, int $serverId, DateTimeImmutable $resolvedAt, string $message): void
    {
        unset($this->openByServer[$serverId]);
        $this->resolved[] = $incidentId;
    }
}
