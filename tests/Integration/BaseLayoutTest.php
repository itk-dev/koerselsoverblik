<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Twig\Environment;

/**
 * Renders the base layout for a user who can get no page yet. A functional test cannot do
 * this, because such a user gets a 403 on every page.
 */
final class BaseLayoutTest extends KernelTestCase
{
    public function testBrandIsNotALinkWhenTheUserSeesNoMenuItem(): void
    {
        $crawler = new Crawler($this->render([]));

        self::assertCount(1, $crawler->filter('[data-test="brand"]'));
        self::assertCount(0, $crawler->filter('a[data-test="brand"]'));
        self::assertCount(0, $crawler->filter('[data-test="menu-item"]'));
    }

    /**
     * @param list<string> $roles
     */
    private function render(array $roles): string
    {
        $container = static::getContainer();

        $user = new User('user@example.com');
        $user->setRoles($roles);

        $tokenStorage = $container->get(TokenStorageInterface::class);
        self::assertInstanceOf(TokenStorageInterface::class, $tokenStorage);
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $requestStack = $container->get(RequestStack::class);
        self::assertInstanceOf(RequestStack::class, $requestStack);
        $requestStack->push(Request::create('/'));

        $twig = $container->get(Environment::class);
        self::assertInstanceOf(Environment::class, $twig);

        return $twig->render('base.html.twig');
    }
}
