<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Entity\ImportBatch;
use App\Entity\MonthCitizens;
use App\Entity\Route;
use App\Entity\StopRow;
use App\Entity\SubAgreement;
use App\Entity\Supplier;
use App\Import\Extract\StopRowDto;
use App\Import\ImportRangeDto;
use App\Import\Mapping\StopStatus;
use App\Import\Mapping\StopType;
use App\Report\DateRangeDto;
use App\Repository\MonthCitizensRepository;
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

    /**
     * The import ran before the suppliers existed, so it built only the count of all suppliers.
     * The command builds the counts of each supplier.
     */
    public function testCommandBuildsTheCitizensOfEachSupplier(): void
    {
        $subAgreement = new SubAgreement()->setReference('NV-TA');
        $route = new Route('NV-TA-01', $subAgreement);
        $batch = new ImportBatch('default', 'test.xlsx', null, new \DateTimeImmutable(), ImportRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-05'), 1));
        $serviceDate = new \DateTimeImmutable('2026-01-05');
        $row = new StopRowDto(
            rowNumber: 1,
            rowKey: 'S-1',
            route: $route->getSourceKey(),
            personId: 'P-A',
            serviceDate: $serviceDate,
            run: 1,
            stopType: StopType::Pickup,
            stopStatus: StopStatus::Completed,
            passengersAfter: null,
            nextDistanceKm: null,
            nextDurationMin: null,
            plannedTime: $serviceDate->setTime(7, 0),
            actualTime: null,
            plannedSolo: null,
        );
        foreach ([$subAgreement, $route, $batch, new StopRow($row, 'default', $route, $batch)] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();

        self::runCommand('app:fixtures:suppliers');

        $this->entityManager->clear();
        $supplier = $this->entityManager->getRepository(Supplier::class)->findOneBy(['name' => 'Vognmand A']);
        self::assertInstanceOf(Supplier::class, $supplier);
        $months = $this->entityManager->getRepository(MonthCitizens::class);
        self::assertInstanceOf(MonthCitizensRepository::class, $months);
        $january = DateRangeDto::create(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-01-31'));
        self::assertSame(['2026-01' => 1], $months->byMonth($january, $supplier->getId()));
    }

    private function supplierOf(string $reference): ?string
    {
        $this->entityManager->clear();
        $subAgreement = $this->entityManager->getRepository(SubAgreement::class)->findOneBy(['reference' => $reference]);
        self::assertInstanceOf(SubAgreement::class, $subAgreement);

        return $subAgreement->getSupplier()?->getName();
    }
}
