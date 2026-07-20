<?php

declare(strict_types=1);

namespace Tests\Unit\Ui;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FormFieldCoverageTest extends TestCase
{
    /** @return iterable<string, array{string, list<string>}> */
    public static function forms(): iterable
    {
        yield 'server' => ['module/server/server/update.tpl.html', [
            'label', 'ip', 'port', 'timeout', 'type', 'request_method', 'post_field', 'pattern',
            'pattern_online', 'redirect_check', 'allow_http_status', 'header_name', 'header_value',
            'custom_header', 'warning_threshold', 'ssl_cert_expiry_days', 'website_username',
            'website_password', 'active', 'email', 'sms', 'discord', 'webhook', 'pushover',
            'telegram', 'user_id',
            'server_image', 'remove_image',
        ]];
        yield 'user' => ['module/user/user/update.tpl.html', [
            'name', 'user_name', 'password', 'password_repeat', 'level', 'mobile', 'discord',
            'webhook_url', 'webhook_json', 'pushover_key', 'pushover_device', 'telegram_id',
            'email', 'server_id',
        ]];
        yield 'config' => ['module/config/config.tpl.html', [
            'proxy', 'proxy_url', 'proxy_user', 'proxy_password', 'email_status', 'email_add_url',
            'email_smtp', 'email_from_name', 'email_from_email', 'email_smtp_host', 'email_smtp_port',
            'email_smtp_username', 'email_smtp_password', 'email_smtp_security', 'sms_status',
            'sms_gateway', 'sms_gateway_username', 'sms_gateway_password', 'sms_from', 'discord_status',
            'webhook_status', 'webhook_url', 'webhook_json', 'pushover_status', 'pushover_api_token',
            'telegram_status', 'telegram_add_url', 'telegram_api_token', 'log_status', 'show_update',
            'combine_notifications', 'dirauth_status', 'authdir_type', 'language', 'site_title',
            'alert_type', 'auto_refresh_servers', 'log_retention_period', 'password_encrypt_key',
        ]];
    }

    /** @param list<string> $fields */
    #[DataProvider('forms')]
    public function testEveryControllerOwnedFieldRemainsInItsTemplate(string $path, array $fields): void
    {
        $contents = file_get_contents(dirname(__DIR__, 3) . '/src/templates/default/' . $path);
        self::assertIsString($contents);

        foreach ($fields as $field) {
            self::assertMatchesRegularExpression(
                '/["\']' . preg_quote($field, '/') . '(?:\[\])?["\']/',
                $contents,
                sprintf('%s is missing %s', $path, $field)
            );
        }
    }
}
