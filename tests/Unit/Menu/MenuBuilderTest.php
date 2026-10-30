<?php

namespace App\Tests\Unit\Menu;

use App\Menu\MainMenu;
use App\Menu\MenuBuilder;
use App\Menu\MenuItem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class MenuBuilderTest extends TestCase
{
    public function testItemWithAMissingRouteIsHidden(): void
    {
        $builder = $this->builder(routes: [], grantedRoles: ['ROLE_VIEW_OVERVIEW']);

        $items = $builder->build([new MenuItem('menu.overview', 'overview', 'ROLE_VIEW_OVERVIEW')]);

        self::assertSame([], $items);
    }

    public function testLinkWithoutARouteIsHidden(): void
    {
        $builder = $this->builder(routes: ['overview'], grantedRoles: ['ROLE_VIEW_OVERVIEW']);

        $items = $builder->build([new MenuItem('menu.overview', role: 'ROLE_VIEW_OVERVIEW')]);

        self::assertSame([], $items);
    }

    public function testItemIsShownWhenTheRoleIsGranted(): void
    {
        $builder = $this->builder(routes: ['overview'], grantedRoles: ['ROLE_VIEW_OVERVIEW']);
        $item = new MenuItem('menu.overview', 'overview', 'ROLE_VIEW_OVERVIEW');

        self::assertSame([$item], $builder->build([$item]));
    }

    public function testItemIsHiddenWhenTheRoleIsNotGranted(): void
    {
        $builder = $this->builder(routes: ['overview'], grantedRoles: []);

        $items = $builder->build([new MenuItem('menu.overview', 'overview', 'ROLE_VIEW_OVERVIEW')]);

        self::assertSame([], $items);
    }

    public function testGroupShowsOnlyTheVisibleChildren(): void
    {
        $builder = $this->builder(routes: ['suppliers', 'users'], grantedRoles: ['ROLE_MANAGE_USERS']);
        $users = new MenuItem('menu.users', 'users', 'ROLE_MANAGE_USERS');
        $group = new MenuItem('menu.administration', children: [
            new MenuItem('menu.suppliers', 'suppliers', 'ROLE_MANAGE_SUPPLIERS'),
            $users,
        ]);

        $items = $builder->build([$group]);

        self::assertCount(1, $items);
        self::assertSame('menu.administration', $items[0]->label);
        self::assertSame([$users], $items[0]->children);
    }

    public function testGroupIsHiddenWhenNoChildIsVisible(): void
    {
        $builder = $this->builder(routes: ['suppliers'], grantedRoles: []);
        $group = new MenuItem('menu.administration', children: [
            new MenuItem('menu.suppliers', 'suppliers', 'ROLE_MANAGE_SUPPLIERS'),
        ]);

        self::assertSame([], $builder->build([$group]));
    }

    public function testMainMenuShowsDailyCountsToAUserWithItsRole(): void
    {
        $builder = $this->builder(
            routes: ['overview', 'daily_counts'],
            grantedRoles: ['ROLE_VIEW_OVERVIEW', 'ROLE_EDIT_DAILY_COUNTS'],
        );

        $labels = array_map(
            static fn (MenuItem $item): string => $item->label,
            $builder->build(MainMenu::items()),
        );

        self::assertSame(['menu.overview', 'menu.daily_counts'], $labels);
    }

    public function testMainMenuShowsEveryItemWhenAllRoutesExistAndAllRolesAreGranted(): void
    {
        $routes = ['overview', 'clear_driving_data', 'daily_counts', 'admin_supplier_index', 'admin_user_index'];
        $roles = [
            'ROLE_VIEW_OVERVIEW',
            'ROLE_CLEAR_DRIVING_DATA',
            'ROLE_EDIT_DAILY_COUNTS',
            'ROLE_MANAGE_SUPPLIERS',
            'ROLE_MANAGE_USERS',
        ];
        $builder = $this->builder(routes: $routes, grantedRoles: $roles);

        $items = $builder->build(MainMenu::items());

        self::assertSame(
            ['menu.overview', 'menu.clear_driving_data', 'menu.daily_counts', 'menu.administration'],
            array_map(static fn (MenuItem $item): string => $item->label, $items),
        );
        self::assertSame(
            ['menu.suppliers', 'menu.users'],
            array_map(static fn (MenuItem $item): string => $item->label, $items[3]->children),
        );
    }

    public function testMainMenuShowsOnlyTheOverviewToAViewer(): void
    {
        $routes = ['overview', 'clear_driving_data', 'daily_counts', 'admin_supplier_index', 'admin_user_index'];
        $builder = $this->builder(routes: $routes, grantedRoles: ['ROLE_VIEW_OVERVIEW']);

        $labels = array_map(
            static fn (MenuItem $item): string => $item->label,
            $builder->build(MainMenu::items()),
        );

        self::assertSame(['menu.overview'], $labels);
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
