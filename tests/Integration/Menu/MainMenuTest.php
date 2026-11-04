<?php

namespace App\Tests\Integration\Menu;

use App\Menu\MainMenu;
use App\Menu\MenuBuilder;
use App\Menu\MenuLink;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Checks the main menu with the role hierarchy in config/packages/security.yaml.
 *
 * Not all menu routes exist yet, so a URL generator stub gives a URL for every route.
 */
final class MainMenuTest extends KernelTestCase
{
    public function testAdminSeesEveryItem(): void
    {
        self::assertSame(
            ['menu.overview', 'menu.daily_counts', 'menu.suppliers'],
            $this->labelsFor('admin'),
        );
    }

    public function testViewerSeesOnlyTheOverview(): void
    {
        self::assertSame(['menu.overview'], $this->labelsFor('viewer'));
    }

    public function testUserWithNoPermissionRoleSeesNoItem(): void
    {
        self::assertSame([], $this->labelsFor('user'));
    }

    /**
     * @return list<string>
     */
    private function labelsFor(string $username): array
    {
        $container = static::getContainer();

        $provider = $container->get('security.user.provider.concrete.users_in_memory');
        self::assertInstanceOf(UserProviderInterface::class, $provider);
        $user = $provider->loadUserByIdentifier($username);

        $tokenStorage = $container->get(TokenStorageInterface::class);
        self::assertInstanceOf(TokenStorageInterface::class, $tokenStorage);
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $authorizationChecker = $container->get(AuthorizationCheckerInterface::class);
        self::assertInstanceOf(AuthorizationCheckerInterface::class, $authorizationChecker);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(static fn (string $name): string => '/'.$name);

        $links = new MenuBuilder($urlGenerator, $authorizationChecker)->build(MainMenu::items());

        return array_map(static fn (MenuLink $link): string => $link->label, $links);
    }
}
