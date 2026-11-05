<?php

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testTheEmailIsTheIdentifierInLowerCase(): void
    {
        $user = new User(' Foobar@Example.COM ');

        self::assertSame('foobar@example.com', $user->getEmail());
        self::assertSame('foobar@example.com', $user->getUserIdentifier());
    }

    public function testTheEmailCannotBeEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new User(' ');
    }

    public function testANewUserHasOnlyRoleUser(): void
    {
        $user = new User('foobar@example.com');

        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertNull($user->getLastLoginAt());
    }

    public function testEveryUserAlsoHasRoleUser(): void
    {
        $user = new User('foobar@example.com');

        $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);

        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
    }

    public function testSetRolesReplacesTheRoles(): void
    {
        $user = new User('foobar@example.com');
        $user->setRoles(['ROLE_ADMIN']);

        $user->setRoles(['ROLE_VIEW_OVERVIEW']);

        self::assertSame(['ROLE_VIEW_OVERVIEW', 'ROLE_USER'], $user->getRoles());
    }

    public function testTheLastLoginTime(): void
    {
        $user = new User('foobar@example.com');
        $time = new \DateTimeImmutable('2026-10-28 12:00:00');

        $user->setLastLoginAt($time);

        self::assertSame($time, $user->getLastLoginAt());
    }
}
