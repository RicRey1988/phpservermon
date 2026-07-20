<?php

declare(strict_types=1);

namespace Tests\Unit\System;

use PHPUnit\Framework\TestCase;
use psm\Service\SystemUpdate\ReleasePackageVerifier;
use RuntimeException;

final class ReleasePackageVerifierTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/psm-verifier-' . bin2hex(random_bytes(4));
        mkdir($this->directory . '/src', 0777, true);
        file_put_contents($this->directory . '/index.php', '<?php');
        file_put_contents($this->directory . '/composer.lock', '{}');
        file_put_contents($this->directory . '/src/bootstrap.php', '<?php');
    }

    protected function tearDown(): void
    {
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        ) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        @rmdir($this->directory);
    }

    public function testVerifiesEveryManifestFileAndProtectedDeletionRules(): void
    {
        $files = ['index.php', 'composer.lock', 'src/bootstrap.php'];
        $hashes = [];
        foreach ($files as $file) { $hashes[$file] = hash_file('sha256', $this->directory . '/' . $file); }
        file_put_contents($this->directory . '/.hs-release.json', json_encode([
            'version' => '4.2.0-hs', 'files' => $hashes, 'delete' => ['old-file.php'],
        ], JSON_THROW_ON_ERROR));

        $verified = (new ReleasePackageVerifier())->verifyDirectory($this->directory, '4.2.0-hs');

        self::assertSame($files, $verified->files);
        self::assertSame(['old-file.php'], $verified->delete);
    }

    public function testRejectsHashMismatchAndTraversal(): void
    {
        file_put_contents($this->directory . '/.hs-release.json', json_encode([
            'version' => '4.2.0-hs',
            'files' => ['../config.php' => str_repeat('a', 64), 'index.php' => str_repeat('b', 64)],
        ], JSON_THROW_ON_ERROR));

        $this->expectException(RuntimeException::class);
        (new ReleasePackageVerifier())->verifyDirectory($this->directory, '4.2.0-hs');
    }
}
