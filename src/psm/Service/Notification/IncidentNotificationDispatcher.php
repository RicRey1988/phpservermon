<?php

declare(strict_types=1);

namespace psm\Service\Notification;

use Closure;
use DateTimeImmutable;
use psm\Notification\ChannelRegistry;
use psm\Notification\DeliveryResult;
use psm\Service\Incident\IncidentTransition;
use Throwable;

final class IncidentNotificationDispatcher
{
    /** @var Closure(): DateTimeImmutable */
    private Closure $clock;
    private bool $combine;

    /** @param callable(): DateTimeImmutable|null $clock */
    public function __construct(
        private readonly DeliveryRepository $deliveries,
        private readonly IncidentRecipientRepository $recipients,
        private readonly IncidentMessageComposer $composer,
        private readonly ChannelRegistry $channels,
        ?bool $combine = null,
        ?callable $clock = null,
    ) {
        $this->combine = $combine ?? (bool) psm_get_conf('combine_notifications');
        $this->clock = $clock === null
            ? static fn (): DateTimeImmutable => new DateTimeImmutable()
            : Closure::fromCallable($clock);
    }

    public function enqueue(IncidentTransition $transition): void
    {
        $message = $this->composer->inApp($transition);
        foreach ($this->recipients->forTransition($transition) as $recipient) {
            $userId = $recipient->recipient->userId();
            $this->deliveries->enqueueUserNotification(
                $transition,
                $userId,
                $message['title'],
                $message['body'],
            );
            foreach ($recipient->channels as $channel) {
                $this->deliveries->enqueueDelivery($transition, $userId, $channel);
            }
        }
    }

    public function flush(int $limit = 100): void
    {
        $now = ($this->clock)();
        $rows = $this->deliveries->claimDue($limit, $now);
        if (!$this->combine) {
            foreach ($rows as $row) {
                $this->deliver([$row], $now);
            }

            return;
        }

        $groups = [];
        foreach ($rows as $row) {
            $key = $row['user_id'] . ':' . $row['channel'] . ':' . $row['transition'];
            $groups[$key][] = $row;
        }
        foreach ($groups as $group) {
            $this->deliver($group, $now);
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function deliver(array $rows, DateTimeImmutable $now): void
    {
        $first = $rows[0];
        $ids = array_map(static fn (array $row): int => (int) $row['delivery_id'], $rows);
        $attempts = max(array_map(static fn (array $row): int => (int) $row['attempts'], $rows));
        try {
            if (!$this->channels->has((string) $first['channel'])) {
                $result = DeliveryResult::permanentFailure('Notification channel is unavailable.');
            } else {
                $message = count($rows) === 1 ? $this->composer->compose($first) : $this->composer->composeMany($rows);
                $result = $this->channels->get((string) $first['channel'])->send(
                    $message,
                    $this->recipients->recipient((int) $first['user_id']),
                );
            }
        } catch (Throwable) {
            $result = DeliveryResult::temporaryFailure('Notification delivery failed unexpectedly.');
        }

        $error = $this->sanitize($result->message());
        if ($result->status() === DeliveryResult::SUCCESS) {
            $this->deliveries->markDelivered($ids, $now);
        } elseif ($result->status() === DeliveryResult::TEMPORARY_FAILURE && $attempts <= 4) {
            $delays = [1 => 1, 2 => 5, 3 => 15, 4 => 60];
            $this->deliveries->reschedule($ids, $now->modify('+' . $delays[$attempts] . ' minutes'), $error, $now);
        } else {
            $this->deliveries->markPermanentFailure($ids, $error);
        }
    }

    private function sanitize(string $message): string
    {
        $clean = strip_tags($message);
        $clean = (string) preg_replace(
            '/\b(token|password|passwd|authorization)\s*[:=]\s*[^\s&]+/iu',
            '$1=[redacted]',
            $clean,
        );
        $clean = trim((string) preg_replace('/\s+/u', ' ', $clean));

        return mb_substr($clean, 0, 255);
    }
}
