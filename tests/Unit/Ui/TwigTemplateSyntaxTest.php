<?php

declare(strict_types=1);

namespace Tests\Unit\Ui;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class TwigTemplateSyntaxTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function modernTemplates(): iterable
    {
        foreach ([
            'main/components.tpl.html',
            'main/app-navbar.tpl.html',
            'main/macros.tpl.html',
            'module/user/login/login.tpl.html',
            'module/user/login/forgot.tpl.html',
            'module/user/login/reset.tpl.html',
            'module/user/user/list.tpl.html',
            'module/user/notification/index.tpl.html',
            'module/server/log.tpl.html',
            'module/server/server/list.tpl.html',
            'module/server/status/index.tpl.html',
            'module/server/status/cards.tpl.html',
            'module/server/server/update.tpl.html',
            'module/server/server/view.tpl.html',
            'module/server/history.tpl.html',
            'module/config/config.tpl.html',
            'module/install/main.tpl.html',
            'module/install/config_new.tpl.html',
            'module/install/index.tpl.html',
            'module/install/config_new_user.tpl.html',
            'module/install/config_upgrade.tpl.html',
            'module/install/results.tpl.html',
            'module/install/success.tpl.html',
        ] as $template) {
            yield $template => [$template];
        }
    }

    #[DataProvider('modernTemplates')]
    public function testModernTemplateCompiles(string $template): void
    {
        $loader = new FilesystemLoader(dirname(__DIR__, 3) . '/src/templates/default');
        $twig = new Environment($loader);
        $twig->addFunction(new TwigFunction('csrf_token', static fn (string $key = ''): string => $key));

        self::assertNotNull($twig->load($template));
    }
}
