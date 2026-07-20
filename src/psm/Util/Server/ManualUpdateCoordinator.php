<?php

declare(strict_types=1);

namespace psm\Util\Server;

final class ManualUpdateCoordinator
{
    public const UPDATED = 'updated';
    public const JOINED = 'joined';
    public const BUSY = 'busy';

    private \Closure $runner;

    /** @var \Closure(): void */
    private \Closure $sleeper;

    /**
     * @param callable(): void $runner
     * @param callable(): void|null $sleeper
     */
    public function __construct(
        private readonly UpdateLockInterface $lock,
        callable $runner,
        private readonly int $maxWaits = 120,
        ?callable $sleeper = null
    ) {
        $this->runner = \Closure::fromCallable($runner);
        $this->sleeper = $sleeper === null
            ? static function (): void {
                usleep(250000);
            }
            : \Closure::fromCallable($sleeper);
    }

    public function run(): string
    {
        if ($this->lock->acquire()) {
            try {
                ($this->runner)();
            } finally {
                $this->lock->release();
            }

            return self::UPDATED;
        }

        for ($attempt = 0; $attempt < $this->maxWaits; $attempt++) {
            ($this->sleeper)();
            if ($this->lock->acquire()) {
                // The cron run that owned the lock already refreshed every status.
                $this->lock->release();

                return self::JOINED;
            }
        }

        return self::BUSY;
    }
}
