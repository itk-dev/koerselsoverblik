<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\OidcRoleMapper;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OidcRoleMapperTest extends TestCase
{
    private const array ROLE_HIERARCHY = [
        'ROLE_ADMIN' => ['ROLE_VIEW_OVERVIEW', 'ROLE_MANAGE_DRIVING_DATA'],
    ];

    private const array ROLE_MAP = [
        'admin' => 'ROLE_ADMIN',
        'enduser' => 'ROLE_VIEW_OVERVIEW',
        'misconfigured' => 'ROLE_SUPERHERO',
    ];

    /**
     * @param array<string, mixed> $claims
     * @param list<string>         $expected
     */
    #[DataProvider('claimsProvider')]
    public function testMapsTheRoleClaim(array $claims, array $expected): void
    {
        $mapper = new OidcRoleMapper(self::ROLE_HIERARCHY, 'roles', self::ROLE_MAP);

        self::assertSame($expected, $mapper->map($claims));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>}>
     */
    public static function claimsProvider(): iterable
    {
        yield 'admin' => [['roles' => ['ROLE_ADMIN']], ['ROLE_ADMIN']];
        yield 'permission roles' => [
            ['roles' => ['ROLE_VIEW_OVERVIEW', 'ROLE_MANAGE_DRIVING_DATA']],
            ['ROLE_VIEW_OVERVIEW', 'ROLE_MANAGE_DRIVING_DATA'],
        ];
        yield 'a string' => [['roles' => 'ROLE_VIEW_OVERVIEW'], ['ROLE_VIEW_OVERVIEW']];
        yield 'mapped values' => [['roles' => ['admin', 'enduser']], ['ROLE_ADMIN', 'ROLE_VIEW_OVERVIEW']];
        yield 'a mapped value and its role give one role' => [['roles' => ['admin', 'ROLE_ADMIN']], ['ROLE_ADMIN']];
        yield 'mapped values are case-sensitive' => [['roles' => ['Admin']], []];
        yield 'a value mapped to an unknown role is ignored' => [['roles' => ['misconfigured']], []];
        yield 'unknown values are ignored' => [['roles' => ['ROLE_VIEW_OVERVIEW', 'ROLE_SUPERHERO', 'superhero']], ['ROLE_VIEW_OVERVIEW']];
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

    public function testLogsTheIgnoredValues(): void
    {
        $log = new TestHandler();
        $mapper = new OidcRoleMapper(self::ROLE_HIERARCHY, 'roles', self::ROLE_MAP, new Logger('test', [$log]));

        $mapper->map(['roles' => ['admin', 'superhero', 'misconfigured', 'superhero', 42]]);

        self::assertCount(1, $log->getRecords());
        $record = $log->getRecords()[0];
        self::assertSame(Level::Warning, $record->level);
        self::assertSame('ignored role values [superhero, misconfigured]', $record->message);
        self::assertSame(['values' => ['superhero', 'misconfigured']], $record->context);
    }

    public function testLogsNothingWhenAllValuesAreKnown(): void
    {
        $log = new TestHandler();
        $mapper = new OidcRoleMapper(self::ROLE_HIERARCHY, 'roles', self::ROLE_MAP, new Logger('test', [$log]));

        $mapper->map(['roles' => ['admin', 'ROLE_VIEW_OVERVIEW']]);

        self::assertSame([], $log->getRecords());
    }
}
