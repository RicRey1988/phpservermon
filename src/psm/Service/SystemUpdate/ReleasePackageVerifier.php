<?php

declare(strict_types=1);

namespace psm\Service\SystemUpdate;

use RuntimeException;
use ZipArchive;

final class ReleasePackageVerifier
{
    public function extractAndVerify(string $archive, string $directory, string $version): VerifiedPackage
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required to install updates.');
        }
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('The release package is not a valid ZIP archive.');
        }
        if ($zip->numFiles <= 0 || $zip->numFiles > 10000) {
            $zip->close();
            throw new RuntimeException('The release package contains an invalid number of files.');
        }
        $totalSize = 0;
        $seen = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            if (!is_array($stat)) {
                $zip->close();
                throw new RuntimeException('The release archive index is invalid.');
            }
            $name = (string) $stat['name'];
            $normalized = rtrim(str_replace('\\', '/', $name), '/');
            $this->assertSafePath($normalized);
            $collisionKey = strtolower($normalized);
            if (isset($seen[$collisionKey])) {
                $zip->close();
                throw new RuntimeException('The release package contains duplicate normalized paths.');
            }
            $seen[$collisionKey] = true;
            $totalSize += (int) $stat['size'];
            if ($totalSize > 100 * 1024 * 1024) {
                $zip->close();
                throw new RuntimeException('The expanded release package is too large.');
            }
            $operations = 0;
            $attributes = 0;
            if ($zip->getExternalAttributesIndex($index, $operations, $attributes) && (($attributes >> 16) & 0170000) === 0120000) {
                $zip->close();
                throw new RuntimeException('Symbolic links are not allowed in release packages.');
            }
        }
        if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
            $zip->close();
            throw new RuntimeException('The update staging directory could not be created.');
        }
        if (!$zip->extractTo($directory)) {
            $zip->close();
            throw new RuntimeException('The release package could not be extracted.');
        }
        $zip->close();

        return $this->verifyDirectory($directory, $version);
    }

    public function verifyDirectory(string $directory, string $version): VerifiedPackage
    {
        $manifestPath = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . '.hs-release.json';
        $raw = file_get_contents($manifestPath);
        if (!is_string($raw)) {
            throw new RuntimeException('The release manifest is missing.');
        }
        $manifest = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($manifest) || ($manifest['version'] ?? '') !== $version || !is_array($manifest['files'] ?? null)) {
            throw new RuntimeException('The release manifest version or file list is invalid.');
        }
        $files = [];
        foreach ($manifest['files'] as $path => $expectedHash) {
            $path = (string) $path;
            $this->assertSafePath($path);
            if (preg_match('/^[a-f0-9]{64}$/', (string) $expectedHash) !== 1) {
                throw new RuntimeException('A release manifest digest is invalid.');
            }
            $fullPath = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
            if (!is_file($fullPath) || !hash_equals((string) $expectedHash, (string) hash_file('sha256', $fullPath))) {
                throw new RuntimeException('Release file verification failed: ' . $path);
            }
            $files[] = $path;
        }
        foreach (['index.php', 'composer.lock', 'src/bootstrap.php'] as $required) {
            if (!in_array($required, $files, true)) {
                throw new RuntimeException('The release package is incomplete.');
            }
        }
        $delete = [];
        foreach (($manifest['delete'] ?? []) as $path) {
            $path = (string) $path;
            $this->assertSafePath($path);
            $delete[] = $path;
        }

        return new VerifiedPackage($directory, $version, $files, $delete);
    }

    private function assertSafePath(string $path): void
    {
        $normalized = str_replace('\\', '/', trim($path));
        if (
            $normalized === '' || str_starts_with($normalized, '/')
            || preg_match('/(^|\/)\.\.($|\/)/', $normalized) === 1
            || preg_match('/(^|\/)\.($|\/)/', $normalized) === 1
            || str_contains($normalized, '//')
            || str_contains($normalized, "\0") || preg_match('/^[A-Za-z]:/', $normalized) === 1
        ) {
            throw new RuntimeException('The release package contains an unsafe path.');
        }
    }
}
