<?php

declare(strict_types=1);

namespace psm\Notification\Http;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class RetryingHttpTransport
{
    /** @var callable(int): void */
    private $sleeper;

    /**
     * @param callable(int): void|null $sleeper Receives the one-based failed attempt number.
     */
    public function __construct(
        private readonly HttpClientInterface $client,
        int $maxAttempts = 3,
        ?callable $sleeper = null
    ) {
        $this->maxAttempts = max(1, min(3, $maxAttempts));
        $this->sleeper = $sleeper ?? static fn (int $attempt): int => usleep($attempt * 250000);
    }

    private readonly int $maxAttempts;

    /** @param array<string, mixed> $options */
    public function post(string $url, array $options): HttpTransportResult
    {
        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            try {
                $response = $this->client->request('POST', $url, $options);
                $statusCode = $response->getStatusCode();
                $body = $response->getContent(false);
            } catch (\Throwable) {
                if ($attempt < $this->maxAttempts) {
                    ($this->sleeper)($attempt);
                    continue;
                }

                return HttpTransportResult::temporaryFailure(
                    'Remote service is temporarily unavailable.',
                    $attempt
                );
            }

            if ($statusCode === 429 || $statusCode >= 500) {
                if ($attempt < $this->maxAttempts) {
                    ($this->sleeper)($attempt);
                    continue;
                }

                return HttpTransportResult::temporaryFailure(
                    'Remote service is temporarily unavailable.',
                    $attempt,
                    $statusCode
                );
            }

            if ($statusCode < 200 || $statusCode >= 300) {
                return HttpTransportResult::permanentFailure(
                    'Remote service rejected the request.',
                    $attempt,
                    $statusCode
                );
            }

            try {
                $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return HttpTransportResult::permanentFailure(
                    'Remote service returned an invalid response.',
                    $attempt,
                    $statusCode
                );
            }

            if (!is_array($data)) {
                return HttpTransportResult::permanentFailure(
                    'Remote service returned an invalid response.',
                    $attempt,
                    $statusCode
                );
            }

            return HttpTransportResult::success($data, $attempt, $statusCode);
        }

        return HttpTransportResult::temporaryFailure('Remote service is temporarily unavailable.', 0);
    }
}
