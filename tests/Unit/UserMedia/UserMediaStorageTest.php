<?php

declare(strict_types=1);

namespace Tests\Unit\UserMedia;

use PHPUnit\Framework\TestCase;
use psm\Service\ServerImage\ProcessedImage;
use psm\Service\UserMedia\UserMediaStorage;

final class UserMediaStorageTest extends TestCase
{
    public function testStoresOnlyDeterministicWebpIdentityFiles(): void
    {
        $directory = sys_get_temp_dir() . '/psm-identity-' . bin2hex(random_bytes(4));
        $storage = new UserMediaStorage($directory, 'public/user-media');
        $image = new ProcessedImage('webp-bytes', 'webp', 128, 128);

        self::assertSame('site-logo.webp', $storage->storeLogo($image));
        self::assertSame('avatar-7.webp', $storage->storeAvatar(7, $image));
        self::assertSame('public/user-media/site-logo.webp', $storage->logoUrl('site-logo.webp'));
        self::assertSame('public/user-media/avatar-7.webp', $storage->avatarUrl(7, 'avatar-7.webp'));
        self::assertNull($storage->avatarUrl(7, '../avatar-7.webp'));

        $storage->deleteLogo();
        $storage->deleteAvatar(7);
        rmdir($directory);
    }
}
