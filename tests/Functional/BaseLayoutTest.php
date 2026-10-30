<?php

namespace App\Tests\Functional;

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

    public function testViewerSeesOnlyTheOverviewInTheMenu(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'viewer');

        $crawler = $client->request('GET', '/');

        $items = $crawler->filter('[data-test="menu-item"]');
        self::assertCount(1, $items);
        self::assertSame('Overblik', trim($items->text()));
    }

    public function testAdminSeesEveryItemWithARoute(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'admin');

        $crawler = $client->request('GET', '/');

        // Only the overview route exists now. The later tasks add the other routes.
        self::assertCount(1, $crawler->filter('[data-test="menu-item"]'));
    }

    public function testActiveMenuItemIsMarked(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'viewer');

        $crawler = $client->request('GET', '/');

        $active = $crawler->filter('[data-test="menu-item"][aria-current="page"]');
        self::assertCount(1, $active);
        self::assertSame('Overblik', trim($active->text()));
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
