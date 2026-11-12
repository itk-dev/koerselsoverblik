<?php

declare(strict_types=1);

namespace App\Tests\Unit\DataFixtures\DrivingData;

use App\DataFixtures\DrivingData\DatasetSettings;
use App\DataFixtures\DrivingData\DevDatasetGenerator;
use App\DataFixtures\DrivingData\ExtractRow;
use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\SchoolCalendar;
use App\Import\Mapping\MappingLoader;
use App\Import\SubAgreementReference;
use PHPUnit\Framework\TestCase;

/**
 * Checks the development dataset: one ISO week at a time, the same rows for the same seed, the
 * volume of the school calendar, and the punctuality that the settings give.
 */
final class DevDatasetGeneratorTest extends TestCase
{
    private const string DEFAULT_MAPPING = __DIR__.'/../../../../config/import/driving_data/default.yaml';

    /**
     * A school week: 2026-09-14 to 2026-09-20.
     */
    private const string SCHOOL_WEEK = '2026-09-14';

    /**
     * The summer holiday: 2026-07-13 to 2026-07-19.
     */
    private const string SUMMER_WEEK = '2026-07-13';

    /**
     * The autumn holiday: 2026-10-12 to 2026-10-18.
     */
    private const string AUTUMN_WEEK = '2026-10-12';

    public function testSameSeedGivesTheSameRows(): void
    {
        self::assertEquals(self::generate(self::AUTUMN_WEEK), self::generate(self::AUTUMN_WEEK));
    }

    public function testOtherSeedGivesOtherRows(): void
    {
        self::assertNotEquals(self::generate(self::AUTUMN_WEEK), self::generate(self::AUTUMN_WEEK, seed: 8));
    }

    public function testWeekHasRowsOnlyOnItsSevenDays(): void
    {
        $days = array_keys(self::rowsByDay(self::generate(self::SCHOOL_WEEK)));
        sort($days);

        self::assertSame('2026-09-14', $days[0]);
        self::assertLessThanOrEqual('2026-09-20', end($days));
        self::assertGreaterThanOrEqual(5, \count($days));
    }

    /**
     * A school day has the volume of a full day of school transport.
     */
    public function testSchoolDayHasTheVolumeOfTheSchoolTransport(): void
    {
        $rows = self::rowsByDay(self::generate(self::SCHOOL_WEEK))['2026-09-15'];

        self::assertGreaterThan(4500, \count($rows));
        self::assertLessThan(6500, \count($rows));
    }

    public function testWeekendHasOnlyBookedTrips(): void
    {
        $byDay = self::rowsByDay(self::generate(self::SCHOOL_WEEK));
        $weekend = [...$byDay['2026-09-19'] ?? [], ...$byDay['2026-09-20'] ?? []];

        foreach ($weekend as $row) {
            self::assertSame(DevDatasetGenerator::BOOKED_ROUTE, self::string($row, 'route'));
        }
    }

    /**
     * Group A drives almost nothing in the summer holiday. Group B still drives some routes.
     */
    public function testHolidaysHaveALowerVolume(): void
    {
        $school = \count(self::rowsByDay(self::generate(self::SCHOOL_WEEK))['2026-09-15']);
        $autumn = \count(self::rowsByDay(self::generate(self::AUTUMN_WEEK))['2026-10-13']);
        $summer = self::rowsByDay(self::generate(self::SUMMER_WEEK))['2026-07-14'];

        self::assertLessThan($school * 0.5, $autumn);
        self::assertLessThan($school * 0.4, \count($summer));
        $groups = array_count_values(array_map(static fn (ExtractRow $row): string => str_contains(self::string($row, 'route'), '_') ? 'B' : 'A', $summer));
        self::assertGreaterThan($groups['A'] ?? 0, $groups['B']);
    }

    /**
     * The route names have the two formats of the planning system, and the import finds a
     * sub-agreement for each of them.
     */
    public function testRouteNamesHaveTheTwoFormats(): void
    {
        $routes = array_unique(array_map(static fn (ExtractRow $row): string => self::string($row, 'route'), self::generate(self::SCHOOL_WEEK)));

        self::assertNotEmpty(preg_grep('/^[A-ZÆØÅ]{2}-[A-ZÆØÅ]{2}-\d{2}$/u', $routes));
        self::assertNotEmpty(preg_grep('/^[A-ZÆØÅ][a-zæøå]+_\d{2}$/u', $routes));
        self::assertContains(DevDatasetGenerator::BOOKED_ROUTE, $routes);
        $references = array_unique(array_map(SubAgreementReference::fromRouteName(...), $routes));
        self::assertGreaterThanOrEqual(6, \count($references));
    }

    /**
     * The real data has route names that are not routes: a misspelled booked-trips route, a name
     * with no separator, and notes. The dataset has them too, so that the pages must handle them.
     */
    public function testDatasetHasRouteNamesThatAreNotRoutes(): void
    {
        $routes = [];
        for ($monday = new \DateTimeImmutable('2026-08-31'); $monday < new \DateTimeImmutable('2026-12-07'); $monday = $monday->modify('+7 days')) {
            foreach (self::generate($monday->format('Y-m-d')) as $row) {
                $routes[self::string($row, 'route')] = true;
            }
        }

        self::assertArrayHasKey(DevDatasetGenerator::MISSPELLED_BOOKED_ROUTE, $routes);
        self::assertArrayHasKey(DevDatasetGenerator::ROUTE_WITH_NO_SEPARATOR, $routes);
        self::assertNotEmpty(array_intersect(DevDatasetGenerator::NOTE_ROUTES, array_keys($routes)));
    }

    public function testEachTripHasAPickupAndADropOff(): void
    {
        $types = array_count_values(array_map(static fn (ExtractRow $row): string => self::string($row, 'stop_type'), self::generate(self::SCHOOL_WEEK)));

        self::assertSame($types['pickup'], $types['drop_off']);
    }

    public function testEachRunEndsWithNoPassengers(): void
    {
        $last = [];
        foreach (self::generate(self::SCHOOL_WEEK) as $row) {
            $passengers = $row->values['passengers_after'];
            self::assertIsInt($passengers);
            $last[self::runKey($row)] = $passengers;
        }

        self::assertSame([0], array_values(array_unique($last)));
    }

    public function testRowsHaveCancelledAndPendingStops(): void
    {
        $statuses = array_count_values(array_map(static fn (ExtractRow $row): string => self::string($row, 'stop_status'), self::generate(self::SCHOOL_WEEK)));

        self::assertSame(['cancelled', 'completed', 'pending'], self::sortedKeys($statuses));
    }

    /**
     * The same citizens ride on the same route each day, as in the school transport.
     */
    public function testRouteHasTheSameCitizensEachDay(): void
    {
        $days = [];
        foreach (self::generate(self::SCHOOL_WEEK) as $row) {
            if ('Bakke_07' === $row->values['route']) {
                $days[self::string($row, 'person_id')][self::date($row, 'service_date')->format('Y-m-d')] = true;
            }
        }

        self::assertNotEmpty($days);
        self::assertLessThanOrEqual(DevDatasetGenerator::CITIZENS_PER_ROUTE, \count($days));
        self::assertNotEmpty(array_filter($days, static fn (array $dates): bool => \count($dates) >= 3));
    }

    /**
     * The rows have the fields of the prototype. The institution end of a trip has a location
     * name. Only a cancelled stop has a cancellation notice.
     */
    public function testRowsHaveTheFieldsOfThePrototype(): void
    {
        $rows = self::generate(self::SCHOOL_WEEK);
        $places = array_filter($rows, static fn (ExtractRow $row): bool => null !== $row->values['location_name']);

        self::assertGreaterThan(0.3 * \count($rows), \count($places));
        self::assertLessThan(0.7 * \count($rows), \count($places));
        foreach ($rows as $row) {
            self::assertIsInt($row->values['passengers']);
            self::assertIsFloat($row->values['price_per_minute']);
            self::assertIsBool($row->values['cancellation_in_time']);
            $cancelled = 'cancelled' === $row->values['stop_status'];
            self::assertSame($cancelled, \is_float($row->values['cancellation_notice_min']));
            if (!$cancelled) {
                self::assertFalse($row->values['cancellation_in_time']);
            }
        }
    }

    /**
     * The import of a week replaces only the rows of that week. A row id that is also in another
     * week then breaks the unique index of the row keys.
     */
    public function testRowIdsAreUniqueAcrossWeeks(): void
    {
        $rows = [...self::generate(self::SCHOOL_WEEK), ...self::generate('2026-09-21')];

        $ids = array_map(static fn (ExtractRow $row): ?string => $row->rowId, $rows);

        self::assertNotContains(null, $ids);
        self::assertCount(\count($rows), array_unique($ids));
    }

    /**
     * The punctuality page shows the last drop-offs in four delay bands. The dataset has a last
     * drop-off in each band, so that the page shows all of them.
     */
    public function testLastDropOffsAreInAllDelayBands(): void
    {
        $bands = array_count_values(array_map(static fn (int $delay): string => match (true) {
            $delay <= 300 => 'on time',
            $delay <= 900 => 'more than 5 to 15',
            $delay <= 1800 => 'more than 15 to 30',
            default => 'more than 30',
        }, self::lastDropOffDelays(self::generate(self::SCHOOL_WEEK))));

        self::assertSame(['more than 15 to 30', 'more than 30', 'more than 5 to 15', 'on time'], self::sortedKeys($bands));
    }

    /**
     * The settings give the share of the runs with an on-time last drop-off: the start value in
     * the first month, and the trend for each month after it.
     */
    public function testSettingsGiveThePunctuality(): void
    {
        self::assertEqualsWithDelta(80.0, self::onTimePercent(self::generate(self::SCHOOL_WEEK, start: 80.0, trend: 0.0)), 2.5);
        self::assertEqualsWithDelta(60.0, self::onTimePercent(self::generate(self::SCHOOL_WEEK, start: 80.0, trend: -2.5)), 2.5);
        self::assertEqualsWithDelta(95.0, self::onTimePercent(self::generate(self::SCHOOL_WEEK, start: 55.0, trend: 5.0)), 2.5);
    }

    /**
     * The rows have values that the default mapping accepts.
     */
    public function testDefaultMappingCanWriteTheRows(): void
    {
        $path = sys_get_temp_dir().'/'.uniqid('dev_dataset_', true).'.xlsx';

        new ExtractWriter()->write($path, new MappingLoader()->load(self::DEFAULT_MAPPING), self::generate(self::AUTUMN_WEEK));

        self::assertFileExists($path);
        unlink($path);
    }

    /**
     * The first month of the settings is January 2026, so that a week in September 2026 is month
     * 8 after it.
     *
     * @return list<ExtractRow>
     */
    private static function generate(string $monday, int $seed = 7, float $start = 70.0, float $trend = 0.0): array
    {
        $settings = new DatasetSettings($seed, new \DateTimeImmutable('2026-01-01'), $start, $trend);

        return new DevDatasetGenerator(new SchoolCalendar())->week($settings, new \DateTimeImmutable($monday));
    }

    /**
     * @param list<ExtractRow> $rows
     *
     * @return array<string, list<ExtractRow>>
     */
    private static function rowsByDay(array $rows): array
    {
        $byDay = [];
        foreach ($rows as $row) {
            $byDay[self::date($row, 'service_date')->format('Y-m-d')][] = $row;
        }

        return $byDay;
    }

    /**
     * Gives the runs with a last drop-off at most 5 minutes late, in percent.
     *
     * @param list<ExtractRow> $rows
     */
    private static function onTimePercent(array $rows): float
    {
        $delays = self::lastDropOffDelays($rows);

        return \count(array_filter($delays, static fn (int $delay): bool => $delay <= 300)) / \count($delays) * 100;
    }

    /**
     * Gives the delay in seconds of the last completed drop-off of each run.
     *
     * @param list<ExtractRow> $rows
     *
     * @return array<string, int> keyed by the route, the date and the run
     */
    private static function lastDropOffDelays(array $rows): array
    {
        $delays = [];
        foreach ($rows as $row) {
            if ('drop_off' !== self::string($row, 'stop_type') || 'completed' !== self::string($row, 'stop_status')) {
                continue;
            }
            $serviceDate = self::date($row, 'service_date');
            $planned = $row->values['planned_time'];
            self::assertInstanceOf(\DateInterval::class, $planned);
            // The drop-offs of a run are in the order of their planned times.
            $delays[self::runKey($row)] = self::date($row, 'actual_time')->getTimestamp() - $serviceDate->add($planned)->getTimestamp();
        }

        return $delays;
    }

    private static function runKey(ExtractRow $row): string
    {
        $run = $row->values['run'];
        self::assertIsInt($run);

        return self::string($row, 'route').'|'.self::date($row, 'service_date')->format('Y-m-d').'|'.$run;
    }

    /**
     * @param array<string, int> $counts
     *
     * @return list<string>
     */
    private static function sortedKeys(array $counts): array
    {
        $keys = array_map(strval(...), array_keys($counts));
        sort($keys);

        return $keys;
    }

    private static function string(ExtractRow $row, string $field): string
    {
        $value = $row->values[$field];
        self::assertIsString($value);

        return $value;
    }

    private static function date(ExtractRow $row, string $field): \DateTimeImmutable
    {
        $value = $row->values[$field];
        self::assertInstanceOf(\DateTimeImmutable::class, $value);

        return $value;
    }
}
