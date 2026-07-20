<?php

declare(strict_types=1);

namespace psm\Notification;

use psm\Notification\Channel\DiscordChannel;
use psm\Notification\Channel\EmailChannel;
use psm\Notification\Channel\PushoverChannel;
use psm\Notification\Channel\SmsChannel;
use psm\Notification\Channel\TelegramChannel;
use psm\Notification\Channel\WebhookChannel;
use psm\Notification\Channel\WebPushChannel;
use psm\Notification\Http\RetryingHttpTransport;
use psm\Service\Push\PushSubscriptionRepository;
use Symfony\Component\HttpClient\HttpClient;
use Throwable;

final class ChannelRegistryFactory
{
    public static function create(?PushSubscriptionRepository $pushSubscriptions = null): ChannelRegistry
    {
        $transport = new RetryingHttpTransport(HttpClient::create(['timeout' => 30]), 3);
        $channels = [
            new EmailChannel(),
            new SmsChannel(),
            new DiscordChannel($transport),
            new WebhookChannel($transport),
            new PushoverChannel($transport, (string) psm_get_conf('pushover_api_token')),
            new TelegramChannel($transport, (string) psm_get_conf('telegram_api_token')),
        ];
        if ($pushSubscriptions !== null) {
            $privateKey = '';
            try {
                $decrypted = psm_password_decrypt(
                    (string) psm_get_conf('password_encrypt_key'),
                    (string) psm_get_conf('webpush_vapid_private_key'),
                );
                $privateKey = $decrypted;
            } catch (Throwable) {
                $privateKey = '';
            }
            $channels[] = new WebPushChannel($pushSubscriptions, [
                'subject' => (string) psm_get_conf('webpush_vapid_subject'),
                'publicKey' => (string) psm_get_conf('webpush_vapid_public_key'),
                'privateKey' => $privateKey,
            ], null, psm_build_url([], true, false));
        }

        return new ChannelRegistry($channels);
    }
}
