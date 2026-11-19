<?php

declare(strict_types=1);

namespace App\Menu;

/**
 * The definition of an item in the menu. The route takes no parameters.
 */
final readonly class MenuItem
{
    /**
     * @param string       $label    the translation key
     * @param string       $role     the permission role that the user must have
     * @param string       $icon     the UX Icons name, for example lucide:car
     * @param list<string> $sections the route name prefixes of the pages that mark the item. Empty means the route
     */
    public function __construct(
        public string $label,
        public string $route,
        public string $role,
        public string $icon,
        public array $sections = [],
    ) {
    }
}
