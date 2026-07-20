<?php

declare(strict_types=1);

namespace Tests\Unit\Notification;

use PHPUnit\Framework\TestCase;
use psm\Notification\Http\RetryingHttpTransport;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RetryingHttpTransportTest extends TestCase
{
    public function testRetriesOneServerErrorThenSucceeds(): void
    {
        $client = new MockHttpClient([
            new MockResponse('{"ok":false}', ['http_code' => 503]),
            new MockResponse('{"ok":true}', ['http_code' => 200]),
        ]);
        $transport = new RetryingHttpTransport($client, 2, static function (): void {});

        $result = $transport->post('https://example.test/send', ['json' => ['text' => 'test']]);

        self::assertTrue($result->isSuccess());
        self::assertSame(2, $result->attempts());
        self::assertSame(['ok' => true], $result->data());
    }

    public function testRetriesRateLimit(): void
    {
        $client = new MockHttpClient([
            new MockResponse('{"retry_after":1}', ['http_code' => 429]),
            new MockResponse('{"ok":true}', ['http_code' => 200]),
        ]);

        $result = (new RetryingHttpTransport($client, 2, static function (): void {}))
            ->post('https://example.test/send', []);

        self::assertTrue($result->isSuccess());
        self::assertSame(2, $result->attempts());
    }

    public function testDoesNotRetryPermanentClientError(): void
    {
        $client = new MockHttpClient(new MockResponse('{"error":"bad request"}', ['http_code' => 400]));

        $result = (new RetryingHttpTransport($client, 3, static function (): void {}))
            ->post('https://example.test/send', []);

        self::assertTrue($result->isPermanentFailure());
        self::assertSame(1, $result->attempts());
    }

    public function testMalformedJsonIsPermanent(): void
    {
        $client = new MockHttpClient(new MockResponse('not-json', ['http_code' => 200]));

        $result = (new RetryingHttpTransport($client, 3, static function (): void {}))
            ->post('https://example.test/send', []);

        self::assertTrue($result->isPermanentFailure());
        self::assertSame('Remote service returned an invalid response.', $result->message());
    }

    public function testTransportExceptionDoesNotLeakSecretUrl(): void
    {
        $client = new MockHttpClient(static function (): MockResponse {
            throw new \RuntimeException('network failed for secret-token');
        });

        $result = (new RetryingHttpTransport($client, 2, static function (): void {}))
            ->post('https://example.test/secret-token', ['body' => ['password' => 'secret-value']]);

        self::assertTrue($result->isTemporaryFailure());
        self::assertSame(2, $result->attempts());
        self::assertStringNotContainsString('secret-token', $result->message());
        self::assertStringNotContainsString('secret-value', $result->message());
    }

    public function testSuccessfulResponseWithoutJsonStillSendsRequest(): void
    {
        $requests = 0;
        $client = new MockHttpClient(static function () use (&$requests): MockResponse {
            $requests++;
            return new MockResponse('', ['http_code' => 204]);
        });

        $result = (new RetryingHttpTransport($client))
            ->post('https://example.test/webhook', ['psm_expect_json' => false]);

        self::assertTrue($result->isSuccess());
        self::assertSame(1, $result->attempts());
        self::assertSame(1, $requests);
    }
}
