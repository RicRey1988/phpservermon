<?php

declare(strict_types=1);

namespace Tests\Unit\Update;

use PHPUnit\Framework\TestCase;

final class SystemUpdateContractTest extends TestCase
{
    public function testControllerIsAdminPostCsrfAndRequiresTypedTarget(): void
    {
        $root = dirname(__DIR__, 3);
        $controller = file_get_contents($root . '/src/psm/Module/Config/Controller/SystemController.php');
        $template = file_get_contents($root . '/src/templates/default/module/config/system.tpl.html');
        self::assertIsString($controller);
        self::assertIsString($template);

        self::assertStringContainsString('PSM_USER_ADMIN', $controller);
        self::assertStringContainsString("setCSRFKey('system_update')", $controller);
        self::assertStringContainsString("REQUEST_METHOD'] ?? 'GET') !== 'POST'", $controller);
        self::assertStringContainsString("psm_POST('target_version'", $controller);
        self::assertStringContainsString('name="target_version"', $template);
        self::assertStringContainsString('name="confirm_update"', $template);
        self::assertStringNotContainsString('name="download_url"', $template);
    }

    public function testManualWorkflowBuildsAndSignsExactThreeAssets(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 3) . '/.github/workflows/release-hs.yml');
        self::assertIsString($workflow);
        self::assertStringContainsString('workflow_dispatch:', $workflow);
        self::assertStringContainsString('RELEASE_SIGNING_PRIVATE_KEY: ${{ secrets.RELEASE_SIGNING_PRIVATE_KEY }}', $workflow);
        self::assertStringContainsString('phpservermon-$version.zip', $workflow);
        self::assertStringContainsString('phpservermon-$version.json', $workflow);
        self::assertStringContainsString('phpservermon-$version.json.sig', $workflow);
        self::assertStringContainsString('openssl dgst -sha256 -verify', $workflow);
        self::assertLessThan(
            strpos($workflow, 'composer audit'),
            strpos($workflow, 'composer install --no-interaction --prefer-dist'),
            'Release dependencies must be installed before Composer audits installed packages.',
        );
        self::assertStringContainsString('composer check-platform-reqs', $workflow);
    }
}
