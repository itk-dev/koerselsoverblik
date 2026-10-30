<?php

namespace App\Menu;

use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Removes the menu items that the current user must not see.
 *
 * A link is visible only if its route exists and the user has its permission role. A group is
 * visible only if at least one child is visible.
 */
final readonly class MenuBuilder
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /**
     * @param list<MenuItem> $items
     *
     * @return list<MenuItem>
     */
    public function build(array $items): array
    {
        $visible = [];
        foreach ($items as $item) {
            $item = $this->visible($item);
            if (null !== $item) {
                $visible[] = $item;
            }
        }

        return $visible;
    }

    private function visible(MenuItem $item): ?MenuItem
    {
        if ([] !== $item->children) {
            $children = $this->build($item->children);

            return [] === $children
                ? null
                : new MenuItem($item->label, $item->route, $item->role, $children, $item->icon);
        }

        if (null === $item->route || null === $item->role || !$this->routeExists($item->route)) {
            return null;
        }

        return $this->authorizationChecker->isGranted($item->role) ? $item : null;
    }

    private function routeExists(string $route): bool
    {
        try {
            $this->urlGenerator->generate($route);
        } catch (RouteNotFoundException) {
            return false;
        }

        return true;
    }
}
