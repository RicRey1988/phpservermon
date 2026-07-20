<?php

declare(strict_types=1);

namespace psm\Service\Update;

use RuntimeException;

final readonly class ManifestVerifier
{
    private string $publicKey;

    public function __construct(?string $publicKeyOrPath = null)
    {
        $source = $publicKeyOrPath ?? __DIR__ . '/keys/hosting-supremo-release-public.pem';
        if (str_contains($source, '-----BEGIN PUBLIC KEY-----')) {
            $this->publicKey = $source;
            return;
        }
        $contents = file_get_contents($source);
        if (!is_string($contents)) {
            throw new RuntimeException('The pinned HS release public key is unavailable.');
        }
        $this->publicKey = $contents;
    }

    public function verify(
        string $manifestBytes,
        string $signatureBase64,
        string $archivePath,
        string $currentVersion,
    ): ReleaseManifest {
        $manifest = ReleaseManifest::fromBytes($manifestBytes);
        if (!version_compare($manifest->version, ltrim($currentVersion, 'vV'), '>')) {
            throw new RuntimeException('The signed HS release is not newer than the installed version.');
        }
        if (version_compare(PHP_VERSION, $manifest->minPhp, '<')) {
            throw new RuntimeException('This release requires PHP ' . $manifest->minPhp . ' or newer.');
        }
        $signature = base64_decode(trim($signatureBase64), true);
        $publicKey = openssl_pkey_get_public($this->publicKey);
        if (
            $signature === false || $publicKey === false
            || openssl_verify($manifestBytes, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1
        ) {
            throw new RuntimeException('Release signature is invalid.');
        }
        $actual = is_file($archivePath) ? hash_file('sha256', $archivePath) : false;
        if (!is_string($actual) || !hash_equals($manifest->sha256, $actual)) {
            throw new RuntimeException('Release archive checksum does not match.');
        }

        return $manifest;
    }
}
