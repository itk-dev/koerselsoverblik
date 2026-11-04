<?php

namespace App\Tests\Unit\Menu;

use App\Menu\MainMenu;
use App\Menu\MenuBuilder;
use App\Menu\MenuItem;
use App\Menu\MenuLink;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class MenuBuilderTest extends TestCase
{
    public function testItemWithAMissingRouteIsHidden(): void
    {
        $builder = $this->builder(routes: [], grantedRoles: ['ROLE_VIEW_OVERVIEW']);

        $links = $builder->build([new MenuItem('menu.overview', 'overview', 'ROLE_VIEW_OVERVIEW', 'dashboard')]);

        self::assertSame([], $links);
    }

    public function testItemIsShownWithTheGeneratedUrlWhenTheRoleIsGranted(): void
    {
        $builder = $this->builder(routes: ['overview'], grantedRoles: ['ROLE_VIEW_OVERVIEW']);

        $links = $builder->build([new MenuItem('menu.overview', 'overview', 'ROLE_VIEW_OVERVIEW', 'dashboard')]);

        self::assertEquals([new MenuLink('menu.overview', 'overview', '/overview', 'dashboard')], $links);
    }

    public function testItemIsHiddenWhenTheRoleIsNotGranted(): void
    {
        $builder = $this->builder(routes: ['overview'], grantedRoles: []);

        $links = $builder->build([new MenuItem('menu.overview', 'overview', 'ROLE_VIEW_OVERVIEW', 'dashboard')]);

        self::assertSame([], $links);
    }

    public function testMainMenuShowsDailyCountsToAUserWithItsRole(): void
    {
        $builder = $this->builder(
            routes: ['overview', 'daily_counts'],
            grantedRoles: ['ROLE_VIEW_OVERVIEW', 'ROLE_EDIT_DAILY_COUNTS'],
        );

        self::assertSame(['menu.overview', 'menu.daily_counts'], $this->labels($builder->build(MainMenu::items())));
    }

    public function testMainMenuShowsEveryItemWhenAllRoutesExistAndAllRolesAreGranted(): void
    {
        $routes = ['overview', 'daily_counts', 'supplier_index'];
        $roles = ['ROLE_VIEW_OVERVIEW', 'ROLE_EDIT_DAILY_COUNTS', 'ROLE_MANAGE_SUPPLIERS'];
        $builder = $this->builder(routes: $routes, grantedRoles: $roles);

        self::assertSame(
            ['menu.overview', 'menu.daily_counts', 'menu.suppliers'],
            $this->labels($builder->build(MainMenu::items())),
        );
    }

    public function testMainMenuShowsOnlyTheOverviewToAViewer(): void
    {
        $routes = ['overview', 'daily_counts', 'supplier_index'];
        $builder = $this->builder(routes: $routes, grantedRoles: ['ROLE_VIEW_OVERVIEW']);

        self::assertSame(['menu.overview'], $this->labels($builder->build(MainMenu::items())));
    }

    /**
     * @param list<MenuLink> $links
     *
     * @return list<string>
     */
    private function labels(array $links): array
    {
        return array_map(static fn (MenuLink $link): string => $link->label, $links);
    }

    /**
     * @param list<string> $routes
     * @param list<string> $grantedRoles
     */
    private function builder(array $routes, array $grantedRoles): MenuBuilder
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $name): string => in_array($name, $routes, true)
                ? '/'.$name
                : throw new RouteNotFoundException(sprintf('No route "%s".', $name)),
        );

        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturnCallback(
            static fn (mixed $attribute): bool => in_array($attribute, $grantedRoles, true),
        );

        return new MenuBuilder($urlGenerator, $authorizationChecker);
    }
}
