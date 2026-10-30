<?php

namespace App\Menu;

/**
 * An item in the menu. A link has a route and a permission role. A group has children only.
 */
final readonly class MenuItem
{
    /**
     * @param string         $label    the translation key
     * @param list<MenuItem> $children
     * @param string|null    $icon     the icon name in templates/_icons.html.twig
     */
    public function __construct(
        public string $label,
        public ?string $route = null,
        public ?string $role = null,
        public array $children = [],
        public ?string $icon = null,
    ) {
    }
}
