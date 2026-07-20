<?php

declare(strict_types=1);

namespace Tests\Unit\Util\Error;

use PHPUnit\Framework\TestCase;
use psm\Util\Error\RuntimeErrorHandler;
use RuntimeException;

final class RuntimeErrorHandlerTest extends TestCase
{
    public function testProductionPageShowsReferenceWithoutLeakingExceptionDetails(): void
    {
        $handler = new RuntimeErrorHandler(false);
        $exception = new RuntimeException('Database password=super-secret');

        $page = $handler->renderPage('PSM-TEST-1234', $exception);

        self::assertStringContainsString('PSM-TEST-1234', $page);
        self::assertStringContainsString('No se pudo completar la solicitud', $page);
        self::assertStringNotContainsString('super-secret', $page);
        self::assertStringNotContainsString('RuntimeException', $page);
    }

    public function testLogEntryRedactsCommonCredentialValues(): void
    {
        $handler = new RuntimeErrorHandler(false);
        $exception = new RuntimeException('Webhook token=abc123 password: hidden-value');

        $entry = $handler->formatLogEntry('PSM-TEST-5678', $exception);

        self::assertStringContainsString('PSM-TEST-5678', $entry);
        self::assertStringContainsString(RuntimeException::class, $entry);
        self::assertStringNotContainsString('abc123', $entry);
        self::assertStringNotContainsString('hidden-value', $entry);
        self::assertStringContainsString('[REDACTED]', $entry);
    }
}
