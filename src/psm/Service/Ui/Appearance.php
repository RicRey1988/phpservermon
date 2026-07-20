<?php

declare(strict_types=1);

namespace psm\Service\Ui;

final readonly class Appearance
{
    private const SCHEMES = ['auto', 'light', 'dark'];
    private const ACCENTS = ['blue', 'orange', 'red', 'purple', 'pink'];
    private const DIRECTIONS = ['ltr', 'rtl'];
    private const SIDEBARS = ['default', 'dark'];

    private function __construct(
        public string $scheme,
        public string $accent,
        public string $direction,
        public string $sidebar,
    ) {
    }

    /** @param array<string, mixed> $values */
    public static function fromPreferences(array $values): self
    {
        return new self(
            self::allowed($values['ui_scheme'] ?? null, self::SCHEMES, 'auto'),
            self::allowed($values['ui_accent'] ?? null, self::ACCENTS, 'blue'),
            self::allowed($values['ui_direction'] ?? null, self::DIRECTIONS, 'ltr'),
            self::allowed($values['ui_sidebar'] ?? null, self::SIDEBARS, 'default'),
        );
    }

    public function resolvedScheme(): string
    {
        return $this->scheme === 'dark' ? 'dark' : 'light';
    }

    /** @return array{scheme: string, resolved_scheme: string, accent: string, direction: string, sidebar: string} */
    public function toArray(): array
    {
        return [
            'scheme' => $this->scheme,
            'resolved_scheme' => $this->resolvedScheme(),
            'accent' => $this->accent,
            'direction' => $this->direction,
            'sidebar' => $this->sidebar,
        ];
    }

    /** @param list<string> $allowed */
    private static function allowed(mixed $value, array $allowed, string $default): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
    }
}
