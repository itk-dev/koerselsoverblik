<?php

namespace App\Menu;

/**
 * A menu item that the current user can see, with its generated URL.
 */
final readonly class MenuLink
{
    /**
     * @param string $label the translation key
     * @param string $route the route name, to find the active item
     * @param string $icon  the icon name in templates/_icons.html.twig
     */
    public function __construct(
        public string $label,
        public string $route,
        public string $url,
        public string $icon,
    ) {
    }
}
