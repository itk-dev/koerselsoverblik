<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Checks the access to the overview for anonymous and signed-in users.
 *
 * The users are the in-memory test users in config/packages/security.yaml. M1-06 replaces them.
 */
final class OverviewAccessTest extends WebTestCase
{
    public function testAnonymousUserIsSentToTheLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function testAnonymousUserCanGetTheLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
    }

    public function testUserWithTheOverviewRoleCanGetTheOverview(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'viewer');

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
    }

    public function testUserWithNoPermissionRoleIsDenied(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'user');

        $client->request('GET', '/');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAdminCanGetTheOverview(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'admin');

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
    }

    /**
     * Loads the user from the provider, so that the user is equal to the user at the refresh.
     */
    private function loginAs(KernelBrowser $client, string $username): void
    {
        $provider = static::getContainer()->get('security.user.provider.concrete.users_in_memory');
        self::assertInstanceOf(UserProviderInterface::class, $provider);

        $client->loginUser($provider->loadUserByIdentifier($username));
    }
}
