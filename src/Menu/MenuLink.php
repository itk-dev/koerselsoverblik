<?php

declare(strict_types=1);

namespace App\Menu;

/**
 * A menu item that the current user can see, with its generated URL.
 */
final readonly class MenuLink
{
    /**
     * @param string $label the translation key
     * @param string $route the route name, to find the active item
     * @param string $icon  the UX Icons name, for example lucide:car
     */
    public function __construct(
        public string $label,
        public string $route,
        public string $url,
        public string $icon,
    ) {
    }
}
