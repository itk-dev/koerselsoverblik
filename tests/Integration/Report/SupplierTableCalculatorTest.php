<?php

declare(strict_types=1);

namespace App\Tests\Integration\Report;

use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Entity\ImportBatch;
use App\Entity\Route;
use App\Entity\RouteRun;
use App\Entity\StopRow;
use App\Entity\SubAgreement;
use App\Entity\Supplier;
use App\Import\DrivingDataImporter;
use App\Import\Extract\StopRowDto;
use App\Import\ImportRangeDto;
use App\Import\Mapping\MappingProvider;
use App\Import\Mapping\StopStatus;
use App\Import\Mapping\StopType;
use App\Import\RouteRunDto;
use App\Report\DateRangeDto;
use App\Report\DelayBandsDto;
use App\Report\PunctualityCountDto;
use App\Report\PunctualityFiguresDto;
use App\Report\PunctualityReportDto;
use App\Report\SoloCountDto;
use App\Report\SupplierPunctualityDto;
use App\Report\SupplierRowDto;
use App\Report\SupplierTableCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks the supplier table of the overview. The base scenario has the sub-agreements "R" (R-01
 * and R-02) and "BOOKED". The punctuality comes from the report of the month, which the tests
 * make by hand.
 */
final class SupplierTableCalculatorTest extends KernelTestCase
{
    private const string SCENARIOS = __DIR__.'/../../fixtures/driving_data';

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        $this->importBase();
    }

    /**
     * The routes have no supplier, so all counts are on "Ukendt".
     */
    public function testRoutesWithNoSupplierAreUnknown(): void
    {
        $rows = $this->calculate([new SupplierPunctualityDto(null, self::lastDropOff(3, 4))]);

        self::assertEquals([new SupplierRowDto(null, 7, 1, new SoloCountDto(4, 2), new PunctualityCountDto(3, 4))], $rows);
    }

    /**
     * R has 6 trips (1 cancelled) and 3 runs (1 solo). BOOKED has 1 trip and 1 solo run.
     */
    public function testEachSupplierHasTheCountsOfItsRoutes(): void
    {
        $this->giveSupplier('R', 'Vognmand A');
        $this->giveSupplier('BOOKED', 'Vognmand B');

        $rows = $this->calculate([
            new SupplierPunctualityDto('Vognmand A', self::lastDropOff(2, 3)),
            new SupplierPunctualityDto('Vognmand B', self::lastDropOff(1, 1)),
        ]);

        self::assertEquals([
            new SupplierRowDto('Vognmand A', 6, 1, new SoloCountDto(3, 1), new PunctualityCountDto(2, 3)),
            new SupplierRowDto('Vognmand B', 1, 0, new SoloCountDto(1, 1), new PunctualityCountDto(1, 1)),
        ], $rows);
    }

    /**
     * The order is the order of the punctuality page: by the name, with "Ukendt" last. A
     * supplier with no punctuality in the report has no measured last drop-offs.
     */
    public function testRowsHaveTheOrderOfThePunctualityPage(): void
    {
        $this->giveSupplier('BOOKED', 'Vognmand B');

        $rows = $this->calculate([new SupplierPunctualityDto(null, self::lastDropOff(2, 3))]);

        self::assertSame(['Vognmand B', null], array_map(static fn (SupplierRowDto $row): ?string => $row->supplier, $rows));
        self::assertEquals(new PunctualityCountDto(0, 0), $rows[0]->dropOffPerRun);
    }

    /**
     * "Ukendt" has a row when only its routes have counts, also with no punctuality.
     */
    public function testUnknownWithNoPunctualityHasARow(): void
    {
        $rows = $this->calculate([]);

        self::assertEquals([new SupplierRowDto(null, 7, 1, new SoloCountDto(4, 2), new PunctualityCountDto(0, 0))], $rows);
    }

    /**
     * A name with only digits is still a name, not a number.
     */
    public function testSupplierNameWithOnlyDigits(): void
    {
        $this->giveSupplier('BOOKED', '42');

        $rows = $this->calculate([]);

        self::assertSame(['42', null], array_map(static fn (SupplierRowDto $row): ?string => $row->supplier, $rows));
    }

    /**
     * X-01 has only a cancelled trip, so it has no counted run. X-02 has a run with a peak, but
     * no stop rows in the range. Both routes count for their supplier.
     */
    public function testRouteWithOnlyTripsOrOnlyRunsCounts(): void
    {
        $supplier = new Supplier()->setName('Vognmand D');
        $subAgreement = new SubAgreement()->setReference('X')->setSupplier($supplier);
        $this->entityManager->persist($supplier);
        $this->entityManager->persist($subAgreement);
        $onlyTrips = new Route('X-01', $subAgreement);
        $onlyRuns = new Route('X-02', $subAgreement);
        $this->entityManager->persist($onlyTrips);
        $this->entityManager->persist($onlyRuns);
        $this->entityManager->persist(new StopRow(new StopRowDto(
            rowNumber: 1,
            rowKey: 'X-1',
            route: 'X-01',
            personId: 'P-X',
            serviceDate: new \DateTimeImmutable('2026-01-06'),
            run: 1,
            stopType: StopType::Pickup,
            stopStatus: StopStatus::Cancelled,
            passengersAfter: null,
            nextDistanceKm: null,
            nextDurationMin: null,
            plannedTime: new \DateTimeImmutable('2026-01-06 07:00'),
            actualTime: null,
            plannedSolo: null,
        ), 'default', $onlyTrips, $this->batch()));
        $this->entityManager->persist(new RouteRun($onlyRuns, new \DateTimeImmutable('2026-01-06'), 1, new RouteRunDto(null, null, null, null, null, null, false, 1)));

        $rows = $this->calculate([]);

        self::assertEquals(new SupplierRowDto('Vognmand D', 1, 1, new SoloCountDto(1, 1), new PunctualityCountDto(0, 0)), $rows[0]);
    }

    /**
     * A supplier in the punctuality report with no trips in the range has a row with no trips.
     */
    public function testSupplierWithOnlyPunctualityHasARow(): void
    {
        $rows = $this->calculate([
            new SupplierPunctualityDto('Vognmand C', self::lastDropOff(1, 1)),
            new SupplierPunctualityDto(null, self::lastDropOff(3, 4)),
        ]);

        self::assertEquals(new SupplierRowDto('Vognmand C', 0, 0, new SoloCountDto(0, 0), new PunctualityCountDto(1, 1)), $rows[0]);
    }

    public function testRangeWithNoDataHasNoRows(): void
    {
        $calculator = self::getContainer()->get(SupplierTableCalculator::class);
        self::assertInstanceOf(SupplierTableCalculator::class, $calculator);

        $rows = $calculator->calculate(
            DateRangeDto::create(new \DateTimeImmutable('2026-02-01'), new \DateTimeImmutable('2026-02-28')),
            new PunctualityReportDto(PunctualityFiguresDto::empty(), [], []),
        );

        self::assertSame([], $rows);
    }

    /**
     * @param list<SupplierPunctualityDto> $suppliers
     *
     * @return list<SupplierRowDto>
     */
    private function calculate(array $suppliers): array
    {
        $this->entityManager->flush();
        $this->entityManager->clear();
        $calculator = self::getContainer()->get(SupplierTableCalculator::class);
        self::assertInstanceOf(SupplierTableCalculator::class, $calculator);

        return $calculator->calculate(
            DateRangeDto::create(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-01-31')),
            new PunctualityReportDto(PunctualityFiguresDto::empty(), $suppliers, []),
        );
    }

    private function giveSupplier(string $reference, string $name): void
    {
        $subAgreement = $this->entityManager->getRepository(SubAgreement::class)->findOneBy(['reference' => $reference]);
        self::assertInstanceOf(SubAgreement::class, $subAgreement);
        $supplier = $this->entityManager->getRepository(Supplier::class)->findOneBy(['name' => $name]) ?? new Supplier()->setName($name);
        $this->entityManager->persist($supplier);
        $subAgreement->setSupplier($supplier);
    }

    private function batch(): ImportBatch
    {
        $batch = $this->entityManager->getRepository(ImportBatch::class)->findOneBy([]);
        self::assertInstanceOf(ImportBatch::class, $batch);

        return $batch;
    }

    private static function lastDropOff(int $onTime, int $measured): PunctualityFiguresDto
    {
        $none = new PunctualityCountDto(0, 0);

        return new PunctualityFiguresDto($none, $none, $none, new PunctualityCountDto($onTime, $measured), new DelayBandsDto(0, 0, 0, 0));
    }

    private function importBase(): void
    {
        $scenario = new ScenarioLoader()->load(self::SCENARIOS.'/base.yaml');
        $path = sys_get_temp_dir().'/'.uniqid('extract_', true).'.xlsx';
        $provider = self::getContainer()->get(MappingProvider::class);
        self::assertInstanceOf(MappingProvider::class, $provider);
        new ExtractWriter()->write($path, $provider->default(), $scenario->rows);
        $importer = self::getContainer()->get(DrivingDataImporter::class);
        self::assertInstanceOf(DrivingDataImporter::class, $importer);
        $importer->import($path, ImportRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-05'), 31), $provider->default(), null, 'base.xlsx');
        unlink($path);
    }
}
