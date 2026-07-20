<?php

declare(strict_types=1);

namespace psm\Service\SystemUpdate;

final readonly class ReleaseInfo
{
    public ReleaseAsset $asset;

    public function __construct(
        public string $version,
        public string $name,
        public string $notes,
        public string $htmlUrl,
        public ReleaseAsset $archiveAsset,
        public ReleaseAsset $manifestAsset,
        public ReleaseAsset $signatureAsset,
    ) {
        $this->asset = $archiveAsset;
    }

    public function isNewerThan(string $currentVersion): bool
    {
        return version_compare($this->version, ltrim($currentVersion, 'vV'), '>');
    }
}
