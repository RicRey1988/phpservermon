<?php

declare(strict_types=1);

namespace Tests\Unit\System;

use PHPUnit\Framework\TestCase;
use psm\Service\SystemUpdate\GitHubReleaseClient;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GitHubReleaseClientTest extends TestCase
{
    public function testAcceptsOnlyHsReleaseWithGitHubSha256Digest(): void
    {
        $requests = [];
        $payload = [
            'tag_name' => 'v4.2.0-hs',
            'html_url' => 'https://github.com/RicRey1988/phpservermon-Redesigned-by-hostingsupremo/releases/tag/v4.2.0-hs',
            'name' => '4.2.0-hs',
            'body' => 'Release notes',
            'draft' => false,
            'prerelease' => false,
            'assets' => array_map(static fn (string $suffix): array => [
                'name' => 'phpservermon-4.2.0-hs.' . $suffix,
                'browser_download_url' => 'https://github.com/RicRey1988/phpservermon-Redesigned-by-hostingsupremo/releases/download/v4.2.0-hs/phpservermon-4.2.0-hs.' . $suffix,
                'digest' => 'sha256:' . str_repeat('a', 64),
                'size' => 2048,
            ], ['zip', 'json', 'json.sig']),
        ];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests, $payload): MockResponse {
            $requests[] = [$method, $url, $options];
            return new MockResponse(json_encode($payload, JSON_THROW_ON_ERROR), ['http_code' => 200]);
        });

        $release = (new GitHubReleaseClient($client))->latest();

        self::assertSame('4.2.0-hs', $release->version);
        self::assertSame(str_repeat('a', 64), $release->asset->sha256);
        self::assertSame('phpservermon-4.2.0-hs.json', $release->manifestAsset->name);
        self::assertSame('phpservermon-4.2.0-hs.json.sig', $release->signatureAsset->name);
        self::assertTrue($release->isNewerThan('4.1.0-hs'));
        self::assertSame('https://api.github.com/repos/RicRey1988/phpservermon-Redesigned-by-hostingsupremo/releases/latest', $requests[0][1]);
        self::assertContains('Accept: application/vnd.github+json', $requests[0][2]['headers']);
    }

    public function testRejectsUnverifiedOrNonHsReleaseAssets(): void
    {
        $payload = [
            'tag_name' => '4.2.0', 'draft' => false, 'prerelease' => false,
            'assets' => [],
        ];
        $this->expectException(RuntimeException::class);
        (new GitHubReleaseClient(new MockHttpClient(new MockResponse(
            json_encode($payload, JSON_THROW_ON_ERROR),
            ['http_code' => 200],
        ))))->latest();
    }

    public function testAcceptsAssetsUsingTheLegacyRedirectingRepositorySlug(): void
    {
        $payload = [
            'tag_name' => 'v4.2.0-hs',
            'html_url' => 'https://github.com/RicRey1988/phpservermon/releases/tag/v4.2.0-hs',
            'name' => '4.2.0-hs',
            'body' => '',
            'draft' => false,
            'prerelease' => false,
            'assets' => array_map(static fn (string $suffix): array => [
                'name' => 'phpservermon-4.2.0-hs.' . $suffix,
                'browser_download_url' => 'https://github.com/RicRey1988/phpservermon/releases/download/v4.2.0-hs/phpservermon-4.2.0-hs.' . $suffix,
                'digest' => 'sha256:' . str_repeat('b', 64),
                'size' => 2048,
            ], ['zip', 'json', 'json.sig']),
        ];
        $client = new MockHttpClient(new MockResponse(
            json_encode($payload, JSON_THROW_ON_ERROR),
            ['http_code' => 200],
        ));

        self::assertSame('4.2.0-hs', (new GitHubReleaseClient($client))->latest()->version);
    }

    public function testIgnoresAnOlderLegacyReleaseBeforeRequiringSignedAssets(): void
    {
        $payload = [
            'tag_name' => 'v3.5.3-hs',
            'draft' => false,
            'prerelease' => false,
            'assets' => [],
        ];
        $client = new GitHubReleaseClient(new MockHttpClient(new MockResponse(
            json_encode($payload, JSON_THROW_ON_ERROR),
            ['http_code' => 200],
        )));

        self::assertNull($client->newerThan('4.1.0-hs'));
    }
}
