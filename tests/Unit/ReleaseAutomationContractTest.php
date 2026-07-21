<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ReleaseAutomationContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testWorkflowsContinuouslyValidateGitHubActionsSyntax(): void
    {
        $workflow = $this->read('.github/workflows/php85.yml');

        self::assertStringContainsString('download-actionlint.bash) 1.7.12', $workflow);
        self::assertStringContainsString('actionlint -color -shellcheck= -pyflakes=', $workflow);
    }

    public function testReleaseAndDeploymentAutomationTargetsCurrentVersion(): void
    {
        $release = $this->read('.github/workflows/release-hs.yml');
        $deployment = $this->read('.github/workflows/vps-ops-chatops.yml');

        self::assertStringContainsString('default: v4.3.3-hs', $release);
        self::assertStringContainsString('VERSION: 4.3.3-hs', $deployment);
        self::assertStringContainsString('psm-static-4.3.3-hs-r1', $deployment);
        self::assertStringNotContainsString('VERSION: 4.3.2-hs', $deployment);
        self::assertDoesNotMatchRegularExpression(
            '/(?:^|\R)REMOTE(?:\R|$)/',
            $release . $deployment . $this->read('.github/workflows/php85.yml'),
        );
        self::assertStringContainsString('mariadb-dump --single-transaction', $deployment);
        self::assertStringContainsString('php bin/psm migrate', $deployment);
        self::assertStringContainsString('php bin/psm health', $deployment);
        self::assertStringContainsString('test "$HEALTH_CODE" -eq 0', $deployment);
        self::assertStringContainsString('database_rollback_completed=1', $deployment);
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($this->root . '/' . $path);
        self::assertIsString($contents);

        return $contents;
    }
}
