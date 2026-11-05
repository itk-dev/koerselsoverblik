<?php

namespace App\Tests\Integration\Doctrine;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks that Doctrine stores a date and time in UTC, also when the PHP default time zone is
 * another time zone.
 */
final class UtcDateTimeRoundTripTest extends KernelTestCase
{
    private string $defaultTimeZone;

    protected function setUp(): void
    {
        $this->defaultTimeZone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        date_default_timezone_set($this->defaultTimeZone);
    }

    public function testACopenhagenTimeIsStoredAndReadInUtc(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        date_default_timezone_set('Europe/Copenhagen');

        $user = new User('foobar@example.com');
        $user->setLastLoginAt(new \DateTimeImmutable('2026-10-28 13:00:00', new \DateTimeZone('Europe/Copenhagen')));
        $entityManager->persist($user);
        $entityManager->flush();
        $entityManager->clear();

        self::assertSame('2026-10-28 12:00:00', $entityManager->getConnection()->fetchOne('SELECT last_login_at FROM `user`'));
        $repository = self::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $repository);
        $stored = $repository->findOneByEmail('foobar@example.com')?->getLastLoginAt();
        self::assertNotNull($stored);
        self::assertSame('2026-10-28 12:00:00 UTC', $stored->format('Y-m-d H:i:s T'));
    }
}
