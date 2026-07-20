<?php

declare(strict_types=1);

namespace Tests\Unit\User;

use PHPUnit\Framework\TestCase;
use psm\Service\User;

final class UserPreferencePersistenceTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!defined('PSM_DB_PREFIX')) {
            define('PSM_DB_PREFIX', 'psm_');
        }
    }

    public function testUpdatingPreferenceTargetsOnlyItsCompositePrimaryKey(): void
    {
        $connection = new RecordingPreferenceConnection();
        $user = new PreferenceTestUser($connection, 7, [
            'status_layout' => 'grid',
            'ui_scheme' => 'light',
        ]);

        $user->setUserPref('ui_scheme', 'dark');

        self::assertSame(
            'UPDATE `psm_users_preferences` SET `value` = ? WHERE `user_id` = ? AND `key` = ?',
            $connection->statement->sql
        );
        self::assertSame(['dark', 7, 'ui_scheme'], $connection->statement->parameters);
    }
}

final class PreferenceTestUser extends User
{
    /** @param array<string, string> $preferences */
    public function __construct(object $connection, int $userId, array $preferences)
    {
        $this->db_connection = $connection;
        $this->user_id = $userId;
        $this->user_preferences = $preferences;
    }
}

final class RecordingPreferenceConnection
{
    public RecordingPreferenceStatement $statement;

    public function prepare(string $sql): RecordingPreferenceStatement
    {
        $this->statement = new RecordingPreferenceStatement($sql);

        return $this->statement;
    }
}

final class RecordingPreferenceStatement
{
    /** @var list<mixed> */
    public array $parameters = [];

    public function __construct(public readonly string $sql)
    {
    }

    /** @param list<mixed> $parameters */
    public function execute(array $parameters): bool
    {
        $this->parameters = $parameters;

        return true;
    }
}
