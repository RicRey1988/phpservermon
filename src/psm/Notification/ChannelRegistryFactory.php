<?php

declare(strict_types=1);

namespace psm\Notification;

use psm\Notification\Channel\DiscordChannel;
use psm\Notification\Channel\EmailChannel;
use psm\Notification\Channel\PushoverChannel;
use psm\Notification\Channel\SmsChannel;
use psm\Notification\Channel\TelegramChannel;
use psm\Notification\Channel\WebhookChannel;
use psm\Notification\Http\RetryingHttpTransport;
use Symfony\Component\HttpClient\HttpClient;

final class ChannelRegistryFactory
{
    public static function create(): ChannelRegistry
    {
        $transport = new RetryingHttpTransport(HttpClient::create(['timeout' => 30]), 3);

        return new ChannelRegistry([
            new EmailChannel(),
            new SmsChannel(),
            new DiscordChannel($transport),
            new WebhookChannel($transport),
            new PushoverChannel($transport, (string) psm_get_conf('pushover_api_token')),
            new TelegramChannel($transport, (string) psm_get_conf('telegram_api_token')),
        ]);
    }
}
