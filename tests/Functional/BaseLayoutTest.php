<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Checks the shell of the base layout: the language, the menu, the user menu and the dialog.
 */
final class BaseLayoutTest extends WebTestCase
{
    use LoginTrait;

    public function testPageIsInDanish(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'viewer');

        $crawler = $client->request('GET', '/');

        self::assertSame('da', $crawler->filter('html')->attr('lang'));
    }

    public function testViewerSeesOnlyTheFiguresInTheMenu(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'viewer');

        $crawler = $client->request('GET', '/');

        $labels = $crawler->filter('[data-test="menu-item"]')->each(static fn ($item): string => trim($item->text()));
        self::assertSame(['Overblik', 'Rettidighed'], $labels);
    }

    /**
     * The daily counts route does not exist yet. tests/Integration/Menu/MainMenuTest.php checks the
     * admin menu with all routes.
     */
    public function testAdminSeesEveryItemWithARoute(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'admin');

        $crawler = $client->request('GET', '/');

        $labels = $crawler->filter('[data-test="menu-item"]')->each(static fn ($item): string => trim($item->text()));
        self::assertSame(['Overblik', 'Rettidighed', 'Upload kørselsdata', 'Leverandører og delaftaler'], $labels);
    }

    public function testBrandLinksToTheFirstMenuItem(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'viewer');

        $crawler = $client->request('GET', '/');

        self::assertSame('/', $crawler->filter('a[data-test="brand"]')->attr('href'));
    }

    /**
     * Each page of a section marks the menu item of the section, also a tab or a sub-page.
     */
    #[DataProvider('activeItemProvider')]
    public function testActiveMenuItemIsMarked(string $user, string $url, string $label): void
    {
        $client = static::createClient();
        $this->loginAs($client, $user);

        $crawler = $client->request('GET', $url);

        $active = $crawler->filter('[data-test="menu-item"][aria-current="page"]');
        self::assertCount(1, $active);
        self::assertSame($label, trim($active->text()));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function activeItemProvider(): iterable
    {
        yield 'overview' => ['viewer', '/', 'Overblik'];
        yield 'upload of one file' => ['admin', '/admin/import', 'Upload kørselsdata'];
        yield 'bulk upload' => ['admin', '/admin/import/bulk', 'Upload kørselsdata'];
        yield 'suppliers' => ['admin', '/admin/suppliers', 'Leverandører og delaftaler'];
        yield 'sub-agreements' => ['admin', '/admin/sub-agreements', 'Leverandører og delaftaler'];
    }

    public function testUserMenuShowsTheUsername(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'viewer');

        $client->request('GET', '/');

        self::assertSelectorTextContains('[data-test="user-menu"]', 'viewer');
    }

    public function testPageHasTheAboutDialog(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'viewer');

        $client->request('GET', '/');

        self::assertSelectorTextContains('[data-test="about-dialog"]', 'Om tallene');
    }
}
