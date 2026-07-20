<?php

declare(strict_types=1);

namespace psm\Service\SystemUpdate;

use psm\Service\Update\ManifestVerifier;
use psm\Service\System\JobRunRepository;
use RuntimeException;
use Throwable;

final readonly class SystemUpdater
{
    public function __construct(
        private GitHubReleaseClient $releases,
        private ReleasePackageVerifier $verifier,
        private ManifestVerifier $manifestVerifier,
        private ReleaseInstaller $installer,
        private MaintenanceMode $maintenance,
        private string $root,
        private ?JobRunRepository $jobRuns = null,
    ) {
    }

    public function update(string $currentVersion, ?string $expectedVersion = null): UpdateResult
    {
        $release = $this->releases->latest();
        if (!$release->isNewerThan($currentVersion)) {
            throw new RuntimeException('The system is already on the latest HS release.');
        }
        if ($expectedVersion !== null && !hash_equals($release->version, $expectedVersion)) {
            throw new RuntimeException('The confirmed version no longer matches the latest signed HS release.');
        }
        $updateDirectory = rtrim($this->root, '/\\') . DIRECTORY_SEPARATOR . '.psm-update';
        if (!is_dir($updateDirectory) && !mkdir($updateDirectory, 0700, true)) {
            throw new RuntimeException('The protected update workspace could not be created.');
        }
        $temporary = $updateDirectory . DIRECTORY_SEPARATOR . 'run-' . bin2hex(random_bytes(8));
        $stage = $temporary . DIRECTORY_SEPARATOR . 'stage';
        if (!mkdir($temporary, 0700, true)) {
            throw new RuntimeException('The update workspace could not be created.');
        }
        $jobRunId = $this->jobRuns?->start('application_update') ?? 0;
        try {
            $archive = $temporary . DIRECTORY_SEPARATOR . $release->archiveAsset->name;
            $manifestPath = $temporary . DIRECTORY_SEPARATOR . $release->manifestAsset->name;
            $signaturePath = $temporary . DIRECTORY_SEPARATOR . $release->signatureAsset->name;
            $this->releases->download($release->manifestAsset, $manifestPath);
            $this->releases->download($release->signatureAsset, $signaturePath);
            $this->releases->download($release->archiveAsset, $archive);
            $manifestBytes = file_get_contents($manifestPath);
            $signatureBytes = file_get_contents($signaturePath);
            if (!is_string($manifestBytes) || !is_string($signatureBytes)) {
                throw new RuntimeException('The signed release metadata could not be read.');
            }
            $manifest = $this->manifestVerifier->verify(
                $manifestBytes,
                $signatureBytes,
                $archive,
                $currentVersion,
            );
            if (!hash_equals($release->version, $manifest->version)) {
                throw new RuntimeException('GitHub release and signed manifest versions do not match.');
            }
            $package = $this->verifier->extractAndVerify($archive, $stage, $release->version);
            $this->maintenance->enable();
            $changed = $this->installer->install($package);
            $this->jobRuns?->finish($jobRunId, $changed, 0, 'Signed HS update installed.');
        } catch (Throwable $exception) {
            $this->jobRuns?->finish($jobRunId, 0, 1, 'Signed HS update failed and file rollback was requested.');
            throw $exception;
        } finally {
            $this->maintenance->disable();
            $this->removeDirectory($temporary);
        }

        return new UpdateResult($release->version, $changed, true);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) { return; }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }
}
