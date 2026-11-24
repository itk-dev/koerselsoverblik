<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Entity\Route;
use App\Entity\RouteDayTotals;
use App\Entity\RouteRun;
use App\Entity\SubAgreement;
use App\Entity\Supplier;
use App\Import\DrivingDataImporter;
use App\Import\ImportRangeDto;
use App\Import\Mapping\MappingProvider;
use App\Import\RouteDayTotalsBuilder;
use App\Import\RouteRunDto;
use App\Report\DateRangeDto;
use App\Report\DayTotalsDto;
use App\Report\SoloCountDto;
use App\Repository\RouteDayTotalsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks the real solo runs of a date range: the runs with exactly one citizen in the car at a
 * time. Also checks the runs that are not cancelled. The counts come from the totals of each route
 * and day.
 */
final class RouteRunSoloCountTest extends KernelTestCase
{
    private const string SCENARIOS = __DIR__.'/../../fixtures/driving_data';

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }

    /**
     * The expected values are in the "expected" section of the scenario: 2 solo runs of 4 runs.
     */
    public function testBaseScenario(): void
    {
        $this->importBase();

        self::assertEquals(new SoloCountDto(runs: 4, soloRuns: 2), $this->solo('2026-01-01', '2026-01-31'));
    }

    /**
     * A cancelled run, a run with no passenger count and a run with no citizen in the car are not
     * counted. A run with a peak of 0 is neither solo nor shared.
     */
    public function testOnlyRunsWithACitizenThatAreNotCancelledCount(): void
    {
        $route = $this->route();
        $this->routeRun($route, '2026-02-02', 1, peak: 1);
        $this->routeRun($route, '2026-02-02', 2, peak: 3);
        $this->routeRun($route, '2026-02-02', 3, peak: null);
        $this->routeRun($route, '2026-02-02', 4, peak: null, cancelled: true);
        $this->routeRun($route, '2026-02-02', 5, peak: 0);

        self::assertEquals(new SoloCountDto(runs: 2, soloRuns: 1), $this->solo('2026-02-01', '2026-02-28'));
    }

    /**
     * The runs that are not cancelled also have a run with no citizen in the car, and a run with
     * no passenger count. Only a cancelled run is not counted.
     */
    public function testRunsThatAreNotCancelledHaveAllPeaks(): void
    {
        $route = $this->route();
        $this->routeRun($route, '2026-02-02', 1, peak: 1);
        $this->routeRun($route, '2026-02-02', 2, peak: 3);
        $this->routeRun($route, '2026-02-02', 3, peak: null);
        $this->routeRun($route, '2026-02-02', 4, peak: null, cancelled: true);
        $this->routeRun($route, '2026-02-02', 5, peak: 0);
        $this->routeRun($route, '2026-02-02', 6, peak: 2, cancelled: true);

        self::assertSame(4, $this->totals()->sum(self::range('2026-02-01', '2026-02-28'), null)->notCancelledRuns);
    }

    /**
     * The runs of the base scenario: R-01 has two runs, R-02 and BOOKED have one run each. No run
     * has only cancelled stops.
     */
    public function testBaseScenarioHasFourRunsThatAreNotCancelled(): void
    {
        $this->importBase();

        self::assertSame(4, $this->totals()->sum(self::range('2026-01-01', '2026-01-31'), null)->notCancelledRuns);
    }

    public function testOnlyTheDatesOfTheRangeCount(): void
    {
        $route = $this->route();
        $this->routeRun($route, '2026-01-31', 1, peak: 1);
        $this->routeRun($route, '2026-02-01', 1, peak: 1);
        $this->routeRun($route, '2026-02-28', 1, peak: 2);
        $this->routeRun($route, '2026-03-01', 1, peak: 1);

        self::assertEquals(new SoloCountDto(runs: 2, soloRuns: 1), $this->solo('2026-02-01', '2026-02-28'));
    }

    /**
     * The runs of an ignored sub-agreement do not count.
     */
    public function testIgnoredSubAgreementDoesNotCount(): void
    {
        $route = $this->route();
        $ignored = $this->route('I', ignored: true);
        $this->routeRun($route, '2026-02-02', 1, peak: 2);
        $this->routeRun($ignored, '2026-02-02', 1, peak: 1);
        $this->routeRun($ignored, '2026-02-02', 2, peak: 3);

        self::assertEquals(new SoloCountDto(runs: 1, soloRuns: 0), $this->solo('2026-02-01', '2026-02-28'));
    }

    public function testRangeWithNoRunsHasZeroCounts(): void
    {
        self::assertEquals(new SoloCountDto(0, 0), $this->solo('2026-02-01', '2026-02-28'));
    }

    /**
     * With a supplier, only the routes of the sub-agreements of that supplier count. An ignored
     * sub-agreement of the supplier does not count.
     */
    public function testOnlyTheRoutesOfTheSupplierCount(): void
    {
        $supplier = new Supplier()->setName('Vognmand A');
        $this->entityManager->persist($supplier);
        $this->routeRun($this->route('A', supplier: $supplier), '2026-02-02', 1, peak: 1);
        $this->routeRun($this->route('AI', ignored: true, supplier: $supplier), '2026-02-02', 1, peak: 1);
        $this->routeRun($this->route(), '2026-02-02', 1, peak: 2);
        $this->entityManager->flush();

        self::assertEquals(new SoloCountDto(runs: 1, soloRuns: 1), $this->solo('2026-02-01', '2026-02-28', $supplier->getId()));
        self::assertEquals(new SoloCountDto(runs: 2, soloRuns: 1), $this->solo('2026-02-01', '2026-02-28'));
    }

    /**
     * With no counted route, nothing counts. The query has no list of routes.
     */
    public function testNoCountedRouteHasZeroCounts(): void
    {
        $this->routeRun($this->route('I', ignored: true), '2026-02-02', 1, peak: 1);

        self::assertEquals(new SoloCountDto(0, 0), $this->solo('2026-02-01', '2026-02-28'));
        self::assertSame([], $this->soloByMonth(self::range('2026-02-01', '2026-02-28')));
    }

    /**
     * Each month has the counts of the range of that month. A month with no item has no counted
     * runs, as the pages read it. March only has a run with no citizen, so it counts nothing.
     */
    public function testSoloRunsOfEachMonth(): void
    {
        $supplier = new Supplier()->setName('Vognmand A');
        $this->entityManager->persist($supplier);
        $ofSupplier = $this->route('A', supplier: $supplier);
        $other = $this->route();
        $this->routeRun($ofSupplier, '2026-01-31', 1, peak: 1);
        $this->routeRun($other, '2026-01-31', 1, peak: 2);
        $this->routeRun($ofSupplier, '2026-02-01', 1, peak: 1);
        $this->routeRun($this->route('I', ignored: true, supplier: $supplier), '2026-02-01', 1, peak: 1);
        $this->routeRun($other, '2026-03-02', 1, peak: 0);
        $this->entityManager->flush();
        $range = self::range('2026-01-01', '2026-03-31');

        foreach ([null, $supplier->getId()] as $supplierId) {
            $months = $this->soloByMonth($range, $supplierId);

            self::assertEquals($this->solo('2026-01-01', '2026-01-31', $supplierId), $months['2026-01']);
            self::assertEquals($this->solo('2026-02-01', '2026-02-28', $supplierId), $months['2026-02']);
            self::assertEquals(new SoloCountDto(0, 0), $months['2026-03'] ?? new SoloCountDto(0, 0));
        }
        self::assertEquals(new SoloCountDto(runs: 2, soloRuns: 1), $this->soloByMonth($range)['2026-01']);
    }

    private function solo(string $firstDate, string $lastDate, ?int $supplier = null): SoloCountDto
    {
        return $this->totals()->sum(self::range($firstDate, $lastDate), $supplier)->solo();
    }

    /**
     * @return array<string, SoloCountDto>
     */
    private function soloByMonth(DateRangeDto $range, ?int $supplier = null): array
    {
        return array_map(static fn (DayTotalsDto $totals): SoloCountDto => $totals->solo(), $this->totals()->sumByMonth($range, $supplier));
    }

    /**
     * Saves the runs, and builds the totals of the dates of the tests, as an import does.
     */
    private function totals(): RouteDayTotalsRepository
    {
        $this->entityManager->flush();
        $builder = self::getContainer()->get(RouteDayTotalsBuilder::class);
        self::assertInstanceOf(RouteDayTotalsBuilder::class, $builder);
        $builder->rebuild(ImportRangeDto::create(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-03-31'), 90));
        $totals = $this->entityManager->getRepository(RouteDayTotals::class);
        self::assertInstanceOf(RouteDayTotalsRepository::class, $totals);

        return $totals;
    }

    private static function range(string $firstDate, string $lastDate): DateRangeDto
    {
        return DateRangeDto::create(new \DateTimeImmutable($firstDate), new \DateTimeImmutable($lastDate));
    }

    private function route(string $reference = 'R', bool $ignored = false, ?Supplier $supplier = null): Route
    {
        $subAgreement = new SubAgreement()->setReference($reference)->setIgnored($ignored)->setSupplier($supplier);
        $this->entityManager->persist($subAgreement);
        $route = new Route($reference.'-01', $subAgreement);
        $this->entityManager->persist($route);

        return $route;
    }

    private function routeRun(Route $route, string $date, int $run, ?int $peak, bool $cancelled = false): void
    {
        $this->entityManager->persist(new RouteRun($route, new \DateTimeImmutable($date), $run, new RouteRunDto(null, null, null, null, null, null, $cancelled, $peak)));
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
