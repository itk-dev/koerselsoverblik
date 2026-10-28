<?php

namespace App\Tests\Integration\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UserRepositoryTest extends KernelTestCase
{
    public function testFindsAUserByEmail(): void
    {
        $user = new User('foobar@example.com');
        $user->setRoles(['ROLE_ADMIN']);
        $this->entityManager()->persist($user);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $found = $this->repository()->findOneByEmail('Foobar@Example.com');

        self::assertNotNull($found);
        self::assertSame('foobar@example.com', $found->getEmail());
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $found->getRoles());
    }

    public function testAnUnknownEmailGivesNoUser(): void
    {
        self::assertNull($this->repository()->findOneByEmail('nobody@example.com'));
    }

    public function testTheEmailIsUnique(): void
    {
        $this->entityManager()->persist(new User('foobar@example.com'));
        $this->entityManager()->persist(new User('FOOBAR@example.com'));

        $this->expectException(UniqueConstraintViolationException::class);

        $this->entityManager()->flush();
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
