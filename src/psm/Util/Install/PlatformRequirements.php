<?php

declare(strict_types=1);

namespace psm\Util\Install;

final class PlatformRequirements
{
    public const MIN_PHP = '8.5.0';
    public const REQUIRED_EXTENSIONS = [
        'ctype',
        'curl',
        'filter',
        'gd',
        'hash',
        'intl',
        'json',
        'libxml',
        'mbstring',
        'openssl',
        'pdo',
        'pdo_mysql',
        'xml',
    ];

    private string $phpVersion;

    /** @var list<string> */
    private array $extensions;

    /**
     * @param list<string>|null $extensions
     */
    public function __construct(?string $phpVersion = null, ?array $extensions = null)
    {
        $this->phpVersion = $phpVersion ?? PHP_VERSION;
        $this->extensions = array_map('strtolower', $extensions ?? get_loaded_extensions());
    }

    /** @return list<string> */
    public function missingExtensions(): array
    {
        return array_values(array_diff(self::REQUIRED_EXTENSIONS, $this->extensions));
    }

    public function isSatisfied(): bool
    {
        return version_compare($this->phpVersion, self::MIN_PHP, '>=')
            && $this->missingExtensions() === []
            && function_exists('imagewebp');
    }

    /**
     * @return array{
     *     php_version: string,
     *     php_supported: bool,
     *     required_extensions: list<string>,
     *     missing_extensions: list<string>,
     *     satisfied: bool
     * }
     */
    public function evaluate(): array
    {
        return [
            'php_version' => $this->phpVersion,
            'php_supported' => version_compare($this->phpVersion, self::MIN_PHP, '>='),
            'required_extensions' => self::REQUIRED_EXTENSIONS,
            'missing_extensions' => $this->missingExtensions(),
            'satisfied' => $this->isSatisfied(),
        ];
    }
}
