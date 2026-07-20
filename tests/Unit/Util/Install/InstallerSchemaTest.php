<?php

declare(strict_types=1);

namespace Tests\Unit\Util\Install;

use PHPUnit\Framework\TestCase;

final class InstallerSchemaTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $source = file_get_contents(dirname(__DIR__, 4) . '/src/psm/Util/Install/Installer.php');

        self::assertIsString($source);
        $this->source = $source;
    }

    public function testFreshInstallContainsHsFieldsAndNoJabber(): void
    {
        $installSource = strstr($this->source, 'public function upgrade(', true);

        self::assertIsString($installSource);
        self::assertStringContainsString('`label` varchar(255)', $installSource);
        self::assertStringContainsString('`custom_header` TEXT', $installSource);
        self::assertStringContainsString('`image_file` VARCHAR(255) NULL', $installSource);
        self::assertStringContainsString('`image_updated_at` DATETIME NULL', $installSource);
        self::assertStringContainsString("('log_discord', '1')", $installSource);
        self::assertStringNotContainsString('jabber', strtolower($installSource));
        self::assertStringNotContainsString('log_jdiscord', $installSource);
    }

    public function test400HsMigrationIsAdditiveAndRunsBeforeVersionUpdate(): void
    {
        self::assertStringContainsString('protected function upgrade400hs()', $this->source);

        $migrationStart = strpos($this->source, 'protected function upgrade400hs()');
        self::assertIsInt($migrationStart);
        $migrationSource = substr($this->source, $migrationStart);

        self::assertDoesNotMatchRegularExpression('/\b(?:DROP|DELETE|TRUNCATE)\b/i', $migrationSource);

        $upgradeStart = strpos($this->source, 'public function upgrade(');
        $upgradeEnd = strpos($this->source, '/**', $upgradeStart + 1);
        $upgradeSource = substr($this->source, $upgradeStart, $upgradeEnd - $upgradeStart);

        self::assertStringContainsString('$this->upgrade400hs();', $upgradeSource);
        self::assertGreaterThan(
            strpos($upgradeSource, '$this->upgrade400hs();'),
            strpos($upgradeSource, "psm_update_conf('version', \$version_to);")
        );
    }

    public function test410HsAddsServerImagesIdempotently(): void
    {
        self::assertStringContainsString("version_compare(\$version_from, '4.1.0-hs', '<')", $this->source);
        self::assertStringContainsString('protected function upgrade410hs()', $this->source);

        $migrationStart = strpos($this->source, 'protected function upgrade410hs()');
        self::assertIsInt($migrationStart);
        $migrationSource = substr($this->source, $migrationStart);

        self::assertStringContainsString("addColumnIfMissing('servers', 'image_file'", $migrationSource);
        self::assertStringContainsString("addColumnIfMissing('servers', 'image_updated_at'", $migrationSource);
        self::assertDoesNotMatchRegularExpression('/\b(?:DROP|DELETE|TRUNCATE)\b/i', $migrationSource);
    }

    public function testFreshAndUpgradeSchemasContainIncidentDeliveryNotificationAndPushTables(): void
    {
        $installSource = strstr($this->source, 'public function upgrade(', true);
        self::assertIsString($installSource);

        foreach (['incidents', 'notification_deliveries', 'user_notifications', 'push_subscriptions', 'user_invitations', 'job_runs'] as $table) {
            self::assertStringContainsString("PSM_DB_PREFIX . '" . $table . "'", $installSource);
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `" . PSM_DB_PREFIX . "' . $table, $installSource);
        }
        foreach ([
            'UNIQUE KEY `incident_delivery`',
            'UNIQUE KEY `user_incident_transition`',
            'UNIQUE KEY `endpoint_hash`',
            'KEY `server_state`',
        ] as $index) {
            self::assertStringContainsString($index, $installSource);
        }

        $migrationStart = strpos($this->source, 'protected function upgrade410hs()');
        self::assertIsInt($migrationStart);
        $migrationSource = substr($this->source, $migrationStart);
        foreach (['incidents', 'notification_deliveries', 'user_notifications', 'push_subscriptions', 'user_invitations', 'job_runs'] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `" . PSM_DB_PREFIX . "' . $table, $migrationSource);
        }
        foreach (['webpush_status', 'webpush_vapid_subject', 'webpush_vapid_public_key', 'webpush_vapid_private_key'] as $key) {
            self::assertStringContainsString("('" . $key . "'", $this->source);
        }
    }
}
