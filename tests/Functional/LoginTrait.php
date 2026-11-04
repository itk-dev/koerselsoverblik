<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Signs in as one of the in-memory test users in config/packages/security.yaml. M1-06 replaces them.
 */
trait LoginTrait
{
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
