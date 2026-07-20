<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DocumentationContractTest extends TestCase
{
    #[DataProvider('requiredReadmeTopics')]
    public function testReadmeDocumentsTheHsProduct(string $topic): void
    {
        $readme = file_get_contents(dirname(__DIR__, 2) . '/README.rst');

        self::assertIsString($readme);
        self::assertStringContainsString($topic, $readme);
    }

    /** @return iterable<string, array{string}> */
    public static function requiredReadmeTopics(): iterable
    {
        yield 'version' => ['4.2.1-hs'];
        yield 'php' => ['PHP 8.5'];
        yield 'images' => ['drag and drop'];
        yield 'cards' => ['Hope UI'];
        yield 'hope-version' => ['Hope UI 2.0'];
        yield 'quick-theme' => ['quick theme toggle'];
        yield 'settings-gear' => ['settings gear'];
        yield 'separate-statistics' => ['separate Statistics'];
        yield 'responsive-widths' => ['360, 390, 768, 1024, 1366 and 1600'];
        yield 'no-datatables' => ['DataTables is not loaded'];
        yield 'statistics' => ['statistics'];
        yield 'pwa' => ['PWA'];
        yield 'web-push' => ['VAPID'];
        yield 'incidents' => ['incident'];
        yield 'cron' => ['cron'];
        yield 'invitations' => ['invitation'];
        yield 'diagnostics' => ['diagnostics'];
        yield 'updater' => ['signed updater'];
        yield 'release' => ['releases/tag/v4.2.1-hs'];
        yield 'fork' => ['RicRey1988/phpservermon'];
        yield 'gd' => ['ext-gd'];
        yield 'zip' => ['ext-zip'];
    }

    public function testReadmeDescribesThePublishedRelease(): void
    {
        $readme = file_get_contents(dirname(__DIR__, 2) . '/README.rst');

        self::assertIsString($readme);
        self::assertStringNotContainsString('release package not published yet', $readme);
        self::assertStringNotContainsString('not yet offered by that updater', $readme);
    }

    public function testRuntimeReferencesUseTheHsRepository(): void
    {
        $files = [
            dirname(__DIR__, 2) . '/src/includes/functions.inc.php',
            dirname(__DIR__, 2) . '/src/psm/Module/Config/Controller/ConfigController.php',
        ];
        $languageFiles = glob(dirname(__DIR__, 2) . '/src/lang/*.lang.php');
        self::assertIsArray($languageFiles);
        $files = array_merge($files, $languageFiles);

        foreach ($files as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source);
            self::assertStringNotContainsString('github.com/phpservermon/phpservermon', $source);
        }

        $runtimeSource = file_get_contents($files[0]);
        self::assertIsString($runtimeSource);
        self::assertStringContainsString('github.com/RicRey1988/phpservermon', $runtimeSource);
    }

}
