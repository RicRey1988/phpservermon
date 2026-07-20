<?php

declare(strict_types=1);

namespace Tests\Unit\Notification;

use PHPUnit\Framework\TestCase;
use psm\Notification\ChannelRegistry;
use psm\Notification\DeliveryResult;
use psm\Notification\NotificationChannelInterface;
use psm\Notification\NotificationDispatcher;
use psm\Notification\NotificationMessage;
use psm\Notification\Recipient;

final class StatusNotifierTest extends TestCase
{
    public function testFailureInOneChannelDoesNotStopTheNextChannel(): void
    {
        $first = new RecordingChannel('first', DeliveryResult::temporaryFailure('temporary'));
        $second = new RecordingChannel('second', DeliveryResult::success());
        $dispatcher = new NotificationDispatcher(new ChannelRegistry([$first, $second]));
        $message = new NotificationMessage('Test', 'Body');
        $recipient = new Recipient(42, ['email' => 'person@example.test']);

        $results = $dispatcher->send(['first', 'second'], $message, $recipient);

        self::assertSame(1, $first->calls);
        self::assertSame(1, $second->calls);
        self::assertSame('temporary_failure', $results['first']->status());
        self::assertSame('success', $results['second']->status());
    }
}

final class RecordingChannel implements NotificationChannelInterface
{
    public int $calls = 0;

    public function __construct(private string $channelName, private DeliveryResult $result)
    {
    }

    public function name(): string
    {
        return $this->channelName;
    }

    public function send(NotificationMessage $message, Recipient $recipient): DeliveryResult
    {
        $this->calls++;
        return $this->result;
    }
}
