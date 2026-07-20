<?php

declare(strict_types=1);

namespace Tests\Unit\Server;

use PHPUnit\Framework\TestCase;
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
}
