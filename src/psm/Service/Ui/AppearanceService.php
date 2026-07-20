<?php

declare(strict_types=1);

namespace psm\Service\Ui;

use psm\Service\User;

final readonly class AppearanceService
{
    public function __construct(private User $user)
    {
    }

    public function forCurrentUser(): Appearance
    {
        return Appearance::fromPreferences([
            'ui_scheme' => $this->user->getUserPref('ui_scheme', 'auto'),
            'ui_accent' => $this->user->getUserPref('ui_accent', 'blue'),
            'ui_direction' => $this->user->getUserPref('ui_direction', 'ltr'),
            'ui_sidebar' => $this->user->getUserPref('ui_sidebar', 'default'),
            'ui_sidebar_types' => $this->user->getUserPref('ui_sidebar_types', ''),
            'ui_sidebar_active' => $this->user->getUserPref('ui_sidebar_active', 'rounded-one-side'),
            'ui_navbar' => $this->user->getUserPref('ui_navbar', 'default'),
        ]);
    }

    /** @param array<string, mixed> $values */
    public function saveForCurrentUser(array $values): Appearance
    {
        $appearance = Appearance::fromPreferences($values);
        $this->user->setUserPref('ui_scheme', $appearance->scheme);
        $this->user->setUserPref('ui_accent', $appearance->accent);
        $this->user->setUserPref('ui_direction', $appearance->direction);
        $this->user->setUserPref('ui_sidebar', $appearance->sidebar);
        $this->user->setUserPref('ui_sidebar_types', implode(',', $appearance->sidebarTypes));
        $this->user->setUserPref('ui_sidebar_active', $appearance->sidebarActive);
        $this->user->setUserPref('ui_navbar', $appearance->navbar);

        return $appearance;
    }
}
