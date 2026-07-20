<?php

declare(strict_types=1);

namespace Tests\Unit\Ui;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class CompositeTemplateRenderingTest extends TestCase
{
    public function testServerEditorRendersEnabledNotificationWarningsWithoutMissingMacros(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $html = $this->twig()->render('module/server/server/update.tpl.html', [
                'edit_server_id' => 0,
                'warning_email' => true,
                'warning_sms' => true,
                'warning_pushover' => true,
                'warning_telegram' => true,
                'warning_discord' => true,
                'warning_webhook' => true,
                'label_warning_email' => 'Email no configurado',
            ]);
        } finally {
            restore_error_handler();
        }

        self::assertStringContainsString('id="edit_server"', $html);
        self::assertStringContainsString('Email no configurado', $html);
        self::assertStringContainsString('class="icon-20', $html);
        self::assertStringNotContainsString('class="hope-icon', $html);
    }

    public function testServerDetailRendersTupleDataAsScalarValues(): void
    {
        $html = $this->twig()->render('module/server/server/view.tpl.html', [
            'status' => 'on',
            'type' => 'service',
            'port' => 443,
            'rtime' => 0.1,
            'active' => 'yes',
            'email' => 'no',
            'sms' => 'no',
            'pushover' => 'no',
            'telegram' => 'yes',
            'discord' => 'no',
            'webhook' => 'no',
            'label_yes' => 'Sí',
            'label_no' => 'No',
            'label_monitoring' => 'Monitoreo',
            'label_email' => 'Email',
            'label_sms' => 'SMS',
            'label_pushover' => 'Pushover',
            'label_telegram' => 'Telegram',
            'label_discord' => 'Discord',
            'label_webhook' => 'Webhook',
            'label_log_title' => 'Registro',
            'label_output' => 'Salida',
            'label_fieldset_monitoring' => 'Notificaciones',
            'label_settings' => 'Ajustes',
            'label_last_output' => 'Última salida',
            'label_last_error_output' => 'Último error',
            'last_output' => 'ok',
            'last_error_output' => '',
        ]);

        self::assertStringContainsString('id="overview-tab"', $html);
        self::assertStringContainsString('class="icon-20', $html);
        self::assertStringNotContainsString('class="hope-icon', $html);
        self::assertStringNotContainsString('fas fa-', $html);
        self::assertStringContainsString('Monitoreo', $html);
        self::assertStringContainsString('badge bg-success">Sí', $html);
        self::assertStringContainsString('id="modal_last_output"', $html);
        self::assertStringNotContainsString('Array', $html);
    }

    public function testAppearanceCustomizerRendersTupleDataAsScalarValues(): void
    {
        $html = $this->twig()->render('main/appearance-customizer.tpl.html', [
            'appearance' => [
                'scheme' => 'light',
                'accent' => 'blue',
                'direction' => 'ltr',
                'sidebar' => 'default',
                'sidebar_types' => ['mini'],
                'sidebar_active' => 'rounded-one-side',
                'navbar' => 'default',
            ],
        ]);

        self::assertStringContainsString('data-value="ltr"', $html);
        self::assertStringContainsString('data-value="mini"', $html);
        self::assertStringContainsString('>LTR</span>', $html);
        self::assertStringContainsString('>Mini</span>', $html);
        self::assertStringNotContainsString('Array', $html);
    }

    private function twig(): Environment
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 3) . '/src/templates/default'));
        $twig->addFunction(new TwigFunction('csrf_token', static fn (string $key = ''): string => $key));

        return $twig;
    }
}
