<?php

declare(strict_types=1);

namespace App\Tests\Integration\Report;

use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Entity\ImportBatch;
use App\Entity\Route;
use App\Entity\RouteDayTotals;
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
use App\Import\RouteDayTotalsBuilder;
use App\Import\RouteRunDto;
use App\Report\ActivitySupplierRowDto;
use App\Report\ActivityTableCalculator;
use App\Report\ActivityTablesDto;
use App\Report\DateRangeDto;
use App\Report\SubAgreementTripsDto;
use App\Repository\RouteDayTotalsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks the tables of the activity page. The base scenario has the sub-agreements "R" (R-01 and
 * R-02) and "BOOKED". R has 6 trips: 4 completed, 1 cancelled and 1 with no registration, and 3
 * runs. BOOKED has 1 completed trip and 1 run.
 */
final class ActivityTableCalculatorTest extends KernelTestCase
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
     * The sub-agreement with the most trips is first. The share is of the trips of all rows.
     */
    public function testSubAgreementsWithTheMostTripsFirst(): void
    {
        $tables = $this->calculate();

        self::assertEquals([
            new SubAgreementTripsDto('R', null, 6, 6 / 7 * 100),
            new SubAgreementTripsDto('BOOKED', null, 1, 1 / 7 * 100),
        ], $tables->subAgreements);
    }

    /**
     * On the same number of trips, the reference decides the order.
     */
    public function testSameTripsAreInTheOrderOfTheReference(): void
    {
        $this->pickup($this->route('AA', null), 'AA-1');

        $references = array_map(static fn (SubAgreementTripsDto $row): string => $row->reference, $this->calculate()->subAgreements);

        self::assertSame(['R', 'AA', 'BOOKED'], $references);
    }

    /**
     * The routes have no supplier, so all counts are on "Ukendt".
     */
    public function testRoutesWithNoSupplierAreUnknown(): void
    {
        $suppliers = $this->calculate()->suppliers;

        self::assertCount(1, $suppliers);
        self::assertNull($suppliers[0]->supplier);
        self::assertSame([7, 5, 1, 4], self::counts($suppliers[0]));
    }

    /**
     * Each supplier has the counts of its routes. The suppliers are in the order of the name, with
     * "Ukendt" last.
     */
    public function testEachSupplierHasTheCountsOfItsRoutes(): void
    {
        $this->giveSupplier('R', 'Vognmand B');
        $this->giveSupplier('BOOKED', 'Vognmand A');
        $this->pickup($this->route('X', null), 'X-1');

        $tables = $this->calculate();

        self::assertSame(['Vognmand A', 'Vognmand B', null], array_map(static fn (ActivitySupplierRowDto $row): ?string => $row->supplier, $tables->suppliers));
        self::assertSame([[1, 1, 0, 1], [6, 4, 1, 3], [1, 1, 0, 0]], array_map(self::counts(...), $tables->suppliers));
        // BOOKED and X have the same number of trips, so the reference decides.
        self::assertSame(['Vognmand B', 'Vognmand A', null], array_map(static fn (SubAgreementTripsDto $row): ?string => $row->supplier, $tables->subAgreements));
    }

    /**
     * A name with only digits is an integer key in PHP. The row still has the name as a string.
     */
    public function testSupplierNameWithOnlyDigitsIsAString(): void
    {
        $this->giveSupplier('R', '123');

        $tables = $this->calculate();

        self::assertSame(['123', null], array_map(static fn (ActivitySupplierRowDto $row): ?string => $row->supplier, $tables->suppliers));
    }

    /**
     * With a supplier, the table of the sub-agreements has only the sub-agreements of that
     * supplier, and the shares are of their trips. The table of the suppliers has all suppliers.
     */
    public function testSupplierFilterLimitsOnlyTheSubAgreements(): void
    {
        $this->giveSupplier('R', 'Vognmand A');
        $this->giveSupplier('BOOKED', 'Vognmand B');
        $this->entityManager->flush();
        $supplier = $this->entityManager->getRepository(Supplier::class)->findOneBy(['name' => 'Vognmand A']);
        self::assertInstanceOf(Supplier::class, $supplier);

        $tables = $this->calculate($supplier->getId());

        self::assertEquals([new SubAgreementTripsDto('R', 'Vognmand A', 6, 100.0)], $tables->subAgreements);
        self::assertCount(2, $tables->suppliers);
    }

    /**
     * The trips and the runs of an ignored sub-agreement do not count.
     */
    public function testIgnoredSubAgreementDoesNotCount(): void
    {
        $subAgreement = $this->entityManager->getRepository(SubAgreement::class)->findOneBy(['reference' => 'BOOKED']);
        self::assertInstanceOf(SubAgreement::class, $subAgreement);
        $subAgreement->setIgnored(true);

        $tables = $this->calculate();

        self::assertEquals([new SubAgreementTripsDto('R', null, 6, 100.0)], $tables->subAgreements);
        self::assertSame([[6, 4, 1, 3]], array_map(self::counts(...), $tables->suppliers));
    }

    /**
     * A route with only a run and no trips gives a supplier row, as on the overview, but no row of
     * a sub-agreement, because a share of 0 trips says nothing.
     */
    public function testRouteWithNoTripsHasNoSubAgreementRow(): void
    {
        $route = $this->route('X', 'Vognmand D');
        $this->entityManager->persist(new RouteRun($route, new \DateTimeImmutable('2026-01-06'), 1, new RouteRunDto(null, null, null, null, null, null, false, 0)));

        $tables = $this->calculate();

        self::assertNotContains('X', array_map(static fn (SubAgreementTripsDto $row): string => $row->reference, $tables->subAgreements));
        self::assertSame([0, 0, 0, 1], self::counts($tables->suppliers[0]));
    }

    /**
     * The rows add up to the figures of the tiles.
     */
    public function testRowsAddUpToTheTiles(): void
    {
        $this->giveSupplier('R', 'Vognmand A');

        $tables = $this->calculate();

        $totals = $this->entityManager->getRepository(RouteDayTotals::class);
        self::assertInstanceOf(RouteDayTotalsRepository::class, $totals);
        $sum = $totals->sum(self::january());
        self::assertSame($sum->trips, array_sum(array_map(static fn (SubAgreementTripsDto $row): int => $row->trips, $tables->subAgreements)));
        self::assertSame($sum->trips, array_sum(array_map(static fn (ActivitySupplierRowDto $row): int => $row->totals->trips, $tables->suppliers)));
        self::assertSame($sum->notCancelledRuns, array_sum(array_map(static fn (ActivitySupplierRowDto $row): int => $row->totals->notCancelledRuns, $tables->suppliers)));
    }

    public function testRangeWithNoDataHasNoRows(): void
    {
        $calculator = self::getContainer()->get(ActivityTableCalculator::class);
        self::assertInstanceOf(ActivityTableCalculator::class, $calculator);

        $tables = $calculator->calculate(DateRangeDto::create(new \DateTimeImmutable('2026-02-01'), new \DateTimeImmutable('2026-02-28')), null);

        self::assertCount(0, $tables->subAgreements);
        self::assertCount(0, $tables->suppliers);
    }

    /**
     * @return list<int> the trips, the completed and the cancelled trips, and the runs that are not cancelled
     */
    private static function counts(ActivitySupplierRowDto $row): array
    {
        return [$row->totals->trips, $row->totals->completedTrips, $row->totals->cancelledTrips, $row->totals->notCancelledRuns];
    }

    private function calculate(?int $supplierId = null): ActivityTablesDto
    {
        $this->entityManager->flush();
        // The tests add stop rows and route runs directly, so the totals are built as an import does.
        $totalsBuilder = self::getContainer()->get(RouteDayTotalsBuilder::class);
        self::assertInstanceOf(RouteDayTotalsBuilder::class, $totalsBuilder);
        $totalsBuilder->rebuild(ImportRangeDto::create(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-01-31'), 31));
        $this->entityManager->clear();
        $calculator = self::getContainer()->get(ActivityTableCalculator::class);
        self::assertInstanceOf(ActivityTableCalculator::class, $calculator);

        return $calculator->calculate(self::january(), $supplierId);
    }

    private static function january(): DateRangeDto
    {
        return DateRangeDto::create(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-01-31'));
    }

    private function giveSupplier(string $reference, string $name): void
    {
        $subAgreement = $this->entityManager->getRepository(SubAgreement::class)->findOneBy(['reference' => $reference]);
        self::assertInstanceOf(SubAgreement::class, $subAgreement);
        $supplier = new Supplier()->setName($name);
        $this->entityManager->persist($supplier);
        $subAgreement->setSupplier($supplier);
    }

    /**
     * Makes a route "<REFERENCE>-01" with a sub-agreement of its own.
     */
    private function route(string $reference, ?string $supplierName): Route
    {
        $supplier = null === $supplierName ? null : new Supplier()->setName($supplierName);
        if (null !== $supplier) {
            $this->entityManager->persist($supplier);
        }
        $subAgreement = new SubAgreement()->setReference($reference)->setSupplier($supplier);
        $route = new Route($reference.'-01', $subAgreement);
        $this->entityManager->persist($subAgreement);
        $this->entityManager->persist($route);

        return $route;
    }

    /**
     * Adds one completed pickup on 6 January.
     */
    private function pickup(Route $route, string $rowKey): void
    {
        $batch = $this->entityManager->getRepository(ImportBatch::class)->findOneBy([]);
        self::assertInstanceOf(ImportBatch::class, $batch);
        $this->entityManager->persist(new StopRow(new StopRowDto(
            rowNumber: 1,
            rowKey: $rowKey,
            route: $route->getSourceKey(),
            personId: 'P-X',
            serviceDate: new \DateTimeImmutable('2026-01-06'),
            run: 1,
            stopType: StopType::Pickup,
            stopStatus: StopStatus::Completed,
            passengersAfter: 1,
            nextDistanceKm: null,
            nextDurationMin: null,
            plannedTime: new \DateTimeImmutable('2026-01-06 07:00'),
            actualTime: null,
            plannedSolo: null,
            locationName: null,
        ), 'default', $route, $batch));
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
