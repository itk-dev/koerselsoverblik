<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks the access to the overview for anonymous and signed-in users.
 */
final class OverviewAccessTest extends WebTestCase
{
    public function testAnonymousUserIsSentToTheLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');

        self::assertResponseRedirects('/openidconnect/login/default');
    }

    public function testUserWithTheOverviewRoleCanGetTheOverview(): void
    {
        $client = static::createClient();
        $this->loginWithRoles($client, ['ROLE_VIEW_OVERVIEW']);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
    }

    public function testUserWithNoPermissionRoleIsDenied(): void
    {
        $client = static::createClient();
        $this->loginWithRoles($client, []);

        $client->request('GET', '/');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAdminCanGetTheOverview(): void
    {
        $client = static::createClient();
        $this->loginWithRoles($client, ['ROLE_ADMIN']);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
    }

    /**
     * Stores a user with the roles, so that the user is equal to the user at the refresh.
     *
     * @param list<string> $roles
     */
    private function loginWithRoles(KernelBrowser $client, array $roles): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $user = new User('user@example.com');
        $user->setRoles($roles);
        $entityManager->persist($user);
        $entityManager->flush();

        $client->loginUser($user);
    }
}
