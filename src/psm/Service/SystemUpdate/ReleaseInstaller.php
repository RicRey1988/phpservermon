<?php

declare(strict_types=1);

namespace psm\Service\SystemUpdate;

use RuntimeException;
use Throwable;

final readonly class ReleaseInstaller
{
    public function __construct(private string $root, private string $logs)
    {
    }

    public function install(VerifiedPackage $package): int
    {
        if (!is_dir($this->root) || !is_writable($this->root) || !is_dir($this->logs) || !is_writable($this->logs)) {
            throw new RuntimeException('The application and logs directories must be writable for automatic updates.');
        }
        $updateDirectory = rtrim($this->root, '/\\') . DIRECTORY_SEPARATOR . '.psm-update';
        $this->ensureDirectory($updateDirectory);
        $lockPath = $updateDirectory . DIRECTORY_SEPARATOR . 'update.lock';
        $lock = fopen($lockPath, 'c+');
        if (!is_resource($lock) || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) { fclose($lock); }
            throw new RuntimeException('Another system update is already running.');
        }
        $rollback = $updateDirectory . DIRECTORY_SEPARATOR . 'rollback-' . bin2hex(random_bytes(8));
        if (!mkdir($rollback, 0700, true)) {
            flock($lock, LOCK_UN);
            fclose($lock);
            @unlink($lockPath);
            throw new RuntimeException('A temporary rollback directory could not be created.');
        }
        $backedUp = [];
        $created = [];
        $changed = 0;
        try {
            foreach ($package->files as $relative) {
                if ($this->isProtected($relative)) {
                    continue;
                }
                if (!$this->isAllowed($relative)) {
                    throw new RuntimeException('The signed package contains a path outside the application update allowlist.');
                }
                $source = $this->path($package->directory, $relative);
                $target = $this->path($this->root, $relative);
                if (!is_file($source)) {
                    throw new RuntimeException('A verified update file disappeared before installation.');
                }
                if (is_file($target)) {
                    $backup = $this->path($rollback, $relative);
                    $this->ensureDirectory(dirname($backup));
                    if (!copy($target, $backup)) {
                        throw new RuntimeException('A temporary rollback copy could not be created.');
                    }
                    $backedUp[$relative] = $backup;
                } else {
                    $created[] = $relative;
                }
                $this->ensureDirectory(dirname($target));
                $temporary = $target . '.psm-new-' . bin2hex(random_bytes(4));
                if (!copy($source, $temporary)) {
                    throw new RuntimeException('An update file could not be staged for replacement.');
                }
                @chmod($temporary, is_file($target) ? (fileperms($target) & 0777) : 0644);
                if (PHP_OS_FAMILY === 'Windows' && is_file($target) && !unlink($target)) {
                    @unlink($temporary);
                    throw new RuntimeException('An existing application file could not be replaced.');
                }
                if (!rename($temporary, $target)) {
                    @unlink($temporary);
                    throw new RuntimeException('An update file could not be installed atomically.');
                }
                $changed++;
            }
            foreach ($package->delete as $relative) {
                if ($this->isProtected($relative)) {
                    continue;
                }
                if (!$this->isAllowed($relative)) {
                    throw new RuntimeException('The signed deletion list contains a path outside the application update allowlist.');
                }
                $target = $this->path($this->root, $relative);
                if (!is_file($target)) {
                    continue;
                }
                $backup = $this->path($rollback, $relative);
                $this->ensureDirectory(dirname($backup));
                if (!copy($target, $backup) || !unlink($target)) {
                    throw new RuntimeException('An obsolete application file could not be removed safely.');
                }
                $backedUp[$relative] = $backup;
                $changed++;
            }
        } catch (Throwable $exception) {
            foreach ($created as $relative) {
                $target = $this->path($this->root, $relative);
                if (is_file($target)) { @unlink($target); }
            }
            foreach ($backedUp as $relative => $backup) {
                $target = $this->path($this->root, $relative);
                $this->ensureDirectory(dirname($target));
                @copy($backup, $target);
            }
            throw new RuntimeException('The update was rolled back: ' . $exception->getMessage(), 0, $exception);
        } finally {
            $this->removeDirectory($rollback);
            flock($lock, LOCK_UN);
            fclose($lock);
            @unlink($lockPath);
        }

        return $changed;
    }

    private function isProtected(string $relative): bool
    {
        $path = trim(str_replace('\\', '/', $relative), '/');
        return in_array($path, ['config.php', '.env'], true)
            || str_starts_with($path, 'logs/')
            || str_starts_with($path, 'public/server-images/')
            || str_starts_with($path, 'icons/')
            || str_starts_with($path, '.psm-update/')
            || str_starts_with($path, '.git/');
    }

    private function isAllowed(string $relative): bool
    {
        $path = trim(str_replace('\\', '/', $relative), '/');
        foreach (['cron/', 'bin/', 'src/', 'vendor/', 'docs/'] as $directory) {
            if (str_starts_with($path, $directory)) { return true; }
        }
        return in_array($path, [
            'index.php', 'install.php', 'public.php', '.htaccess', 'composer.json', 'composer.lock',
            'LICENSE', 'README.rst', 'CHANGELOG.md', 'CHANGELOG.rst', 'manifest.webmanifest',
            'service-worker.js', 'offline.html', 'favicon.ico', 'favicon.png', 'phpservermon.png',
        ], true);
    }

    private function path(string $base, string $relative): string
    {
        return rtrim($base, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
            throw new RuntimeException('A required update directory could not be created.');
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) { return; }
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        ) as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }
}
