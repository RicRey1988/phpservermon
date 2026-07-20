<?php

declare(strict_types=1);

namespace Tests\Unit\System;

use PHPUnit\Framework\TestCase;
use psm\Service\SystemUpdate\ReleaseInstaller;
use psm\Service\SystemUpdate\VerifiedPackage;

final class ReleaseInstallerTest extends TestCase
{
    private string $root;
    private string $stage;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/psm-installer-' . bin2hex(random_bytes(4));
        $this->root = $base . '/root';
        $this->stage = $base . '/stage';
        mkdir($this->root . '/logs', 0777, true);
        mkdir($this->root . '/src', 0777, true);
        mkdir($this->stage, 0777, true);
        mkdir($this->stage . '/src', 0777, true);
        file_put_contents($this->root . '/index.php', 'old');
        file_put_contents($this->root . '/config.php', 'private-config');
        file_put_contents($this->root . '/logs/app.log', 'private-log');
        file_put_contents($this->root . '/src/obsolete.php', 'obsolete');
        file_put_contents($this->stage . '/index.php', 'new');
        file_put_contents($this->stage . '/config.php', 'malicious');
        file_put_contents($this->stage . '/src/new.php', 'added');
    }

    protected function tearDown(): void
    {
        $base = dirname($this->root);
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($base);
    }

    public function testAppliesVerifiedFilesWhilePreservingPrivateRuntimeData(): void
    {
        $package = new VerifiedPackage(
            $this->stage,
            '4.2.0-hs',
            ['index.php', 'config.php', 'src/new.php'],
            ['src/obsolete.php', 'logs/app.log'],
        );

        $count = (new ReleaseInstaller($this->root, $this->root . '/logs'))->install($package);

        self::assertSame(3, $count);
        self::assertSame('new', file_get_contents($this->root . '/index.php'));
        self::assertSame('added', file_get_contents($this->root . '/src/new.php'));
        self::assertSame('private-config', file_get_contents($this->root . '/config.php'));
        self::assertSame('private-log', file_get_contents($this->root . '/logs/app.log'));
        self::assertFileDoesNotExist($this->root . '/src/obsolete.php');
    }
}
