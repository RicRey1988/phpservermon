<?php

declare(strict_types=1);

namespace psm\Notification\Channel;

use Closure;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use psm\Notification\DeliveryResult;
use psm\Notification\NotificationChannelInterface;
use psm\Notification\NotificationMessage;
use psm\Notification\Recipient;
use psm\Service\Push\PushSubscriptionRepository;
use Throwable;

final class WebPushChannel implements NotificationChannelInterface
{
    /** @var array{subject:string, publicKey:string, privateKey:string} */
    private array $vapid;
    /** @var Closure(array<string, mixed>, string): WebPushSendResult */
    private Closure $sender;

    /**
     * @param array{subject:string, publicKey:string, privateKey:string} $vapid
     * @param null|callable(array<string, mixed>, string): WebPushSendResult $sender
     */
    public function __construct(
        private readonly PushSubscriptionRepository $subscriptions,
        array $vapid,
        ?callable $sender = null,
        private readonly string $baseUrl = '',
    ) {
        $this->vapid = $vapid;
        $this->sender = $sender === null
            ? Closure::fromCallable([$this, 'sendWithLibrary'])
            : Closure::fromCallable($sender);
    }

    public function name(): string
    {
        return 'webpush';
    }

    public function send(NotificationMessage $message, Recipient $recipient): DeliveryResult
    {
        if (!$this->isConfigured()) {
            return DeliveryResult::skipped('Web Push is not configured.');
        }
        $devices = $this->subscriptions->forUser($recipient->userId());
        if ($devices === []) {
            return DeliveryResult::skipped('No Web Push devices are registered.');
        }

        $payload = $this->payload($message);
        $successes = 0;
        $temporary = false;
        $lastReason = 'Web Push delivery failed.';
        foreach ($devices as $device) {
            try {
                $result = ($this->sender)($device, $payload);
            } catch (Throwable $exception) {
                $result = WebPushSendResult::temporaryFailure($exception->getMessage());
            }
            if ($result->successful) {
                $successes++;
                continue;
            }
            if ($result->expired) {
                $hash = (string) ($device['endpoint_hash'] ?? '');
                if ($hash !== '') {
                    $this->subscriptions->deleteByHash($hash);
                }
                continue;
            }
            $temporary = $temporary || $result->temporary;
            $lastReason = $this->safeReason($result->reason);
        }

        if ($temporary) {
            return DeliveryResult::temporaryFailure($lastReason);
        }
        if ($successes > 0) {
            return DeliveryResult::success(sprintf('Web Push delivered to %d device(s).', $successes));
        }

        return DeliveryResult::permanentFailure($lastReason);
    }

    /** @param array<string, mixed> $device */
    private function sendWithLibrary(array $device, string $payload): WebPushSendResult
    {
        $subscription = Subscription::create([
            'endpoint' => (string) ($device['endpoint'] ?? ''),
            'publicKey' => (string) ($device['public_key'] ?? ''),
            'authToken' => (string) ($device['auth_token'] ?? ''),
            'contentEncoding' => (string) ($device['content_encoding'] ?? 'aes128gcm'),
        ]);
        $webPush = new WebPush(['VAPID' => $this->vapid], ['TTL' => 86400, 'urgency' => 'high'], 30);
        $report = $webPush->sendOneNotification($subscription, $payload);
        if ($report->isSuccess()) {
            return WebPushSendResult::success();
        }
        if ($report->isSubscriptionExpired()) {
            return WebPushSendResult::expired($report->getReason());
        }
        $status = $report->getResponse()?->getStatusCode();
        if ($status === null || in_array($status, [408, 425, 429], true) || $status >= 500) {
            return WebPushSendResult::temporaryFailure($report->getReason());
        }

        return WebPushSendResult::permanentFailure($report->getReason());
    }

    private function isConfigured(): bool
    {
        return trim($this->vapid['subject']) !== ''
            && trim($this->vapid['publicKey']) !== ''
            && trim($this->vapid['privateKey']) !== '';
    }

    private function payload(NotificationMessage $message): string
    {
        $context = $message->context();
        $incident = preg_replace('/\D+/', '', (string) ($context['incident_id'] ?? 'notification')) ?: 'notification';
        $transition = preg_replace('/[^a-z0-9_-]+/i', '', (string) ($context['transition'] ?? 'update')) ?: 'update';
        return (string) json_encode([
            'title' => mb_substr($message->subject(), 0, 120),
            'body' => mb_substr($message->body(), 0, 300),
            'icon' => 'pwa/icon-192.png',
            'badge' => 'pwa/icon-192.png',
            'tag' => 'incident-' . $incident . '-' . $transition,
            'url' => $this->safeUrl($message->url()),
            'critical' => $message->isCritical(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function safeUrl(?string $url): string
    {
        $fallback = rtrim($this->baseUrl, '/') . '/index.php?mod=server_status';
        if ($url === null || trim($url) === '') {
            return $fallback;
        }
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            return rtrim($this->baseUrl, '/') . '/' . ltrim($url, '/');
        }
        $expected = parse_url($this->baseUrl);
        $actual = parse_url($url);
        if (!is_array($expected) || !is_array($actual)) {
            return $fallback;
        }
        if (
            strtolower((string) ($actual['scheme'] ?? '')) !== strtolower((string) ($expected['scheme'] ?? ''))
            || strtolower((string) ($actual['host'] ?? '')) !== strtolower((string) ($expected['host'] ?? ''))
            || (int) ($actual['port'] ?? 0) !== (int) ($expected['port'] ?? 0)
        ) {
            return $fallback;
        }

        return $url;
    }

    private function safeReason(string $reason): string
    {
        $reason = preg_replace('/https?:\/\/\S+/i', '[endpoint]', $reason) ?? 'Web Push delivery failed.';
        return mb_substr(strip_tags($reason), 0, 300);
    }
}
