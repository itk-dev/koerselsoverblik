<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks the access to the overview for anonymous and signed-in users.
 */
final class OverviewAccessTest extends WebTestCase
{
    use LoginTrait;

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
}
