<?php

declare(strict_types=1);

namespace psm\Service\SystemUpdate;

use RuntimeException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GitHubReleaseClient
{
    private const API_URL = 'https://api.github.com/repos/RicRey1988/phpservermon/releases/latest';

    private HttpClientInterface $http;

    public function __construct(?HttpClientInterface $http = null)
    {
        $this->http = $http ?? HttpClient::create(['timeout' => 30, 'max_redirects' => 5]);
    }

    public function latest(): ReleaseInfo
    {
        $release = $this->fetchRelease();
        if (!$release instanceof ReleaseInfo) {
            throw new RuntimeException('GitHub did not return a release.');
        }

        return $release;
    }

    public function newerThan(string $currentVersion): ?ReleaseInfo
    {
        return $this->fetchRelease($currentVersion);
    }

    private function fetchRelease(?string $currentVersion = null): ?ReleaseInfo
    {
        $response = $this->http->request('GET', self::API_URL, ['headers' => [
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent' => 'PHPServerMon-HS-Updater',
        ]]);
        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException('GitHub release information is temporarily unavailable.');
        }
        $payload = $response->toArray(false);
        $version = ltrim(trim((string) ($payload['tag_name'] ?? '')), 'vV');
        if (
            preg_match('/^\d+\.\d+\.\d+-hs$/', $version) !== 1
            || ($payload['draft'] ?? true) === true
            || ($payload['prerelease'] ?? true) === true
        ) {
            throw new RuntimeException('The latest GitHub release is not a stable HS release.');
        }
        if ($currentVersion !== null && version_compare($version, $currentVersion, '<=')) {
            return null;
        }
        $baseName = 'phpservermon-' . $version;
        $expectedNames = [$baseName . '.zip', $baseName . '.json', $baseName . '.json.sig'];
        $assets = [];
        foreach (($payload['assets'] ?? []) as $candidate) {
            if (!is_array($candidate) || !in_array(($candidate['name'] ?? ''), $expectedNames, true)) {
                continue;
            }
            $name = (string) $candidate['name'];
            $url = (string) ($candidate['browser_download_url'] ?? '');
            $digest = (string) ($candidate['digest'] ?? '');
            if (
                !str_starts_with($url, 'https://github.com/RicRey1988/phpservermon/releases/download/')
                || preg_match('/^sha256:([a-f0-9]{64})$/', $digest, $matches) !== 1
            ) {
                throw new RuntimeException('The release asset does not provide a trusted SHA-256 digest.');
            }
            $size = (int) ($candidate['size'] ?? 0);
            if ($size <= 0 || $size > 150 * 1024 * 1024) {
                throw new RuntimeException('The release asset size is invalid.');
            }
            $assets[$name] = new ReleaseAsset($name, $url, $matches[1], $size);
        }
        foreach ($expectedNames as $expectedName) {
            if (!isset($assets[$expectedName])) {
                throw new RuntimeException('A required signed HS release asset is missing.');
            }
        }

        return new ReleaseInfo(
            $version,
            mb_substr(trim((string) ($payload['name'] ?? $version)), 0, 160),
            mb_substr(trim((string) ($payload['body'] ?? '')), 0, 10000),
            (string) ($payload['html_url'] ?? ''),
            $assets[$expectedNames[0]],
            $assets[$expectedNames[1]],
            $assets[$expectedNames[2]],
        );
    }

    public function download(ReleaseAsset $asset, string $destination): void
    {
        $response = $this->http->request('GET', $asset->downloadUrl, ['headers' => [
            'Accept' => 'application/octet-stream',
            'User-Agent' => 'PHPServerMon-HS-Updater',
        ]]);
        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException('The release package could not be downloaded.');
        }
        $contents = $response->getContent();
        if (strlen($contents) !== $asset->size || !hash_equals($asset->sha256, hash('sha256', $contents))) {
            throw new RuntimeException('The release package digest or size does not match GitHub metadata.');
        }
        if (file_put_contents($destination, $contents, LOCK_EX) !== strlen($contents)) {
            throw new RuntimeException('The verified release package could not be stored.');
        }
    }
}
