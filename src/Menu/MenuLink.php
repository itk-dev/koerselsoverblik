<?php

declare(strict_types=1);

namespace App\Menu;

/**
 * A menu item that the current user can see, with its generated URL.
 */
final readonly class MenuLink
{
    /**
     * @param string       $label    the translation key
     * @param string       $route    the route name of the link
     * @param string       $icon     the UX Icons name, for example lucide:car
     * @param list<string> $sections the route name prefixes of the pages that mark the item. Empty means the route
     */
    public function __construct(
        public string $label,
        public string $route,
        public string $url,
        public string $icon,
        public array $sections = [],
    ) {
    }

    /**
     * Tells if the current page is in a section of the item, for example a tab or a sub-page. A
     * section "admin_import" has the routes "admin_import" and "admin_import_*".
     */
    public function isActive(?string $currentRoute): bool
    {
        foreach ([] === $this->sections ? [$this->route] : $this->sections as $section) {
            if ($currentRoute === $section || str_starts_with((string) $currentRoute, $section.'_')) {
                return true;
            }
        }

        return false;
    }
}
