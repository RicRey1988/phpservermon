<?php

declare(strict_types=1);

namespace psm\Service\Update;

use JsonException;
use RuntimeException;

final readonly class ReleaseManifest
{
    private const KEYS = ['schema', 'version', 'archive', 'sha256', 'min_php', 'repository'];

    private function __construct(
        public int $schema,
        public string $version,
        public string $archive,
        public string $sha256,
        public string $minPhp,
        public string $repository,
    ) {
    }

    public static function fromBytes(string $bytes): self
    {
        try {
            $data = json_decode($bytes, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Release manifest JSON is invalid.', 0, $exception);
        }
        if (!is_array($data) || array_keys($data) !== self::KEYS) {
            throw new RuntimeException('Release manifest shape is invalid.');
        }
        $manifest = new self(
            is_int($data['schema']) ? $data['schema'] : 0,
            is_string($data['version']) ? $data['version'] : '',
            is_string($data['archive']) ? $data['archive'] : '',
            is_string($data['sha256']) ? $data['sha256'] : '',
            is_string($data['min_php']) ? $data['min_php'] : '',
            is_string($data['repository']) ? $data['repository'] : '',
        );
        if (
            $manifest->schema !== 1
            || preg_match('/^\d+\.\d+\.\d+-hs$/', $manifest->version) !== 1
            || $manifest->archive !== 'phpservermon-' . $manifest->version . '.zip'
            || preg_match('/^[a-f0-9]{64}$/', $manifest->sha256) !== 1
            || preg_match('/^\d+\.\d+\.\d+$/', $manifest->minPhp) !== 1
            || version_compare($manifest->minPhp, '8.5.0', '<')
            || $manifest->repository !== 'RicRey1988/phpservermon'
            || !hash_equals($manifest->canonicalBytes(), $bytes)
        ) {
            throw new RuntimeException('Release manifest values or canonical encoding are invalid.');
        }

        return $manifest;
    }

    public function canonicalBytes(): string
    {
        return json_encode([
            'schema' => $this->schema,
            'version' => $this->version,
            'archive' => $this->archive,
            'sha256' => $this->sha256,
            'min_php' => $this->minPhp,
            'repository' => $this->repository,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
    }
}
