<?php

namespace App\Tests\Integration\Repository;

use App\Entity\SubAgreement;
use App\Entity\SubAgreementSupplierAssignment;
use App\Entity\Supplier;
use App\Repository\SubAgreementRepository;
use App\Repository\SubAgreementSupplierAssignmentRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks the queries for sub-agreements and their supplier assignments. Each test runs in a
 * transaction that is rolled back.
 */
final class SubAgreementRepositoriesTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }

    public function testSupplierOnADateFollowsTheAssignments(): void
    {
        $subAgreement = $this->subAgreement('AB-CD');
        $old = $this->supplier('Gammel Vognmand');
        $new = $this->supplier('Ny Vognmand');
        $this->assign($subAgreement, $old, '2026-01-01', '2026-03-31');
        $this->assign($subAgreement, $new, '2026-04-01', null);
        $this->entityManager->flush();

        $repository = $this->assignments();
        self::assertNull($repository->findSupplier($subAgreement, new \DateTimeImmutable('2025-12-31')));
        self::assertSame($old, $repository->findSupplier($subAgreement, new \DateTimeImmutable('2026-01-01')));
        self::assertSame($old, $repository->findSupplier($subAgreement, new \DateTimeImmutable('2026-03-31')));
        self::assertSame($new, $repository->findSupplier($subAgreement, new \DateTimeImmutable('2026-04-01')));
        self::assertSame($new, $repository->findSupplier($subAgreement, new \DateTimeImmutable('2030-01-01')));
    }

    public function testSupplierOfOneSubAgreementIsNotTheSupplierOfAnother(): void
    {
        $assigned = $this->subAgreement('AB-CD');
        $other = $this->subAgreement('TEST');
        $this->assign($assigned, $this->supplier('Vognmand'), '2026-01-01', null);
        $this->entityManager->flush();

        self::assertNull($this->assignments()->findSupplier($other, new \DateTimeImmutable('2026-06-01')));
    }

    /**
     * Only the validator prevents overlap, so a race can save two overlapping assignments. The
     * figures must not fail because of that.
     */
    public function testOverlappingAssignmentsGiveTheSupplierWithTheLatestFirstDate(): void
    {
        $subAgreement = $this->subAgreement('AB-CD');
        $earlier = $this->supplier('Tidlig Vognmand');
        $later = $this->supplier('Sen Vognmand');
        $this->assign($subAgreement, $later, '2026-02-01', null);
        $this->assign($subAgreement, $earlier, '2026-01-01', null);
        $this->entityManager->flush();

        $repository = $this->assignments();
        self::assertSame($earlier, $repository->findSupplier($subAgreement, new \DateTimeImmutable('2026-01-31')));
        self::assertSame($later, $repository->findSupplier($subAgreement, new \DateTimeImmutable('2026-02-01')));
    }

    /**
     * The database stops the most likely duplicate: the same form sent two times at once.
     */
    public function testTwoAssignmentsOfASubAgreementCannotHaveTheSameFirstDate(): void
    {
        $subAgreement = $this->subAgreement('AB-CD');
        $supplier = $this->supplier('Vognmand');
        $this->assign($subAgreement, $supplier, '2026-01-01', null);
        $this->assign($subAgreement, $supplier, '2026-01-01', null);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->entityManager->flush();
    }

    /**
     * A sub-agreement without a current assignment needs the attention of an administrator, so it
     * comes first. Each group is sorted by the reference.
     */
    public function testSubAgreementsWithoutACurrentAssignmentComeFirst(): void
    {
        $supplier = $this->supplier('Vognmand');
        $this->assign($this->subAgreement('A-CURRENT'), $supplier, '2026-01-01', null);
        $this->assign($this->subAgreement('B-ENDED'), $supplier, '2026-01-01', '2026-05-31');
        $this->subAgreement('C-NONE');
        $this->assign($this->subAgreement('D-LATER'), $supplier, '2026-07-01', null);
        $this->assign($this->subAgreement('E-CURRENT'), $supplier, '2026-06-01', '2026-06-01');
        $this->entityManager->flush();

        $repository = self::getContainer()->get(SubAgreementRepository::class);
        self::assertInstanceOf(SubAgreementRepository::class, $repository);
        $references = array_map(
            static fn (SubAgreement $subAgreement): string => $subAgreement->getReference(),
            $repository->findAllWithoutCurrentAssignmentFirst(new \DateTimeImmutable('2026-06-01')),
        );

        self::assertSame(['B-ENDED', 'C-NONE', 'D-LATER', 'A-CURRENT', 'E-CURRENT'], $references);
    }

    private function assignments(): SubAgreementSupplierAssignmentRepository
    {
        $repository = self::getContainer()->get(SubAgreementSupplierAssignmentRepository::class);
        self::assertInstanceOf(SubAgreementSupplierAssignmentRepository::class, $repository);

        return $repository;
    }

    private function subAgreement(string $reference): SubAgreement
    {
        $subAgreement = (new SubAgreement())->setReference($reference);
        $this->entityManager->persist($subAgreement);

        return $subAgreement;
    }

    private function supplier(string $name): Supplier
    {
        $supplier = (new Supplier())->setName($name);
        $this->entityManager->persist($supplier);

        return $supplier;
    }

    private function assign(SubAgreement $subAgreement, Supplier $supplier, string $from, ?string $to): void
    {
        $this->entityManager->persist(new SubAgreementSupplierAssignment($subAgreement, $supplier, new \DateTimeImmutable($from), null === $to ? null : new \DateTimeImmutable($to)));
    }
}
