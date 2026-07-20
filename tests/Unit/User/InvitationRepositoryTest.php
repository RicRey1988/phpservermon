<?php

declare(strict_types=1);

namespace Tests\Unit\User;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use psm\Service\Database;
use psm\Service\Invitation\InvitationRepository;

final class InvitationRepositoryTest extends TestCase
{
    public function testCreatesOnlyAHashAndReturnsOneTimeRawToken(): void
    {
        $database = new InvitationDatabase();
        $repository = new InvitationRepository($database, static fn (): string => str_repeat("\x01", 32));

        $token = $repository->create(' New.User@Example.Test ', 20, 4, new DateTimeImmutable('+1 day'));

        self::assertSame(rtrim(strtr(base64_encode(str_repeat("\x01", 32)), '+/', '-_'), '='), $token);
        self::assertSame('new.user@example.test', $database->queries[0][1]['email']);
        self::assertSame(hash('sha256', $token), $database->queries[0][1]['token_hash']);
        self::assertStringNotContainsString($token, json_encode($database->queries, JSON_THROW_ON_ERROR));
    }

    public function testLookupAndAcceptanceAreHashBasedAndSingleUse(): void
    {
        $database = new InvitationDatabase([
            ['invitation_id' => '9', 'email' => 'person@example.test', 'level' => '20'],
        ]);
        $repository = new InvitationRepository($database);

        $rawToken = str_repeat('a', 43);
        self::assertSame(9, $repository->findValid($rawToken)['invitation_id']);
        $repository->markAccepted(9, new DateTimeImmutable('2026-07-20 11:00:00'));

        self::assertStringContainsString('accepted_at IS NULL', $database->queries[0][0]);
        self::assertSame(hash('sha256', $rawToken), $database->queries[0][1]['token_hash']);
        self::assertStringContainsString('accepted_at IS NULL', $database->queries[1][0]);
    }
}

final class InvitationDatabase extends Database
{
    /** @var list<array{0:string,1:array<string, mixed>}> */
    public array $queries = [];

    /** @param list<array<string, string>> $selectRows */
    public function __construct(private array $selectRows = [])
    {
    }

    public function execute($query, $parameters, $fetch = true)
    {
        $this->queries[] = [(string) $query, $parameters];
        if (str_starts_with(ltrim((string) $query), 'SELECT')) {
            $rows = $this->selectRows;
            $this->selectRows = [];
            return $rows;
        }
        return [];
    }
}
