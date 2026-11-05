<?php

namespace App\Tests\Integration\Menu;

use App\Entity\User;
use App\Menu\MainMenu;
use App\Menu\MenuBuilder;
use App\Menu\MenuLink;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

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
            $this->labelsFor(['ROLE_ADMIN']),
        );
    }

    public function testViewerSeesOnlyTheOverview(): void
    {
        self::assertSame(['menu.overview'], $this->labelsFor(['ROLE_VIEW_OVERVIEW']));
    }

    public function testUserWithNoPermissionRoleSeesNoItem(): void
    {
        self::assertSame([], $this->labelsFor([]));
    }

    /**
     * @param list<string> $roles
     *
     * @return list<string>
     */
    private function labelsFor(array $roles): array
    {
        $container = static::getContainer();

        $user = new User('user@example.com');
        $user->setRoles($roles);

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
