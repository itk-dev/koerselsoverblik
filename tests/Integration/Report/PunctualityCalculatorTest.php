<?php

declare(strict_types=1);

namespace App\Tests\Integration\Report;

use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Entity\ImportBatch;
use App\Entity\LocationDayTotals;
use App\Entity\Route;
use App\Entity\RouteDayTotals;
use App\Entity\StopRow;
use App\Entity\SubAgreement;
use App\Entity\Supplier;
use App\Import\DrivingDataImporter;
use App\Import\Extract\StopRowDto;
use App\Import\ImportRangeDto;
use App\Import\LocationDayTotalsBuilder;
use App\Import\Mapping\MappingProvider;
use App\Import\Mapping\StopStatus;
use App\Import\Mapping\StopType;
use App\Import\RouteBuilder;
use App\Import\RouteDayTotalsBuilder;
use App\Report\DateRangeDto;
use App\Report\DelayBandsDto;
use App\Report\LocationPunctualityDto;
use App\Report\PunctualityCalculator;
use App\Report\PunctualityCountDto;
use App\Report\PunctualityFiguresDto;
use App\Report\PunctualityReportDto;
use App\Report\RoutePunctualityDto;
use App\Report\SupplierPunctualityDto;
use App\Report\WorstLocationsDto;
use App\Repository\LocationDayTotalsRepository;
use App\Repository\RouteDayTotalsRepository;
use App\Repository\RouteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks the four punctuality measures, the delay bands of the last drop-off and the worst
 * routes, in total and for each supplier.
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
        /** @var array<string, array{int, int}> $locations */
        $locations = $scenario->expected['locations'];
        self::assertEquals(
            array_map(static fn (string $location, array $count): LocationPunctualityDto => new LocationPunctualityDto($location, new PunctualityCountDto(...$count)), array_keys($locations), $locations),
            $report->worstLocations->locations,
        );
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
     * The figures of a route belong to the supplier of its sub-agreement. The suppliers are in
     * the order of the name, and the routes of a sub-agreement with no supplier are last.
     */
    public function testFiguresOfEachSupplier(): void
    {
        // The supplier B is saved first, so the order of the name is not the order of the save.
        $routeOfB = $this->route('S-01', $this->subAgreement('S', $this->supplier('Vognmand B')));
        $routeOfA = $this->route('T-01', $this->subAgreement('T', $this->supplier('Vognmand A')));
        $unknown = $this->route('U-01', $this->subAgreement('U'));
        // The pickup is late, and the drop-off is on time.
        $this->stop($routeOfB, '2026-01-05', 1, StopType::Pickup, StopStatus::Completed, 400);
        $this->stop($routeOfB, '2026-01-05', 1, StopType::DropOff, StopStatus::Completed, 0, 1800);
        // The pickup is on time, and the drop-off is late.
        $this->stop($routeOfA, '2026-01-06', 1, StopType::Pickup, StopStatus::Completed, 0);
        $this->stop($routeOfA, '2026-01-06', 1, StopType::DropOff, StopStatus::Completed, 1000, 1800);
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
            new SupplierPunctualityDto('Vognmand A', $lateDropOff),
            new SupplierPunctualityDto('Vognmand B', $latePickup),
            new SupplierPunctualityDto(null, $onTime),
        ], $report->suppliers);
        self::assertEquals($lateDropOff->add($latePickup)->add($onTime), $report->total);
        // S-01 and U-01 have no late run at the last drop-off, so they are not worst routes.
        self::assertEquals([
            new RoutePunctualityDto('T-01', 'Vognmand A', new PunctualityCountDto(0, 1)),
        ], $report->worstRoutes);
    }

    /**
     * With a supplier, the total, the worst routes and the worst locations are of that supplier
     * only. The supplier table always shows all suppliers.
     */
    public function testFiguresOfOneSupplier(): void
    {
        $supplierA = $this->supplier('Vognmand A');
        $routeOfA = $this->route('T-01', $this->subAgreement('T', $supplierA));
        $routeOfB = $this->route('S-01', $this->subAgreement('S', $this->supplier('Vognmand B')));
        $unknown = $this->route('U-01', $this->subAgreement('U'));
        $this->stop($routeOfA, '2026-01-06', 1, StopType::DropOff, StopStatus::Completed, 1000, location: 'Skole 1');
        $this->stop($routeOfB, '2026-01-05', 1, StopType::DropOff, StopStatus::Completed, 0, location: 'Skole 1');
        $this->stop($unknown, '2026-01-05', 1, StopType::DropOff, StopStatus::Completed, 0, location: 'Skole 2');
        $this->entityManager->flush();

        $report = $this->calculate('2026-01-05', '2026-01-06', $supplierA->getId());

        self::assertEquals(new PunctualityCountDto(0, 1), $report->total->dropOffPerRun);
        self::assertSame(['Vognmand A', 'Vognmand B', null], array_map(static fn (SupplierPunctualityDto $item): ?string => $item->supplier, $report->suppliers));
        self::assertSame(['T-01'], array_map(static fn (RoutePunctualityDto $route): string => $route->route, $report->worstRoutes));
        self::assertEquals([new LocationPunctualityDto('Skole 1', new PunctualityCountDto(0, 1))], $report->worstLocations->locations);
    }

    /**
     * The figures of a supplier add all the routes and all the dates of the range. The figures of
     * a route add all the dates of the range, so the route with more late runs is first.
     */
    public function testFiguresAddTheRoutesAndTheDatesOfTheRange(): void
    {
        $supplier = $this->supplier('Vognmand A');
        $onTime = $this->route('S-01', $this->subAgreement('S', $supplier));
        $late = $this->route('T-01', $this->subAgreement('T', $supplier));
        $unknown = $this->route('U-01', $this->subAgreement('U'));
        foreach (['2026-01-05', '2026-01-06'] as $date) {
            $this->stop($onTime, $date, 1, StopType::Pickup, StopStatus::Completed, 0);
            $this->stop($onTime, $date, 1, StopType::DropOff, StopStatus::Completed, 0, 1800);
        }
        foreach (['2026-01-05', '2026-01-06', '2026-01-07'] as $date) {
            $this->stop($late, $date, 1, StopType::Pickup, StopStatus::Completed, 0);
            $this->stop($late, $date, 1, StopType::DropOff, StopStatus::Completed, 600, 1800);
        }
        $this->stop($unknown, '2026-01-07', 1, StopType::Pickup, StopStatus::Completed, 0);
        $this->stop($unknown, '2026-01-07', 1, StopType::DropOff, StopStatus::Completed, 600, 1800);

        $report = $this->calculate('2026-01-05', '2026-01-07');

        self::assertEquals([
            'Vognmand A' => new PunctualityCountDto(2, 5),
            '' => new PunctualityCountDto(0, 1),
        ], array_column(array_map(
            static fn (SupplierPunctualityDto $supplier): array => [$supplier->supplier ?? '', $supplier->figures->dropOffPerRun],
            $report->suppliers,
        ), 1, 0));
        self::assertEquals([
            new RoutePunctualityDto('T-01', 'Vognmand A', new PunctualityCountDto(0, 3)),
            new RoutePunctualityDto('U-01', null, new PunctualityCountDto(0, 1)),
        ], $report->worstRoutes);
    }

    /**
     * The routes of an ignored sub-agreement add nothing: no stops, no runs, no supplier and no
     * worst route. When the flag is cleared, the figures come back.
     */
    public function testIgnoredSubAgreementAddsNothing(): void
    {
        $included = $this->route('A-01', $this->subAgreement('A', $this->supplier('Vognmand A')));
        $ignoredSubAgreement = $this->subAgreement('I', $this->supplier('Vognmand B'))->setIgnored(true);
        $ignored = $this->route('I-01', $ignoredSubAgreement);
        $this->stop($included, '2026-01-05', 1, StopType::Pickup, StopStatus::Completed, 0);
        $this->stop($included, '2026-01-05', 1, StopType::DropOff, StopStatus::Completed, 0, 1800);
        $this->stop($ignored, '2026-01-05', 1, StopType::Pickup, StopStatus::Completed, 600);
        $this->stop($ignored, '2026-01-05', 1, StopType::DropOff, StopStatus::Completed, 600, 1800);
        $onTime = new PunctualityFiguresDto(
            new PunctualityCountDto(1, 1),
            new PunctualityCountDto(1, 1),
            new PunctualityCountDto(1, 1),
            new PunctualityCountDto(1, 1),
            new DelayBandsDto(1, 0, 0, 0),
        );
        $late = new PunctualityFiguresDto(
            new PunctualityCountDto(0, 1),
            new PunctualityCountDto(0, 1),
            new PunctualityCountDto(0, 1),
            new PunctualityCountDto(0, 1),
            new DelayBandsDto(0, 1, 0, 0),
        );

        $report = $this->calculate('2026-01-05', '2026-01-05');

        self::assertEquals($onTime, $report->total);
        self::assertEquals([new SupplierPunctualityDto('Vognmand A', $onTime)], $report->suppliers);
        self::assertSame([], $report->worstRoutes);

        $ignoredSubAgreement->setIgnored(false);
        $report = $this->calculate('2026-01-05', '2026-01-05');

        self::assertEquals($onTime->add($late), $report->total);
        self::assertEquals([
            new SupplierPunctualityDto('Vognmand A', $onTime),
            new SupplierPunctualityDto('Vognmand B', $late),
        ], $report->suppliers);
        self::assertEquals([
            new RoutePunctualityDto('I-01', 'Vognmand B', new PunctualityCountDto(0, 1)),
        ], $report->worstRoutes);
    }

    /**
     * The worst routes have the most late runs at the last drop-off. On the same number, the route
     * with the lower punctuality is first, and then the route name decides. A route with no late
     * run is not in the list. Only 10 routes are given.
     */
    public function testWorstRoutes(): void
    {
        $subAgreement = $this->subAgreement('W');
        $runs = [
            'W-00' => [true],
            'W-01' => [false],
            'W-02' => [false, false],
            'W-03' => [true, false],
            'W-04' => [false, true],
        ];
        for ($number = 5; $number <= 12; ++$number) {
            $runs[\sprintf('W-%02d', $number)] = [true, false];
        }
        // 3 late runs of 10 (70 %) are worse than 1 late run of 1 (0 %).
        $runs['W-13'] = [false, false, false, true, true, true, true, true, true, true];
        foreach ($runs as $name => $onTime) {
            $route = $this->route($name, $subAgreement);
            foreach ($onTime as $index => $isOnTime) {
                $this->stop($route, '2026-01-05', $index + 1, StopType::Pickup, StopStatus::Completed, 0);
                $this->stop($route, '2026-01-05', $index + 1, StopType::DropOff, StopStatus::Completed, $isOnTime ? 0 : 600, 1800);
            }
        }

        $report = $this->calculate('2026-01-05', '2026-01-05');

        self::assertSame(
            ['W-13', 'W-02', 'W-01', 'W-03', 'W-04', 'W-05', 'W-06', 'W-07', 'W-08', 'W-09'],
            array_map(static fn (RoutePunctualityDto $route): string => $route->route, $report->worstRoutes),
        );
        self::assertEquals(new RoutePunctualityDto('W-13', null, new PunctualityCountDto(7, 10)), $report->worstRoutes[0]);
        self::assertEquals(new RoutePunctualityDto('W-03', null, new PunctualityCountDto(1, 2)), $report->worstRoutes[3]);
    }

    /**
     * The worst locations have the lowest punctuality of the pickups and the drop-offs together.
     * On the same punctuality, the location with more measured stops is first, and then the name
     * decides. A location adds the stops of all its routes and dates. Only 10 locations are given.
     */
    public function testWorstLocations(): void
    {
        $first = $this->route('L-01', $this->subAgreement('L'));
        $second = $this->route('M-01', $this->subAgreement('M'));
        $delays = [
            'Skole 01' => [600],
            'Skole 02' => [600, 600],
            'Skole 03' => [0, 600],
            'Skole 04' => [600, 0],
        ];
        for ($number = 5; $number <= 12; ++$number) {
            $delays[\sprintf('Skole %02d', $number)] = [0];
        }
        foreach ($delays as $location => $locationDelays) {
            foreach ($locationDelays as $index => $delay) {
                // The stops of a location are on two routes and two dates.
                $this->stop(0 === $index ? $first : $second, 0 === $index ? '2026-01-05' : '2026-01-06', 1, 0 === $index ? StopType::Pickup : StopType::DropOff, StopStatus::Completed, $delay, location: $location);
            }
        }

        $report = $this->calculate('2026-01-05', '2026-01-06');

        self::assertSame(
            ['Skole 02', 'Skole 01', 'Skole 03', 'Skole 04', 'Skole 05', 'Skole 06', 'Skole 07', 'Skole 08', 'Skole 09', 'Skole 10'],
            array_map(static fn (LocationPunctualityDto $location): string => $location->location, $report->worstLocations->locations),
        );
        self::assertEquals(new LocationPunctualityDto('Skole 02', new PunctualityCountDto(0, 2)), $report->worstLocations->locations[0]);
        self::assertEquals(new LocationPunctualityDto('Skole 03', new PunctualityCountDto(1, 2)), $report->worstLocations->locations[2]);
        // The 2 locations after the first 10 have enough stops, so they are not left out.
        self::assertSame(0, $report->worstLocations->leftOut);
    }

    /**
     * A location with fewer measured stops than the minimum is not ranked, also at 0 %. The
     * report counts it. A location with exactly the minimum is ranked.
     */
    public function testLocationWithTooFewStopsIsLeftOut(): void
    {
        $route = $this->route('L-01', $this->subAgreement('L'));
        foreach ([600, 600] as $delay) {
            $this->stop($route, '2026-01-05', 1, StopType::DropOff, StopStatus::Completed, $delay, location: 'Skole 1');
        }
        foreach ([0, 0, 600] as $delay) {
            $this->stop($route, '2026-01-05', 1, StopType::DropOff, StopStatus::Completed, $delay, location: 'Skole 2');
        }

        $report = $this->calculator(3)->calculate(DateRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-05')));

        self::assertEquals(new WorstLocationsDto([new LocationPunctualityDto('Skole 2', new PunctualityCountDto(2, 3))], 1, 3), $report->worstLocations);
    }

    /**
     * The stops of an ignored sub-agreement add no location.
     */
    public function testIgnoredSubAgreementAddsNoLocation(): void
    {
        $included = $this->route('A-01', $this->subAgreement('A'));
        $ignored = $this->route('I-01', $this->subAgreement('I')->setIgnored(true));
        $this->stop($included, '2026-01-05', 1, StopType::DropOff, StopStatus::Completed, 0, location: 'Skole 1');
        $this->stop($ignored, '2026-01-05', 1, StopType::DropOff, StopStatus::Completed, 600, location: 'Skole 1');
        $this->stop($ignored, '2026-01-05', 1, StopType::DropOff, StopStatus::Completed, 600, location: 'Skole 2');

        $report = $this->calculate('2026-01-05', '2026-01-05');

        self::assertEquals([new LocationPunctualityDto('Skole 1', new PunctualityCountDto(1, 1))], $report->worstLocations->locations);
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
        $this->stop($route, '2026-01-04', 1, StopType::Pickup, StopStatus::Completed, 0, location: 'Skole 1');
        $this->stop($route, '2026-01-07', 1, StopType::DropOff, StopStatus::Completed, 0, 1800, 'Skole 1');

        $report = $this->calculate('2026-01-05', '2026-01-06');

        self::assertEquals(PunctualityFiguresDto::empty(), $report->total);
        foreach ([$report->total->pickupPerCitizen, $report->total->dropOffPerCitizen, $report->total->pickupPerRun, $report->total->dropOffPerRun] as $count) {
            self::assertNull($count->percent());
        }
        self::assertSame([], $report->suppliers);
        self::assertSame([], $report->worstRoutes);
        self::assertEquals(new WorstLocationsDto([], 0, 1), $report->worstLocations);
    }

    /**
     * The total of each month is the same as the total of the range of that month, with and with
     * no supplier. An ignored sub-agreement adds nothing. A month with no data has no item.
     */
    public function testTotalsOfEachMonth(): void
    {
        $supplier = $this->supplier('Vognmand A');
        $ofSupplier = $this->route('T-01', $this->subAgreement('T', $supplier));
        $other = $this->route('S-01', $this->subAgreement('S'));
        $ignored = $this->route('I-01', $this->subAgreement('I', $supplier)->setIgnored(true));
        $this->stop($ofSupplier, '2026-01-05', 1, StopType::Pickup, StopStatus::Completed, 0);
        $this->stop($ofSupplier, '2026-01-05', 1, StopType::DropOff, StopStatus::Completed, 0, 1800);
        $this->stop($other, '2026-01-31', 1, StopType::Pickup, StopStatus::Completed, 400);
        $this->stop($other, '2026-01-31', 1, StopType::DropOff, StopStatus::Completed, 1000, 1800);
        $this->stop($ofSupplier, '2026-02-01', 1, StopType::Pickup, StopStatus::Completed, 0);
        $this->stop($ofSupplier, '2026-02-01', 1, StopType::DropOff, StopStatus::Completed, 2000, 1800);
        $this->stop($ignored, '2026-02-02', 1, StopType::Pickup, StopStatus::Completed, 600);
        $this->stop($ignored, '2026-02-02', 1, StopType::DropOff, StopStatus::Completed, 600, 1800);
        $this->entityManager->flush();
        $calculator = $this->calculator();
        $range = DateRangeDto::create(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-03-31'));

        foreach ([null, $supplier->getId()] as $supplierId) {
            $totals = $calculator->totalsByMonth($range, $supplierId);

            self::assertSame(['2026-01', '2026-02'], array_keys($totals));
            self::assertEquals($this->calculate('2026-01-01', '2026-01-31', $supplierId)->total, $totals['2026-01']);
            self::assertEquals($this->calculate('2026-02-01', '2026-02-28', $supplierId)->total, $totals['2026-02']);
            self::assertEquals($this->calculate('2026-01-01', '2026-03-31', $supplierId)->total, $calculator->total($range, $supplierId));
        }
        self::assertEquals(new PunctualityCountDto(1, 2), $calculator->totalsByMonth($range)['2026-01']->dropOffPerRun);
        self::assertEquals(new DelayBandsDto(0, 0, 0, 1), $calculator->totalsByMonth($range)['2026-02']->bands);
    }

    public function testRangeWithNoDataHasNoTotals(): void
    {
        $range = DateRangeDto::create(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-01-31'));

        self::assertSame([], $this->calculator()->totalsByMonth($range));
        self::assertEquals(PunctualityFiguresDto::empty(), $this->calculator()->total($range));
    }

    private function calculate(string $firstDate, string $lastDate, ?int $supplier = null): PunctualityReportDto
    {
        return $this->calculator()->calculate(DateRangeDto::create(new \DateTimeImmutable($firstDate), new \DateTimeImmutable($lastDate)), $supplier);
    }

    /**
     * Saves the stops, and builds the route runs and the totals of January and February from
     * them, as an import does.
     *
     * @param int $locationMinStops the minimum number of measured stops of a ranked location
     */
    private function calculator(int $locationMinStops = 1): PunctualityCalculator
    {
        $this->entityManager->flush();
        $range = ImportRangeDto::create(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-02-28'), 59);
        $routeBuilder = self::getContainer()->get(RouteBuilder::class);
        self::assertInstanceOf(RouteBuilder::class, $routeBuilder);
        $routeBuilder->rebuild($range);
        $totalsBuilder = self::getContainer()->get(RouteDayTotalsBuilder::class);
        self::assertInstanceOf(RouteDayTotalsBuilder::class, $totalsBuilder);
        $totalsBuilder->rebuild($range);
        $locationTotalsBuilder = self::getContainer()->get(LocationDayTotalsBuilder::class);
        self::assertInstanceOf(LocationDayTotalsBuilder::class, $locationTotalsBuilder);
        $locationTotalsBuilder->rebuild($range);

        $totals = $this->entityManager->getRepository(RouteDayTotals::class);
        self::assertInstanceOf(RouteDayTotalsRepository::class, $totals);
        $locationTotals = $this->entityManager->getRepository(LocationDayTotals::class);
        self::assertInstanceOf(LocationDayTotalsRepository::class, $locationTotals);
        $routes = $this->entityManager->getRepository(Route::class);
        self::assertInstanceOf(RouteRepository::class, $routes);

        // The container removes a private service that no other service uses yet.
        return new PunctualityCalculator($totals, $locationTotals, $routes, $locationMinStops);
    }

    private function supplier(string $name): Supplier
    {
        $supplier = new Supplier()->setName($name);
        $this->entityManager->persist($supplier);

        return $supplier;
    }

    private function subAgreement(string $reference, ?Supplier $supplier = null): SubAgreement
    {
        $subAgreement = new SubAgreement()->setReference($reference)->setSupplier($supplier);
        $this->entityManager->persist($subAgreement);

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
     * @param ?int    $delaySeconds the actual time minus the planned time, or null for no actual time
     * @param int     $plannedAfter the seconds after 07:00 of the planned time
     * @param ?string $location     the location name, or null for no location
     */
    private function stop(Route $route, string $date, int $run, StopType $type, StopStatus $status, ?int $delaySeconds, int $plannedAfter = 0, ?string $location = null): void
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
            locationName: $location,
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
