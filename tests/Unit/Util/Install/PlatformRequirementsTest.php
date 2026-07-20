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
}
