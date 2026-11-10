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
use App\Entity\SubAgreementSupplierAssignment;
use App\Entity\Supplier;
use App\Import\DrivingDataImporter;
use App\Import\Extract\StopRowDto;
use App\Import\ImportRangeDto;
use App\Import\Mapping\MappingProvider;
use App\Import\Mapping\StopStatus;
use App\Import\Mapping\StopType;
use App\Import\RouteBuilder;
use App\Report\DateRangeDto;
use App\Report\DelayBandsDto;
use App\Report\PunctualityCalculator;
use App\Report\PunctualityCountDto;
use App\Report\PunctualityFiguresDto;
use App\Report\PunctualityReportDto;
use App\Report\RoutePunctualityDto;
use App\Report\SupplierPunctualityDto;
use App\Repository\RouteRepository;
use App\Repository\RouteRunRepository;
use App\Repository\StopRowRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks the four punctuality measures, the delay bands of the last drop-off and the worst
 * routes, in total and for each supplier on each date.
 */
final class PunctualityCalculatorTest extends KernelTestCase
{
    private const string SCENARIOS = __DIR__.'/../../fixtures/driving_data';

    private EntityManagerInterface $entityManager;

    private ?ImportBatch $batch = null;

    private int $rowKey = 0;

    protected function setUp(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }

    /**
     * The expected values are in the "expected" section of the scenario. The routes of the
     * scenario have no supplier.
     */
    public function testBaseScenario(): void
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

        $report = $this->calculate('2026-01-05', '2026-01-05');

        /** @var array{pickup_per_citizen: array{int, int}, drop_off_per_citizen: array{int, int}, pickup_per_run: array{int, int}, drop_off_per_run: array{int, int}, bands: array{int, int, int, int}} $expected */
        $expected = $scenario->expected['punctuality'];
        $figures = new PunctualityFiguresDto(
            new PunctualityCountDto(...$expected['pickup_per_citizen']),
            new PunctualityCountDto(...$expected['drop_off_per_citizen']),
            new PunctualityCountDto(...$expected['pickup_per_run']),
            new PunctualityCountDto(...$expected['drop_off_per_run']),
            new DelayBandsDto(...$expected['bands']),
        );
        self::assertEquals($figures, $report->total);
        self::assertEquals([new SupplierPunctualityDto(null, $figures)], $report->suppliers);
    }

    /**
     * A delay on the edge of two bands is in the lower band. One second more is in the higher
     * band.
     */
    public function testDelayOnTheEdgeOfABandIsInTheLowerBand(): void
    {
        $route = $this->route('E-01', $this->subAgreement('E'));
        foreach ([300, 301, 900, 901, 1800, 1801] as $index => $delay) {
            $this->stop($route, '2026-01-05', $index + 1, StopType::Pickup, StopStatus::Completed, 0);
            $this->stop($route, '2026-01-05', $index + 1, StopType::DropOff, StopStatus::Completed, $delay, 1800);
        }

        $report = $this->calculate('2026-01-05', '2026-01-05');

        self::assertEquals(new DelayBandsDto(1, 2, 2, 1), $report->total->bands);
        self::assertEquals(new PunctualityCountDto(1, 6), $report->total->dropOffPerRun);
        self::assertEquals(new PunctualityCountDto(1, 6), $report->total->dropOffPerCitizen);
        self::assertEquals(new PunctualityCountDto(6, 6), $report->total->pickupPerCitizen);
    }

    /**
     * A cancelled stop and a stop with no registration are not measured, also with an actual time.
     * A completed stop with no actual time is not measured either.
     */
    public function testStopsThatAreNotMeasured(): void
    {
        $route = $this->route('N-01', $this->subAgreement('N'));
        $this->stop($route, '2026-01-05', 1, StopType::Pickup, StopStatus::Cancelled, 1200);
        $this->stop($route, '2026-01-05', 1, StopType::Pickup, StopStatus::Completed, 0, 300);
        $this->stop($route, '2026-01-05', 1, StopType::Pickup, StopStatus::Completed, null, 600);
        $this->stop($route, '2026-01-05', 1, StopType::DropOff, StopStatus::Completed, 60, 1800);
        $this->stop($route, '2026-01-05', 1, StopType::DropOff, StopStatus::Pending, 1200, 2400);

        $report = $this->calculate('2026-01-05', '2026-01-05');

        self::assertEquals(new PunctualityCountDto(1, 1), $report->total->pickupPerCitizen);
        self::assertEquals(new PunctualityCountDto(1, 1), $report->total->dropOffPerCitizen);
        // The first pickup that is not cancelled is measured. The last drop-off is pending.
        self::assertEquals(new PunctualityCountDto(1, 1), $report->total->pickupPerRun);
        self::assertEquals(new PunctualityCountDto(0, 0), $report->total->dropOffPerRun);
        self::assertEquals(new DelayBandsDto(0, 0, 0, 0), $report->total->bands);
    }

    /**
     * The supplier changes on 6 January. The figures of 5 January belong to the first supplier,
     * and the figures of 6 January to the second. The suppliers are in the order of the name, and
     * the routes with no supplier are last.
     */
    public function testSupplierChangeSplitsTheFiguresOnTheDate(): void
    {
        $first = $this->supplier('Vikingbus');
        $second = $this->supplier('Aarhus Taxa');
        $route = $this->route('S-01', $this->subAgreement('S', [
            [$first, '2026-01-01', '2026-01-05'],
            [$second, '2026-01-06', null],
        ]));
        $unknown = $this->route('U-01', $this->subAgreement('U'));
        // 5 January: the pickup is late, and the drop-off is on time.
        $this->stop($route, '2026-01-05', 1, StopType::Pickup, StopStatus::Completed, 400);
        $this->stop($route, '2026-01-05', 1, StopType::DropOff, StopStatus::Completed, 0, 1800);
        // 6 January: the pickup is on time, and the drop-off is late.
        $this->stop($route, '2026-01-06', 1, StopType::Pickup, StopStatus::Completed, 0);
        $this->stop($route, '2026-01-06', 1, StopType::DropOff, StopStatus::Completed, 1000, 1800);
        $this->stop($unknown, '2026-01-05', 1, StopType::Pickup, StopStatus::Completed, 0);
        $this->stop($unknown, '2026-01-05', 1, StopType::DropOff, StopStatus::Completed, 0, 1800);

        $report = $this->calculate('2026-01-05', '2026-01-06');

        $lateDropOff = new PunctualityFiguresDto(
            new PunctualityCountDto(1, 1),
            new PunctualityCountDto(0, 1),
            new PunctualityCountDto(1, 1),
            new PunctualityCountDto(0, 1),
            new DelayBandsDto(0, 0, 1, 0),
        );
        $latePickup = new PunctualityFiguresDto(
            new PunctualityCountDto(0, 1),
            new PunctualityCountDto(1, 1),
            new PunctualityCountDto(0, 1),
            new PunctualityCountDto(1, 1),
            new DelayBandsDto(1, 0, 0, 0),
        );
        $onTime = new PunctualityFiguresDto(
            new PunctualityCountDto(1, 1),
            new PunctualityCountDto(1, 1),
            new PunctualityCountDto(1, 1),
            new PunctualityCountDto(1, 1),
            new DelayBandsDto(1, 0, 0, 0),
        );
        self::assertEquals([
            new SupplierPunctualityDto('Aarhus Taxa', $lateDropOff),
            new SupplierPunctualityDto('Vikingbus', $latePickup),
            new SupplierPunctualityDto(null, $onTime),
        ], $report->suppliers);
        self::assertEquals($lateDropOff->add($latePickup)->add($onTime), $report->total);
        // The route is in the list one time for each supplier.
        self::assertEquals([
            new RoutePunctualityDto('S-01', 'Aarhus Taxa', new PunctualityCountDto(0, 1)),
            new RoutePunctualityDto('S-01', 'Vikingbus', new PunctualityCountDto(1, 1)),
            new RoutePunctualityDto('U-01', null, new PunctualityCountDto(1, 1)),
        ], $report->worstRoutes);
    }

    /**
     * The figures of a supplier and of a route add all the dates of the range. On the same
     * punctuality and the same number of runs, the supplier name decides the order of one route,
     * and the route with no supplier is first.
     */
    public function testFiguresAddTheDatesOfTheRange(): void
    {
        $vikingbus = $this->supplier('Vikingbus');
        $aarhusTaxa = $this->supplier('Aarhus Taxa');
        $onTime = $this->route('S-01', $this->subAgreement('S', [[$vikingbus, '2026-01-01', null]]));
        $late = $this->route('T-01', $this->subAgreement('T', [
            [$vikingbus, '2026-01-01', '2026-01-05'],
            [$aarhusTaxa, '2026-01-06', '2026-01-06'],
        ]));
        foreach (['2026-01-05', '2026-01-06'] as $date) {
            $this->stop($onTime, $date, 1, StopType::Pickup, StopStatus::Completed, 0);
            $this->stop($onTime, $date, 1, StopType::DropOff, StopStatus::Completed, 0, 1800);
        }
        foreach (['2026-01-05', '2026-01-06', '2026-01-07'] as $date) {
            $this->stop($late, $date, 1, StopType::Pickup, StopStatus::Completed, 0);
            $this->stop($late, $date, 1, StopType::DropOff, StopStatus::Completed, 600, 1800);
        }

        $report = $this->calculate('2026-01-05', '2026-01-07');

        self::assertEquals([
            'Aarhus Taxa' => new PunctualityCountDto(0, 1),
            'Vikingbus' => new PunctualityCountDto(2, 3),
            '' => new PunctualityCountDto(0, 1),
        ], array_column(array_map(
            static fn (SupplierPunctualityDto $supplier): array => [$supplier->supplier ?? '', $supplier->figures->dropOffPerRun],
            $report->suppliers,
        ), 1, 0));
        self::assertEquals([
            new RoutePunctualityDto('T-01', null, new PunctualityCountDto(0, 1)),
            new RoutePunctualityDto('T-01', 'Aarhus Taxa', new PunctualityCountDto(0, 1)),
            new RoutePunctualityDto('T-01', 'Vikingbus', new PunctualityCountDto(0, 1)),
            new RoutePunctualityDto('S-01', 'Vikingbus', new PunctualityCountDto(2, 2)),
        ], $report->worstRoutes);
    }

    /**
     * The worst routes have the lowest punctuality at the last drop-off. On the same punctuality,
     * the route with more runs is first, and then the route name decides. Only 10 routes are
     * given.
     */
    public function testWorstRoutes(): void
    {
        $subAgreement = $this->subAgreement('W');
        $runs = [
            'W-01' => [false],
            'W-02' => [false, false],
            'W-03' => [true, false],
            'W-04' => [false, true],
        ];
        for ($number = 5; $number <= 12; ++$number) {
            $runs[\sprintf('W-%02d', $number)] = [true];
        }
        foreach ($runs as $name => $onTime) {
            $route = $this->route($name, $subAgreement);
            foreach ($onTime as $index => $isOnTime) {
                $this->stop($route, '2026-01-05', $index + 1, StopType::Pickup, StopStatus::Completed, 0);
                $this->stop($route, '2026-01-05', $index + 1, StopType::DropOff, StopStatus::Completed, $isOnTime ? 0 : 600, 1800);
            }
        }

        $report = $this->calculate('2026-01-05', '2026-01-05');

        self::assertSame(
            ['W-02', 'W-01', 'W-03', 'W-04', 'W-05', 'W-06', 'W-07', 'W-08', 'W-09', 'W-10'],
            array_map(static fn (RoutePunctualityDto $route): string => $route->route, $report->worstRoutes),
        );
        self::assertEquals(new RoutePunctualityDto('W-02', null, new PunctualityCountDto(0, 2)), $report->worstRoutes[0]);
        self::assertEquals(new RoutePunctualityDto('W-03', null, new PunctualityCountDto(1, 2)), $report->worstRoutes[2]);
    }

    /**
     * A route with no measured last drop-off is not in the list of the worst routes.
     */
    public function testRouteWithNoMeasuredLastDropOffIsNotAWorstRoute(): void
    {
        $route = $this->route('P-01', $this->subAgreement('P'));
        $this->stop($route, '2026-01-05', 1, StopType::Pickup, StopStatus::Completed, 0);
        $this->stop($route, '2026-01-05', 1, StopType::DropOff, StopStatus::Pending, null, 1800);

        $report = $this->calculate('2026-01-05', '2026-01-05');

        self::assertSame([], $report->worstRoutes);
        self::assertEquals(new PunctualityCountDto(1, 1), $report->total->pickupPerRun);
    }

    /**
     * The rows outside the range are not counted.
     */
    public function testRangeWithNoDataHasNoValues(): void
    {
        $route = $this->route('R-01', $this->subAgreement('R'));
        $this->stop($route, '2026-01-04', 1, StopType::Pickup, StopStatus::Completed, 0);
        $this->stop($route, '2026-01-07', 1, StopType::DropOff, StopStatus::Completed, 0, 1800);

        $report = $this->calculate('2026-01-05', '2026-01-06');

        self::assertEquals(PunctualityFiguresDto::empty(), $report->total);
        foreach ([$report->total->pickupPerCitizen, $report->total->dropOffPerCitizen, $report->total->pickupPerRun, $report->total->dropOffPerRun] as $count) {
            self::assertNull($count->percent());
        }
        self::assertSame([], $report->suppliers);
        self::assertSame([], $report->worstRoutes);
    }

    private function calculate(string $firstDate, string $lastDate): PunctualityReportDto
    {
        $this->entityManager->flush();
        $builder = self::getContainer()->get(RouteBuilder::class);
        self::assertInstanceOf(RouteBuilder::class, $builder);
        $builder->rebuild(ImportRangeDto::create(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-01-31'), 31));

        $stopRows = $this->entityManager->getRepository(StopRow::class);
        self::assertInstanceOf(StopRowRepository::class, $stopRows);
        $routeRuns = $this->entityManager->getRepository(RouteRun::class);
        self::assertInstanceOf(RouteRunRepository::class, $routeRuns);
        $routes = $this->entityManager->getRepository(Route::class);
        self::assertInstanceOf(RouteRepository::class, $routes);

        // The container removes a private service that no other service uses yet.
        return new PunctualityCalculator($stopRows, $routeRuns, $routes, 5)
            ->calculate(DateRangeDto::create(new \DateTimeImmutable($firstDate), new \DateTimeImmutable($lastDate)));
    }

    private function supplier(string $name): Supplier
    {
        $supplier = new Supplier()->setName($name);
        $this->entityManager->persist($supplier);

        return $supplier;
    }

    /**
     * @param list<array{Supplier, string, ?string}> $assignments the supplier, the first date and the last date
     */
    private function subAgreement(string $reference, array $assignments = []): SubAgreement
    {
        $subAgreement = new SubAgreement()->setReference($reference);
        $this->entityManager->persist($subAgreement);
        foreach ($assignments as [$supplier, $validFrom, $validTo]) {
            $this->entityManager->persist(new SubAgreementSupplierAssignment(
                $subAgreement,
                $supplier,
                new \DateTimeImmutable($validFrom),
                null === $validTo ? null : new \DateTimeImmutable($validTo),
            ));
        }

        return $subAgreement;
    }

    private function route(string $name, SubAgreement $subAgreement): Route
    {
        $route = new Route($name, $subAgreement);
        $this->entityManager->persist($route);

        return $route;
    }

    /**
     * Stores one stop. The planned time is 07:00 on the date, plus the given seconds.
     *
     * @param ?int $delaySeconds the actual time minus the planned time, or null for no actual time
     * @param int  $plannedAfter the seconds after 07:00 of the planned time
     */
    private function stop(Route $route, string $date, int $run, StopType $type, StopStatus $status, ?int $delaySeconds, int $plannedAfter = 0): void
    {
        $serviceDate = new \DateTimeImmutable($date);
        $planned = $serviceDate->setTime(7, 0)->modify(\sprintf('+%d seconds', $plannedAfter));
        $row = new StopRowDto(
            rowNumber: ++$this->rowKey,
            rowKey: 'S-'.$this->rowKey,
            route: $route->getSourceKey(),
            personId: 'P-'.$this->rowKey,
            serviceDate: $serviceDate,
            run: $run,
            stopType: $type,
            stopStatus: $status,
            passengersAfter: null,
            nextDistanceKm: null,
            nextDurationMin: null,
            plannedTime: $planned,
            actualTime: null === $delaySeconds ? null : $planned->modify(\sprintf('%+d seconds', $delaySeconds)),
            plannedSolo: null,
        );
        $this->entityManager->persist(new StopRow($row, 'default', $route, $this->batch()));
    }

    private function batch(): ImportBatch
    {
        if (null === $this->batch) {
            $this->batch = new ImportBatch('default', 'test.xlsx', null, new \DateTimeImmutable(), ImportRangeDto::create(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-01-31'), 31));
            $this->entityManager->persist($this->batch);
        }

        return $this->batch;
    }
}
