<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * The two app roles of Entra ID give the permission roles.
 */
final class RoleHierarchyTest extends KernelTestCase
{
    private const array PERMISSION_ROLES = [
        'ROLE_VIEW_OVERVIEW',
        'ROLE_MANAGE_DRIVING_DATA',
        'ROLE_EDIT_DAILY_COUNTS',
        'ROLE_MANAGE_SUPPLIERS',
    ];

    /**
     * At this time, "Superadministrator" (admin) and "Slutbruger" (user) give the same access.
     */
    #[DataProvider('appRoleProvider')]
    public function testEachAppRoleGivesAllPermissionRoles(string $role): void
    {
        $hierarchy = self::getContainer()->get('security.role_hierarchy');
        self::assertInstanceOf(RoleHierarchyInterface::class, $hierarchy);

        $reachable = $hierarchy->getReachableRoleNames([$role]);

        self::assertSame([], array_values(array_diff(self::PERMISSION_ROLES, $reachable)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function appRoleProvider(): iterable
    {
        yield 'Superadministrator' => ['ROLE_ADMIN'];
        yield 'Slutbruger' => ['ROLE_USER'];
    }
}
