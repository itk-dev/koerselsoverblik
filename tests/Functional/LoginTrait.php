<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Signs in as a test user with the roles that the identity provider gives.
 */
trait LoginTrait
{
    /**
     * The roles of each test user. "user" has no permission role.
     */
    private const array TEST_USER_ROLES = [
        'admin' => ['ROLE_ADMIN'],
        'viewer' => ['ROLE_VIEW_OVERVIEW'],
        'user' => [],
    ];

    /**
     * Stores the user, so that the user is equal to the user at the refresh.
     */
    private function loginAs(KernelBrowser $client, string $username): void
    {
        self::assertArrayHasKey($username, self::TEST_USER_ROLES);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $user = new User($username.'@example.com');
        $user->setRoles(self::TEST_USER_ROLES[$username]);
        $entityManager->persist($user);
        $entityManager->flush();

        $client->loginUser($user);
    }
}
