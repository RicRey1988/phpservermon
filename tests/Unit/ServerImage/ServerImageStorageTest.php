<?php

declare(strict_types=1);

namespace Tests\Unit\ServerImage;

use PHPUnit\Framework\TestCase;
use psm\Service\ServerImage\ProcessedImage;
use psm\Service\ServerImage\ServerImageStorage;

final class ServerImageStorageTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/psm-storage-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->directory));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    public function testStoresOnlyTheServerIdFilenameAndResolvesItsUrl(): void
    {
        $storage = new ServerImageStorage($this->directory, 'public/server-images', 'generic.svg');
        $file = $storage->store(42, new ProcessedImage('webp-bytes', 'webp', 1, 1));

        self::assertSame('server-42.webp', $file);
        self::assertFileExists($this->directory . '/server-42.webp');
        self::assertSame('public/server-images/server-42.webp', $storage->urlFor($file));
    }

    public function testMissingOrUntrustedDatabaseValueUsesGenericImage(): void
    {
        $storage = new ServerImageStorage($this->directory, 'public/server-images', 'generic.svg');

        self::assertSame('generic.svg', $storage->urlFor(null));
        self::assertSame('generic.svg', $storage->urlFor('server-42.webp'));
        self::assertSame('generic.svg', $storage->urlFor('../config.php'));
    }

    public function testDeleteTargetsOnlyTheExactNumericServerPath(): void
    {
        $storage = new ServerImageStorage($this->directory, 'public/server-images', 'generic.svg');
        $storage->store(42, new ProcessedImage('image', 'webp', 1, 1));
        file_put_contents($this->directory . '/server-420.webp', 'keep');

        $storage->delete(42);

        self::assertFileDoesNotExist($this->directory . '/server-42.webp');
        self::assertFileExists($this->directory . '/server-420.webp');
    }
}
