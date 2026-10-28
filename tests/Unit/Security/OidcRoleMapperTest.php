<?php

namespace App\Tests\Unit\Security;

use App\Security\OidcRoleMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OidcRoleMapperTest extends TestCase
{
    private const array ROLE_HIERARCHY = [
        'ROLE_ADMIN' => ['ROLE_VIEW_OVERVIEW', 'ROLE_UPLOAD_DRIVING_DATA'],
    ];

    /**
     * @param array<string, mixed> $claims
     * @param list<string>         $expected
     */
    #[DataProvider('claimsProvider')]
    public function testMapsTheRoleClaim(array $claims, array $expected): void
    {
        $mapper = new OidcRoleMapper(self::ROLE_HIERARCHY, 'roles');

        self::assertSame($expected, $mapper->map($claims));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>}>
     */
    public static function claimsProvider(): iterable
    {
        yield 'admin' => [['roles' => ['ROLE_ADMIN']], ['ROLE_ADMIN']];
        yield 'permission roles' => [
            ['roles' => ['ROLE_VIEW_OVERVIEW', 'ROLE_UPLOAD_DRIVING_DATA']],
            ['ROLE_VIEW_OVERVIEW', 'ROLE_UPLOAD_DRIVING_DATA'],
        ];
        yield 'a string' => [['roles' => 'ROLE_VIEW_OVERVIEW'], ['ROLE_VIEW_OVERVIEW']];
        yield 'unknown values are ignored' => [['roles' => ['ROLE_VIEW_OVERVIEW', 'ROLE_SUPERHERO', 'admin']], ['ROLE_VIEW_OVERVIEW']];
        yield 'ROLE_USER is ignored' => [['roles' => ['ROLE_USER']], []];
        yield 'non-string values are ignored' => [['roles' => [42, null, ['ROLE_ADMIN']]], []];
        yield 'duplicates are removed' => [['roles' => ['ROLE_ADMIN', 'ROLE_ADMIN']], ['ROLE_ADMIN']];
        yield 'missing claim' => [['upn' => 'user@example.com'], []];
        yield 'empty claim' => [['roles' => []], []];
        yield 'empty string' => [['roles' => ''], []];
        yield 'claim of another type' => [['roles' => 42], []];
    }

    public function testReadsTheConfiguredClaim(): void
    {
        $mapper = new OidcRoleMapper(self::ROLE_HIERARCHY, 'groups');

        self::assertSame(['ROLE_ADMIN'], $mapper->map(['groups' => ['ROLE_ADMIN'], 'roles' => ['ROLE_VIEW_OVERVIEW']]));
    }
}
