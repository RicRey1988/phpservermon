<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class LocalizedDateFormatTest extends TestCase
{
    public function testSpanishLiteralTextIsNotParsedAsPhpDateTokens(): void
    {
        $previous = $GLOBALS['sm_lang'] ?? null;
        $GLOBALS['sm_lang'] = ['locale' => [1 => 'es_ES']];

        try {
            $formatted = formatLanguage('%e de %B de %Y', strtotime('2025-12-07 12:00:00 UTC'));
        } finally {
            if ($previous === null) {
                unset($GLOBALS['sm_lang']);
            } else {
                $GLOBALS['sm_lang'] = $previous;
            }
        }

        self::assertStringContainsString('7 de diciembre de 2025', mb_strtolower($formatted));
        self::assertStringNotContainsString('America/', $formatted);
    }
}
