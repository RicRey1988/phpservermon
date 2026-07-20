<?php

declare(strict_types=1);

namespace Tests\Unit\Server;

use PHPUnit\Framework\TestCase;
use psm\Service\Incident\IncidentTransition;
use psm\Service\Incident\IncidentTransitionType;
use psm\Util\Server\UpdateManager;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class UpdateManagerTest extends TestCase
{
    public function testOneServerFailureDoesNotStopTheRemainingServers(): void
    {
        if (!defined('PSM_DB_PREFIX')) {
            define('PSM_DB_PREFIX', 'psm_');
        }

        $database = new class {
            public function query(string $sql): array
            {
                return [
                    ['server_id' => 1, 'status' => 'on'],
                    ['server_id' => 2, 'status' => 'off'],
                ];
            }
        };
        $container = new ContainerBuilder();
        $container->set('db', $database);
        $notifier = new class {
            public bool $combine = true;
            public int $calls = 0;
            public int $combinedCalls = 0;
            public function notify(int $serverId, bool $old, bool $new): void { $this->calls++; }
            public function notifyCombined(): void { $this->combinedCalls++; }
        };
        $archive = new class {
            public int $calls = 0;
            public function archive(int $serverId): void { $this->calls++; }
            public function cleanup(int $serverId): void {}
        };
        $manager = new UpdateManager(
            $container,
            static fn () => new class {
                public function update(int $serverId): bool
                {
                    if ($serverId === 1) {
                        throw new \RuntimeException('simulated');
                    }
                    return true;
                }
            },
            static fn () => $notifier,
            static fn () => $archive
        );

        $summary = $manager->run(true);

        self::assertSame(1, $summary->processed());
        self::assertSame(1, $summary->failed());
        self::assertSame(1, $notifier->calls);
        self::assertSame(1, $notifier->combinedCalls);
        self::assertSame(1, $archive->calls);
    }

    public function testConfirmedTransitionsUsePersistentDispatcherAndFlushRetries(): void
    {
        if (!defined('PSM_DB_PREFIX')) {
            define('PSM_DB_PREFIX', 'psm_');
        }

        $database = new class {
            public function query(string $sql): array
            {
                return [['server_id' => 5, 'status' => 'on']];
            }
        };
        $incidentManager = new class {
            public int $calls = 0;
            public function record(int $serverId, bool $old, bool $new, ?string $error): IncidentTransition
            {
                $this->calls++;
                return new IncidentTransition(12, $serverId, IncidentTransitionType::Down, new \DateTimeImmutable());
            }
        };
        $dispatcher = new class {
            public int $enqueued = 0;
            public int $flushes = 0;
            public function enqueue(IncidentTransition $transition): void { $this->enqueued++; }
            public function flush(): void { $this->flushes++; }
        };
        $container = new ContainerBuilder();
        $container->set('db', $database);
        $container->set('service.incident.manager', $incidentManager);
        $container->set('service.notification.incident_dispatcher', $dispatcher);
        $notifier = new class {
            public bool $combine = false;
            public int $calls = 0;
            public function notify(int $serverId, bool $old, bool $new): void { $this->calls++; }
        };
        $updater = new class {
            public string $error = 'Connection refused';
            public function update(int $serverId): bool { return false; }
        };
        $archive = new class {
            public function archive(int $serverId): void {}
            public function cleanup(int $serverId): void {}
        };
        $manager = new UpdateManager(
            $container,
            static fn () => $updater,
            static fn () => $notifier,
            static fn () => $archive,
        );

        $summary = $manager->run(true);

        self::assertTrue($summary->isSuccess());
        self::assertSame(1, $incidentManager->calls);
        self::assertSame(1, $dispatcher->enqueued);
        self::assertSame(1, $dispatcher->flushes);
        self::assertSame(0, $notifier->calls);
    }
}
