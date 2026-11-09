<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\Security\OidcRoleMapper;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks the role claim against the role hierarchy in config/packages/security.yaml.
 */
final class OidcRoleMapperTest extends KernelTestCase
{
    /**
     * One permission role gives access to all of the driving data administration.
     */
    public function testOnlyTheManageRoleGivesAccessToTheDrivingData(): void
    {
        /** @var array<string, list<string>> $roleHierarchy */
        $roleHierarchy = self::getContainer()->getParameter('security.role_hierarchy.roles');
        $mapper = new OidcRoleMapper($roleHierarchy, 'roles');

        self::assertSame(
            ['ROLE_MANAGE_DRIVING_DATA'],
            $mapper->map(['roles' => ['ROLE_UPLOAD_DRIVING_DATA', 'ROLE_MANAGE_DRIVING_DATA', 'ROLE_CLEAR_DRIVING_DATA']]),
        );
    }
}
