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
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Checks the development dataset: one ISO week at a time, the same rows for the same seed, the
 * volume of the school calendar, and the punctuality that the settings give.
 */
#[Group('fixtures')]
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
        self::assertSame(self::fingerprint(self::AUTUMN_WEEK), self::fingerprint(self::AUTUMN_WEEK));
    }

    public function testOtherSeedGivesOtherRows(): void
    {
        self::assertNotSame(self::fingerprint(self::AUTUMN_WEEK), self::fingerprint(self::AUTUMN_WEEK, seed: 8));
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
     *
     * Each of these names is on a few percent of the weekdays. The test reads a whole year, so that
     * a change of the random sequence does not remove a name from the range.
     */
    public function testDatasetHasRouteNamesThatAreNotRoutes(): void
    {
        $routes = [];
        for ($monday = new \DateTimeImmutable('2026-01-05'); $monday < new \DateTimeImmutable('2027-01-04'); $monday = $monday->modify('+7 days')) {
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
     * The rows have the location name and the kept columns of the prototype. The institution end
     * of a trip has a location name. Only a cancelled stop has a cancellation notice.
     */
    public function testRowsHaveTheLocationNameAndTheKeptColumnsOfThePrototype(): void
    {
        $rows = self::generate(self::SCHOOL_WEEK);
        $places = array_filter($rows, static fn (ExtractRow $row): bool => null !== $row->values['location_name']);

        self::assertGreaterThan(0.3 * \count($rows), \count($places));
        self::assertLessThan(0.7 * \count($rows), \count($places));
        foreach ($rows as $row) {
            self::assertIsInt($row->kept['passengers']);
            self::assertIsFloat($row->kept['prisprminut']);
            self::assertIsBool($row->values['cancelled_in_time']);
            $cancelled = 'cancelled' === $row->values['stop_status'];
            self::assertSame($cancelled, \is_float($row->kept['aflysningsvarsel']));
            // The extract has "no" on a row that is not cancelled.
            if (!$cancelled) {
                self::assertFalse($row->values['cancelled_in_time']);
            }
        }
        // Some cancelled trips came in time, and some came too late.
        $codes = array_map(
            static fn (ExtractRow $row): mixed => $row->values['cancelled_in_time'],
            array_filter($rows, static fn (ExtractRow $row): bool => 'cancelled' === $row->values['stop_status']),
        );
        self::assertContains(true, $codes);
        self::assertContains(false, $codes);
    }

    /**
     * Only a cancelled stop has the time, the channel, the type and the category of the
     * cancellation. The institution portal can give no category. Some trips are cancelled on the
     * day before, and the code "in time" is then "yes".
     */
    public function testCancelledStopsHaveTheCancellationColumns(): void
    {
        $channels = [];
        $earlierDay = 0;
        $noCategory = 0;
        foreach (self::generate(self::SCHOOL_WEEK) as $row) {
            $cancelledAt = $row->kept['aflysningstidspunkt'];
            if ('cancelled' !== $row->values['stop_status']) {
                self::assertNull($cancelledAt);
                self::assertNull($row->kept['afbestillingskanal']);
                self::assertNull($row->kept['aflysningstype']);
                self::assertNull($row->kept['aflysningskategori']);
                continue;
            }
            self::assertInstanceOf(\DateTimeImmutable::class, $cancelledAt);
            self::assertIsString($row->kept['afbestillingskanal']);
            self::assertIsString($row->kept['aflysningstype']);
            $channels[$row->kept['afbestillingskanal']] = true;
            if (null === $row->kept['aflysningskategori']) {
                self::assertSame('Institution portal', $row->kept['afbestillingskanal']);
                ++$noCategory;
            }
            if ($cancelledAt < self::date($row, 'service_date')) {
                self::assertTrue($row->values['cancelled_in_time']);
                ++$earlierDay;
            }
        }

        self::assertCount(5, $channels);
        self::assertGreaterThan(0, $earlierDay);
        self::assertGreaterThan(0, $noCategory);
    }

    /**
     * The time of a cancellation follows its cause, so that the no-shows and the notice bands
     * have realistic data. The driver registers a no-show at the door, on the day of the trip. An
     * early cancellation is always in time. Some late cancellations are too late.
     */
    public function testCancellationTimeFollowsTheCause(): void
    {
        $late = [];
        foreach (self::generate(self::SCHOOL_WEEK) as $row) {
            if ('cancelled' !== $row->values['stop_status']) {
                continue;
            }
            $cancelledAt = $row->kept['aflysningstidspunkt'];
            self::assertInstanceOf(\DateTimeImmutable::class, $cancelledAt);
            if ('Driver app' === $row->kept['afbestillingskanal']) {
                self::assertSame(self::date($row, 'service_date')->format('Y-m-d'), $cancelledAt->format('Y-m-d'));
            }
            if ('Afbestilt tidligt' === $row->kept['aflysningskategori']) {
                self::assertTrue($row->values['cancelled_in_time']);
            }
            if ('Afbestilt sent' === $row->kept['aflysningskategori']) {
                $late[] = $row->values['cancelled_in_time'];
            }
        }

        self::assertContains(false, $late);
    }

    /**
     * One driver drives all runs of a route on a day. Another driver than the fixed driver drives
     * a route on some days, so that the driver changes can be shown.
     */
    public function testRouteHasOneDriverOnADay(): void
    {
        $drivers = [];
        foreach (self::generate(self::SCHOOL_WEEK) as $row) {
            $driver = $row->kept['chauffør'];
            self::assertIsString($driver);
            if (DevDatasetGenerator::BOOKED_ROUTE !== $row->values['route']) {
                $drivers[self::string($row, 'route')][self::date($row, 'service_date')->format('Y-m-d')][$driver] = true;
            }
        }

        $changes = 0;
        foreach ($drivers as $days) {
            foreach ($days as $driversOfDay) {
                self::assertCount(1, $driversOfDay);
            }
            $changes += \count(array_unique(array_map(array_key_first(...), $days))) - 1;
        }
        self::assertGreaterThan(0, $changes);
    }

    /**
     * The import of a week replaces only the rows of that week. A row id that is also in another
     * week then breaks the unique index of the row keys.
     */
    public function testRowIdsAreUniqueAcrossWeeks(): void
    {
        $ids = [...self::rowIds(self::SCHOOL_WEEK), ...self::rowIds('2026-09-21')];

        self::assertNotContains(null, $ids);
        self::assertCount(\count($ids), array_unique($ids));
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
     * Gives a hash of all values of the rows of a week. Two weeks of rows together use much memory,
     * so a test compares the hashes, not the rows.
     */
    private static function fingerprint(string $monday, int $seed = 7): string
    {
        return hash('xxh128', serialize(self::generate($monday, $seed)));
    }

    /**
     * Gives only the row ids of a week. The rows of two weeks together use much memory.
     *
     * @return list<?string>
     */
    private static function rowIds(string $monday): array
    {
        return array_map(static fn (ExtractRow $row): ?string => $row->rowId, self::generate($monday));
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
