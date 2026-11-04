<?php

namespace App\Menu;

use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Removes the menu items that the current user must not see, and generates the URLs.
 *
 * An item is visible only if the user has its permission role and its route exists.
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
     * @return list<MenuLink>
     */
    public function build(array $items): array
    {
        $links = [];
        foreach ($items as $item) {
            $link = $this->link($item);
            if (null !== $link) {
                $links[] = $link;
            }
        }

        return $links;
    }

    private function link(MenuItem $item): ?MenuLink
    {
        if (!$this->authorizationChecker->isGranted($item->role)) {
            return null;
        }

        // A later task adds the route. Other routing errors are bugs, so they are not caught.
        try {
            $url = $this->urlGenerator->generate($item->route);
        } catch (RouteNotFoundException) {
            return null;
        }

        return new MenuLink($item->label, $item->route, $url, $item->icon);
    }
}
