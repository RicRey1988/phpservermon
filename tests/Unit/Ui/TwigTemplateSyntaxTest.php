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
            'main/macros.tpl.html',
            'module/user/login/login.tpl.html',
            'module/user/login/forgot.tpl.html',
            'module/user/login/reset.tpl.html',
            'module/user/user/list.tpl.html',
            'module/server/log.tpl.html',
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
