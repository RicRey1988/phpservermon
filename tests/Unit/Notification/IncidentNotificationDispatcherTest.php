<?php

declare(strict_types=1);

namespace Tests\Unit\Notification;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use psm\Notification\ChannelRegistry;
use psm\Notification\DeliveryResult;
use psm\Notification\NotificationChannelInterface;
use psm\Notification\NotificationMessage;
use psm\Notification\Recipient;
use psm\Service\Incident\IncidentTransition;
use psm\Service\Incident\IncidentTransitionType;
use psm\Service\Notification\DeliveryRepository;
use psm\Service\Notification\IncidentMessageComposer;
use psm\Service\Notification\IncidentNotificationDispatcher;
use psm\Service\Notification\IncidentRecipient;
use psm\Service\Notification\IncidentRecipientRepository;

final class IncidentNotificationDispatcherTest extends TestCase
{
    public function testRepositoryResetsStaleLeasesAndUsesIdempotentEnqueue(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/src/psm/Service/Notification/DeliveryRepository.php');
        self::assertIsString($source);
        self::assertStringContainsString('INSERT IGNORE', $source);
        self::assertStringContainsString("-5 minutes", $source);
        self::assertStringContainsString("state = 'sending'", $source);
        self::assertStringContainsString('FOR UPDATE', $source);
    }

    public function testEnqueueCreatesOneUniqueDeliveryAndInAppAlertPerRecipientTransition(): void
    {
        $store = new MemoryDeliveryRepository();
        $recipients = new FixedRecipientRepository([
            new IncidentRecipient(new Recipient(3, ['email' => 'a@example.test']), ['email', 'telegram']),
        ]);
        $dispatcher = $this->dispatcher($store, $recipients, new SequenceDeliveryChannel('email'), new SequenceDeliveryChannel('telegram'));
        $transition = $this->transition(5, IncidentTransitionType::Down);

        $dispatcher->enqueue($transition);
        $dispatcher->enqueue($transition);

        self::assertCount(2, $store->deliveries);
        self::assertCount(1, $store->notifications);
    }

    public function testTemporaryFailuresUseBoundedRetryScheduleAndThenBecomePermanent(): void
    {
        $store = new MemoryDeliveryRepository();
        $channel = new SequenceDeliveryChannel('email', array_fill(0, 5, DeliveryResult::temporaryFailure('network token=secret')));
        $dispatcher = $this->dispatcher($store, new FixedRecipientRepository([
            new IncidentRecipient(new Recipient(8, ['email' => 'a@example.test']), ['email']),
        ]), $channel);
        $dispatcher->enqueue($this->transition(6, IncidentTransitionType::Down));

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $dispatcher->flush();
        }

        self::assertSame([1, 5, 15, 60], $store->retryMinutes);
        self::assertSame('permanent_failure', $store->deliveries[0]['state']);
        self::assertSame(5, $channel->calls);
        self::assertStringNotContainsString('secret', (string) $store->deliveries[0]['last_error']);
    }

    public function testSuccessfulAndPermanentRowsAreNeverSentAgain(): void
    {
        $store = new MemoryDeliveryRepository();
        $success = new SequenceDeliveryChannel('email', [DeliveryResult::success()]);
        $permanent = new SequenceDeliveryChannel('telegram', [DeliveryResult::permanentFailure('invalid recipient')]);
        $dispatcher = $this->dispatcher($store, new FixedRecipientRepository([
            new IncidentRecipient(new Recipient(9), ['email', 'telegram']),
        ]), $success, $permanent);
        $dispatcher->enqueue($this->transition(7, IncidentTransitionType::Recovery));

        $dispatcher->flush();
        $dispatcher->flush();

        self::assertSame(1, $success->calls);
        self::assertSame(1, $permanent->calls);
        self::assertSame('delivered', $store->deliveries[0]['state']);
        self::assertSame('permanent_failure', $store->deliveries[1]['state']);
    }

    public function testCombinedModeSendsOneMessageAndCompletesEveryUnderlyingRow(): void
    {
        $store = new MemoryDeliveryRepository();
        $channel = new SequenceDeliveryChannel('email', [DeliveryResult::success()]);
        $recipients = new FixedRecipientRepository([
            new IncidentRecipient(new Recipient(11), ['email']),
        ]);
        $dispatcher = $this->dispatcher($store, $recipients, $channel, combine: true);
        $dispatcher->enqueue($this->transition(8, IncidentTransitionType::Down));
        $dispatcher->enqueue($this->transition(9, IncidentTransitionType::Down));

        $dispatcher->flush();

        self::assertSame(1, $channel->calls);
        self::assertSame(2, $channel->lastMessage?->context()['combined_count']);
        self::assertSame(['delivered', 'delivered'], array_column($store->deliveries, 'state'));
    }

    private function transition(int $incidentId, IncidentTransitionType $type): IncidentTransition
    {
        return new IncidentTransition($incidentId, $incidentId + 100, $type, new DateTimeImmutable('2026-07-19 12:00:00'));
    }

    private function dispatcher(
        MemoryDeliveryRepository $store,
        FixedRecipientRepository $recipients,
        SequenceDeliveryChannel $first,
        ?SequenceDeliveryChannel $second = null,
        bool $combine = false,
    ): IncidentNotificationDispatcher {
        return new IncidentNotificationDispatcher(
            $store,
            $recipients,
            new FixedMessageComposer(),
            new ChannelRegistry(array_values(array_filter([$first, $second]))),
            $combine,
            static fn (): DateTimeImmutable => new DateTimeImmutable('2026-07-19 12:00:00'),
        );
    }
}

final class MemoryDeliveryRepository extends DeliveryRepository
{
    /** @var list<array<string, mixed>> */
    public array $deliveries = [];
    /** @var list<array<string, mixed>> */
    public array $notifications = [];
    /** @var list<int> */
    public array $retryMinutes = [];

    public function __construct()
    {
    }

    public function enqueueDelivery(IncidentTransition $transition, int $userId, string $channel): void
    {
        $key = $transition->incidentId . ':' . $userId . ':' . $channel . ':' . $transition->type->value;
        foreach ($this->deliveries as $delivery) {
            if ($delivery['unique'] === $key) {
                return;
            }
        }
        $this->deliveries[] = [
            'delivery_id' => count($this->deliveries) + 1,
            'incident_id' => $transition->incidentId,
            'server_id' => $transition->serverId,
            'user_id' => $userId,
            'channel' => $channel,
            'transition' => $transition->type->value,
            'attempts' => 0,
            'state' => 'pending',
            'unique' => $key,
            'last_error' => null,
        ];
    }

    public function enqueueUserNotification(IncidentTransition $transition, int $userId, string $title, string $body): void
    {
        $key = $userId . ':' . $transition->incidentId . ':' . $transition->type->value;
        foreach ($this->notifications as $notification) {
            if ($notification['unique'] === $key) {
                return;
            }
        }
        $this->notifications[] = ['unique' => $key, 'title' => $title, 'body' => $body];
    }

    public function claimDue(int $limit, DateTimeImmutable $now): array
    {
        $claimed = [];
        foreach ($this->deliveries as &$delivery) {
            if ($delivery['state'] !== 'pending' || count($claimed) >= $limit) {
                continue;
            }
            $delivery['state'] = 'sending';
            $delivery['attempts']++;
            $claimed[] = $delivery;
        }
        unset($delivery);

        return $claimed;
    }

    public function markDelivered(array $deliveryIds, DateTimeImmutable $at): void
    {
        $this->setState($deliveryIds, 'delivered');
    }

    public function markPermanentFailure(array $deliveryIds, string $error): void
    {
        $this->setState($deliveryIds, 'permanent_failure', $error);
    }

    public function reschedule(array $deliveryIds, DateTimeImmutable $availableAt, string $error, DateTimeImmutable $now): void
    {
        $this->retryMinutes[] = (int) (($availableAt->getTimestamp() - $now->getTimestamp()) / 60);
        $this->setState($deliveryIds, 'pending', $error);
    }

    /** @param list<int> $ids */
    private function setState(array $ids, string $state, ?string $error = null): void
    {
        foreach ($this->deliveries as &$delivery) {
            if (in_array($delivery['delivery_id'], $ids, true)) {
                $delivery['state'] = $state;
                $delivery['last_error'] = $error;
            }
        }
        unset($delivery);
    }
}

final class FixedRecipientRepository extends IncidentRecipientRepository
{
    /** @param list<IncidentRecipient> $recipients */
    public function __construct(private readonly array $recipients)
    {
    }

    public function forTransition(IncidentTransition $transition): array
    {
        return $this->recipients;
    }

    public function recipient(int $userId): Recipient
    {
        foreach ($this->recipients as $recipient) {
            if ($recipient->recipient->userId() === $userId) {
                return $recipient->recipient;
            }
        }

        return new Recipient($userId);
    }
}

final class FixedMessageComposer extends IncidentMessageComposer
{
    public function __construct()
    {
    }

    public function compose(array $delivery): NotificationMessage
    {
        return new NotificationMessage('Incident', 'Server state changed');
    }

    public function composeMany(array $deliveries): NotificationMessage
    {
        return new NotificationMessage('Incidents', 'Several servers changed', null, true, ['combined_count' => count($deliveries)]);
    }

    public function inApp(IncidentTransition $transition): array
    {
        return ['title' => 'Incident', 'body' => 'Server state changed'];
    }
}

final class SequenceDeliveryChannel implements NotificationChannelInterface
{
    public int $calls = 0;
    public ?NotificationMessage $lastMessage = null;
    /** @var list<DeliveryResult> */
    private array $results;

    /** @param list<DeliveryResult> $results */
    public function __construct(private readonly string $channelName, array $results = [])
    {
        $this->results = $results;
    }

    public function name(): string
    {
        return $this->channelName;
    }

    public function send(NotificationMessage $message, Recipient $recipient): DeliveryResult
    {
        $this->calls++;
        $this->lastMessage = $message;

        return array_shift($this->results) ?? DeliveryResult::success();
    }
}
