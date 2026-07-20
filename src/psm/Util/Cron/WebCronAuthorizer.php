<?php

declare(strict_types=1);

namespace psm\Util\Cron;

final readonly class WebCronAuthorizer
{
    /** @param list<string> $allowlist */
    public function __construct(
        private bool $enabled,
        private string $configuredKey,
        private array $allowlist = []
    ) {
    }

    public function isAllowed(string $remoteIp, ?string $key): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $keyAllowed = $this->configuredKey !== ''
            && $key !== null
            && $key !== ''
            && hash_equals($this->configuredKey, $key);
        $ipAllowed = $remoteIp !== '' && in_array($remoteIp, $this->allowlist, true);

        return $keyAllowed || $ipAllowed;
    }
}
