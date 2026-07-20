<?php

declare(strict_types=1);

namespace psm\Service\Ui;

final readonly class Appearance
{
    private const SCHEMES = ['auto', 'light', 'dark'];
    private const ACCENTS = ['default', 'blue', 'gray', 'red', 'yellow', 'pink', 'orange', 'purple'];
    private const DIRECTIONS = ['ltr', 'rtl'];
    private const SIDEBARS = ['default', 'dark', 'color', 'transparent'];
    private const SIDEBAR_TYPES = ['mini', 'hover', 'boxed'];
    private const SIDEBAR_ACTIVE = ['rounded-one-side', 'rounded-all', 'pill-one-side', 'pill-all'];
    private const NAVBARS = ['default', 'glass', 'color', 'sticky', 'transparent'];

    private function __construct(
        public string $scheme,
        public string $accent,
        public string $direction,
        public string $sidebar,
        /** @var list<string> */
        public array $sidebarTypes,
        public string $sidebarActive,
        public string $navbar,
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
            self::allowedList($values['ui_sidebar_types'] ?? null, self::SIDEBAR_TYPES),
            self::allowed($values['ui_sidebar_active'] ?? null, self::SIDEBAR_ACTIVE, 'rounded-one-side'),
            self::allowed($values['ui_navbar'] ?? null, self::NAVBARS, 'default'),
        );
    }

    public function resolvedScheme(): string
    {
        return $this->scheme === 'dark' ? 'dark' : 'light';
    }

    /** @return array{scheme: string, resolved_scheme: string, accent: string, direction: string, sidebar: string, sidebar_types: list<string>, sidebar_active: string, navbar: string, body_classes: string, sidebar_classes: string, navbar_classes: string} */
    public function toArray(): array
    {
        return [
            'scheme' => $this->scheme,
            'resolved_scheme' => $this->resolvedScheme(),
            'accent' => $this->accent,
            'direction' => $this->direction,
            'sidebar' => $this->sidebar,
            'sidebar_types' => $this->sidebarTypes,
            'sidebar_active' => $this->sidebarActive,
            'navbar' => $this->navbar,
            'body_classes' => implode(' ', array_filter([
                $this->scheme === 'auto' ? 'auto' : null,
                $this->resolvedScheme() === 'dark' ? 'dark' : null,
                $this->accent === 'default' ? 'theme-color-default' : 'theme-color-' . $this->accent,
            ])),
            'sidebar_classes' => implode(' ', array_merge(
                [$this->sidebarColorClass(), $this->sidebarActiveClass()],
                array_map(static fn (string $type): string => 'sidebar-' . $type, $this->sidebarTypes),
            )),
            'navbar_classes' => $this->navbarClass(),
        ];
    }

    /** @param list<string> $allowed */
    private static function allowed(mixed $value, array $allowed, string $default): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
    }

    /** @param list<string> $allowed @return list<string> */
    private static function allowedList(mixed $value, array $allowed): array
    {
        $values = is_string($value) ? explode(',', $value) : (is_array($value) ? $value : []);
        $normalized = [];
        foreach ($values as $item) {
            if (is_string($item) && in_array($item, $allowed, true) && !in_array($item, $normalized, true)) {
                $normalized[] = $item;
            }
        }
        return $normalized;
    }

    private function sidebarActiveClass(): string
    {
        return match ($this->sidebarActive) {
            'rounded-all' => 'navs-rounded-all',
            'pill-one-side' => 'navs-pill',
            'pill-all' => 'navs-pill-all',
            default => 'navs-rounded',
        };
    }

    private function sidebarColorClass(): string
    {
        return match ($this->sidebar) {
            'dark' => 'sidebar-dark',
            'color' => 'sidebar-color',
            'transparent' => 'sidebar-transparent',
            default => 'sidebar-white',
        };
    }

    private function navbarClass(): string
    {
        return match ($this->navbar) {
            'glass' => 'nav-glass',
            'sticky' => 'navs-sticky',
            'transparent' => 'navs-transparent',
            default => '',
        };
    }
}
