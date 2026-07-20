<?php

declare(strict_types=1);

namespace Tests\Unit\Update;

use PHPUnit\Framework\TestCase;
use psm\Service\Update\ManifestVerifier;
use RuntimeException;

final class ManifestVerifierTest extends TestCase
{
    public function testVerifiesExactBytesSignatureVersionAndArchiveHash(): void
    {
        if (!function_exists('openssl_pkey_new')) {
            self::markTestSkipped('OpenSSL extension is required.');
        }
        [$private, $public] = $this->keyPair();
        $archive = tempnam(sys_get_temp_dir(), 'psm-archive-');
        self::assertIsString($archive);
        file_put_contents($archive, 'verified archive');
        $bytes = $this->manifest(hash_file('sha256', $archive));
        openssl_sign($bytes, $signature, $private, OPENSSL_ALGO_SHA256);

        $manifest = (new ManifestVerifier($public))->verify($bytes, base64_encode($signature), $archive, '4.1.0-hs');

        self::assertSame('4.1.1-hs', $manifest->version);
        unlink($archive);
    }

    public function testRejectsTamperedManifest(): void
    {
        if (!function_exists('openssl_pkey_new')) {
            self::markTestSkipped('OpenSSL extension is required.');
        }
        [$private, $public] = $this->keyPair();
        $archive = tempnam(sys_get_temp_dir(), 'psm-archive-');
        self::assertIsString($archive);
        file_put_contents($archive, 'verified archive');
        $bytes = $this->manifest(hash_file('sha256', $archive));
        openssl_sign($bytes, $signature, $private, OPENSSL_ALGO_SHA256);

        try {
            $this->expectException(RuntimeException::class);
            (new ManifestVerifier($public))->verify(str_replace('4.1.1-hs', '4.1.2-hs', $bytes), base64_encode($signature), $archive, '4.1.0-hs');
        } finally {
            unlink($archive);
        }
    }

    /** @return array{string,string} */
    private function keyPair(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        self::assertTrue(openssl_pkey_export($key, $private));
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        return [$private, $details['key']];
    }

    private function manifest(string $hash): string
    {
        return '{"schema":1,"version":"4.1.1-hs","archive":"phpservermon-4.1.1-hs.zip","sha256":"'
            . $hash . '","min_php":"8.5.0","repository":"RicRey1988/phpservermon"}' . "\n";
    }
}
