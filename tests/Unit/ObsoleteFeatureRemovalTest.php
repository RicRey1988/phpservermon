<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ObsoleteFeatureRemovalTest extends TestCase
{
    public function testRuntimeSourceDoesNotReferenceJabber(): void
    {
        $root = dirname(__DIR__, 2);
        $paths = [
            $root . '/src/includes',
            $root . '/src/psm',
            $root . '/src/templates/default/module',
        ];

        foreach ($paths as $path) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                if ($file->isFile() && preg_match('/\.(php|html)$/', $file->getFilename())) {
                    self::assertDoesNotMatchRegularExpression('/jabber|JAXL/i', file_get_contents($file->getPathname()));
                }
            }
        }
    }
}
