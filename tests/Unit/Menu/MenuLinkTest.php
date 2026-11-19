<?php

declare(strict_types=1);

namespace App\Tests\Unit\Menu;

use App\Menu\MenuLink;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MenuLinkTest extends TestCase
{
    #[DataProvider('routeProvider')]
    public function testIsActiveOnTheRoutesOfItsSections(?string $currentRoute, bool $expected): void
    {
        $link = new MenuLink('menu.suppliers', 'admin_supplier_index', '/admin/suppliers', 'lucide:car', ['admin_supplier', 'admin_sub_agreement']);

        self::assertSame($expected, $link->isActive($currentRoute));
    }

    /**
     * @return iterable<string, array{?string, bool}>
     */
    public static function routeProvider(): iterable
    {
        yield 'the route of the item' => ['admin_supplier_index', true];
        yield 'another route of the section' => ['admin_supplier_edit', true];
        yield 'a route of the second section' => ['admin_sub_agreement_show', true];
        yield 'a section name only' => ['admin_supplier', true];
        yield 'a longer name is not the section' => ['admin_suppliers_index', false];
        yield 'another section' => ['admin_import', false];
        yield 'no route' => [null, false];
    }

    public function testWithNoSectionsTheRouteIsTheSection(): void
    {
        $link = new MenuLink('menu.upload', 'admin_import', '/admin/import', 'lucide:upload');

        self::assertTrue($link->isActive('admin_import'));
        self::assertTrue($link->isActive('admin_import_bulk'));
        self::assertFalse($link->isActive('admin_supplier_index'));
    }
}
