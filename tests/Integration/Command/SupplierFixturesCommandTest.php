<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Entity\SubAgreement;
use App\Entity\Supplier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks that the command gives the sub-agreements of the development dataset their invented
 * suppliers.
 */
final class SupplierFixturesCommandTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }

    public function testCommandGivesTheSubAgreementsTheirSuppliers(): void
    {
        foreach (['NV-TA', 'Bakke', 'Turbestilling'] as $reference) {
            $this->entityManager->persist(new SubAgreement()->setReference($reference));
        }
        $this->entityManager->flush();

        $result = self::runCommand('app:fixtures:suppliers');

        $this->assertCommandIsSuccessful($result);
        self::assertStringContainsString('Gave 2 sub-agreements a supplier.', $result->getDisplay());
        self::assertSame('Vognmand A', $this->supplierOf('NV-TA'));
        self::assertSame('Vognmand B', $this->supplierOf('Bakke'));
        self::assertNull($this->supplierOf('Turbestilling'));
    }

    /**
     * NV-TA and VE-RO have the same supplier. The second run finds the supplier of the first run.
     */
    public function testEachSupplierIsCreatedOnce(): void
    {
        foreach (['NV-TA', 'VE-RO'] as $reference) {
            $this->entityManager->persist(new SubAgreement()->setReference($reference));
        }
        $this->entityManager->flush();

        self::runCommand('app:fixtures:suppliers');
        self::runCommand('app:fixtures:suppliers');

        self::assertCount(1, $this->entityManager->getRepository(Supplier::class)->findBy(['name' => 'Vognmand A']));
    }

    private function supplierOf(string $reference): ?string
    {
        $this->entityManager->clear();
        $subAgreement = $this->entityManager->getRepository(SubAgreement::class)->findOneBy(['reference' => $reference]);
        self::assertInstanceOf(SubAgreement::class, $subAgreement);

        return $subAgreement->getSupplier()?->getName();
    }
}
