<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\InMemoryUser;

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
        $client->loginUser(new InMemoryUser('viewer', null, ['ROLE_USER', 'ROLE_VIEW_OVERVIEW']));

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
    }

    public function testUserWithNoPermissionRoleIsDenied(): void
    {
        $client = static::createClient();
        $client->loginUser(new InMemoryUser('user', null, ['ROLE_USER']));

        $client->request('GET', '/');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAdminCanGetTheOverview(): void
    {
        $client = static::createClient();
        $client->loginUser(new InMemoryUser('admin', null, ['ROLE_USER', 'ROLE_ADMIN']));

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
    }
}
