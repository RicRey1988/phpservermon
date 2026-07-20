<?php

declare(strict_types=1);

namespace Tests\Unit\Util\Install;

use PHPUnit\Framework\TestCase;
use psm\Util\Install\PlatformRequirements;

final class PlatformRequirementsTest extends TestCase
{
    public function testRejectsPhpBefore85(): void
    {
        $requirements = new PlatformRequirements('8.4.9', PlatformRequirements::REQUIRED_EXTENSIONS);

        self::assertFalse($requirements->isSatisfied());
    }

    public function testReportsMissingExtension(): void
    {
        $extensions = array_values(array_diff(PlatformRequirements::REQUIRED_EXTENSIONS, ['pdo_mysql']));
        $requirements = new PlatformRequirements('8.5.0', $extensions);

        self::assertContains('pdo_mysql', $requirements->missingExtensions());
    }

    public function testRequiresIntlForLocalizedDateFormatting(): void
    {
        self::assertContains('intl', PlatformRequirements::REQUIRED_EXTENSIONS);

        $extensions = array_values(array_diff(PlatformRequirements::REQUIRED_EXTENSIONS, ['intl']));
        $requirements = new PlatformRequirements('8.5.0', $extensions);

        self::assertContains('intl', $requirements->missingExtensions());
        self::assertFalse($requirements->isSatisfied());
    }

    public function testRequiresZipForSignedUpdates(): void
    {
        self::assertContains('zip', PlatformRequirements::REQUIRED_EXTENSIONS);
        $extensions = array_values(array_diff(PlatformRequirements::REQUIRED_EXTENSIONS, ['zip']));
        self::assertContains('zip', (new PlatformRequirements('8.5.0', $extensions))->missingExtensions());
    }

    public function testEvaluateReturnsAConsistentResult(): void
    {
        $requirements = new PlatformRequirements('8.5.0', PlatformRequirements::REQUIRED_EXTENSIONS);

        self::assertSame([
            'php_version' => '8.5.0',
            'php_supported' => true,
            'required_extensions' => PlatformRequirements::REQUIRED_EXTENSIONS,
            'missing_extensions' => [],
            'satisfied' => true,
        ], $requirements->evaluate());
    }

    public function testRequiresGdWithWebpSupportForServerImages(): void
    {
        self::assertContains('gd', PlatformRequirements::REQUIRED_EXTENSIONS);

        $extensions = array_values(array_diff(PlatformRequirements::REQUIRED_EXTENSIONS, ['gd']));
        $requirements = new PlatformRequirements('8.5.0', $extensions);

        self::assertContains('gd', $requirements->missingExtensions());
        self::assertFalse($requirements->isSatisfied());

        $source = file_get_contents(dirname(__DIR__, 4) . '/src/psm/Util/Install/PlatformRequirements.php');
        self::assertIsString($source);
        self::assertStringContainsString("function_exists('imagewebp')", $source);
    }
}
