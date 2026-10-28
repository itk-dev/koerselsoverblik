<?php

namespace App\Tests\Integration\Security;

use App\Repository\UserRepository;
use App\Security\OidcLogin;
use App\Security\OidcRoleMapper;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class OidcLoginTest extends KernelTestCase
{
    private MockClock $clock;

    private TestHandler $log;

    private OidcLogin $login;

    protected function setUp(): void
    {
        // In the default timezone, as the native clock, so that the time survives the database.
        $this->clock = new MockClock('2026-10-28 12:00:00', date_default_timezone_get());
        $this->log = new TestHandler();

        $this->login = new OidcLogin(
            $this->repository(),
            $this->entityManager(),
            new OidcRoleMapper(['ROLE_ADMIN' => ['ROLE_VIEW_OVERVIEW']], 'roles'),
            $this->clock,
            new Logger('openid_connect_audit', [$this->log]),
        );
    }

    public function testTheFirstLoginCreatesTheUserWithTheRolesFromTheClaim(): void
    {
        $user = $this->login->login(['upn' => 'Foobar@Example.com', 'roles' => ['ROLE_ADMIN', 'ROLE_SUPERHERO']]);

        $this->entityManager()->clear();
        $stored = $this->repository()->findOneByEmail('foobar@example.com');
        self::assertNotNull($stored);
        self::assertSame($user->getId(), $stored->getId());
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $stored->getRoles());
        self::assertEquals($this->clock->now(), $stored->getLastLoginAt());
    }

    public function testTheNextLoginUpdatesTheRolesAndTheTime(): void
    {
        $first = $this->login->login(['upn' => 'foobar@example.com', 'roles' => ['ROLE_ADMIN']]);
        $this->clock->sleep(3600);

        $next = $this->login->login(['upn' => 'foobar@example.com', 'roles' => ['ROLE_VIEW_OVERVIEW']]);

        $this->entityManager()->clear();
        self::assertSame($first->getId(), $next->getId());
        self::assertCount(1, $this->repository()->findAll());
        $stored = $this->repository()->findOneByEmail('foobar@example.com');
        self::assertNotNull($stored);
        self::assertSame(['ROLE_VIEW_OVERVIEW', 'ROLE_USER'], $stored->getRoles());
        self::assertEquals($this->clock->now(), $stored->getLastLoginAt());
    }

    public function testAUserWithNoKnownRoleCanSignIn(): void
    {
        $user = $this->login->login(['upn' => 'foobar@example.com']);

        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testALoginIsWrittenToTheAuthenticationLog(): void
    {
        $this->login->login(['upn' => 'foobar@example.com', 'roles' => ['ROLE_ADMIN', 'ROLE_VIEW_OVERVIEW']]);

        self::assertSame([[
            'INFO',
            'user foobar@example.com logged in with roles [ROLE_ADMIN, ROLE_VIEW_OVERVIEW] at '.$this->clock->now()->format(\DATE_ATOM),
            ['email' => 'foobar@example.com', 'roles' => ['ROLE_ADMIN', 'ROLE_VIEW_OVERVIEW']],
        ]], array_map(
            static fn (LogRecord $record): array => [$record->level->getName(), $record->message, $record->context],
            $this->log->getRecords(),
        ));
    }

    /**
     * @param array<string, mixed> $claims
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidClaimsProvider')]
    public function testALoginWithoutAValidUpnIsRejected(array $claims): void
    {
        $this->expectException(AuthenticationException::class);

        try {
            $this->login->login($claims);
        } finally {
            self::assertSame([], $this->repository()->findAll());
            self::assertFalse($this->log->hasRecords('info'));
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidClaimsProvider(): iterable
    {
        yield 'missing' => [['roles' => ['ROLE_ADMIN']]];
        yield 'empty' => [['upn' => ' ', 'roles' => ['ROLE_ADMIN']]];
        yield 'not a string' => [['upn' => ['foobar@example.com'], 'roles' => ['ROLE_ADMIN']]];
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }

    private function repository(): UserRepository
    {
        $repository = static::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $repository);

        return $repository;
    }
}
