<?php

declare(strict_types=1);

namespace Tests\Unit\Update;

use PHPUnit\Framework\TestCase;
use psm\Service\Update\ReleaseManifest;
use RuntimeException;

final class ReleaseManifestTest extends TestCase
{
    public function testParsesOnlyCanonicalHsManifest(): void
    {
        $bytes = '{"schema":1,"version":"4.1.1-hs","archive":"phpservermon-4.1.1-hs.zip","sha256":"'
            . str_repeat('a', 64)
            . '","min_php":"8.5.0","repository":"RicRey1988/phpservermon"}' . "\n";

        $manifest = ReleaseManifest::fromBytes($bytes);

        self::assertSame('4.1.1-hs', $manifest->version);
        self::assertSame($bytes, $manifest->canonicalBytes());
    }

    public function testRejectsExtraKeysAndNonCanonicalBytes(): void
    {
        $bytes = json_encode([
            'schema' => 1, 'version' => '4.1.1-hs', 'archive' => 'phpservermon-4.1.1-hs.zip',
            'sha256' => str_repeat('a', 64), 'min_php' => '8.5.0',
            'repository' => 'RicRey1988/phpservermon', 'url' => 'https://evil.example',
        ], JSON_THROW_ON_ERROR) . "\n";

        $this->expectException(RuntimeException::class);
        ReleaseManifest::fromBytes($bytes);
    }
}
