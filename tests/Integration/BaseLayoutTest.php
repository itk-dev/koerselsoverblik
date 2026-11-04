<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Twig\Environment;

/**
 * Renders the base layout for a user who can get no page yet. A functional test cannot do
 * this, because such a user gets a 403 on every page.
 */
final class BaseLayoutTest extends KernelTestCase
{
    public function testBrandIsNotALinkWhenTheUserSeesNoMenuItem(): void
    {
        $crawler = new Crawler($this->render('user'));

        self::assertCount(1, $crawler->filter('[data-test="brand"]'));
        self::assertCount(0, $crawler->filter('a[data-test="brand"]'));
        self::assertCount(0, $crawler->filter('[data-test="menu-item"]'));
    }

    private function render(string $username): string
    {
        $container = static::getContainer();

        $provider = $container->get('security.user.provider.concrete.users_in_memory');
        self::assertInstanceOf(UserProviderInterface::class, $provider);
        $user = $provider->loadUserByIdentifier($username);

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
