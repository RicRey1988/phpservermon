<?php

declare(strict_types=1);

namespace Tests\Unit\Ui;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class CompositeTemplateRenderingTest extends TestCase
{
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
        self::assertStringContainsString('fas fa-info-circle', $html);
        self::assertStringContainsString('Monitoreo', $html);
        self::assertStringContainsString('bg-success">Sí', $html);
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
