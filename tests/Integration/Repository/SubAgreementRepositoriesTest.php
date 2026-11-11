<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\SubAgreement;
use App\Entity\Supplier;
use App\Repository\SubAgreementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks the queries for sub-agreements. Each test runs in a transaction that is rolled back.
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

    /**
     * A sub-agreement with no supplier needs the attention of an administrator, so it comes
     * first. Each group is sorted by the reference.
     */
    public function testSubAgreementsWithoutASupplierComeFirst(): void
    {
        $supplier = new Supplier()->setName('Vognmand A');
        $this->entityManager->persist($supplier);
        $this->subAgreement('A-SUPPLIER', $supplier);
        $this->subAgreement('D-NONE', null);
        $this->subAgreement('B-SUPPLIER', $supplier);
        $this->subAgreement('C-NONE', null);
        $this->entityManager->flush();

        $repository = self::getContainer()->get(SubAgreementRepository::class);
        self::assertInstanceOf(SubAgreementRepository::class, $repository);
        $references = array_map(
            static fn (SubAgreement $subAgreement): string => $subAgreement->getReference(),
            $repository->findAllWithoutSupplierFirst(),
        );

        self::assertSame(['C-NONE', 'D-NONE', 'A-SUPPLIER', 'B-SUPPLIER'], $references);
    }

    private function subAgreement(string $reference, ?Supplier $supplier): void
    {
        $this->entityManager->persist(new SubAgreement()->setReference($reference)->setSupplier($supplier));
    }
}
