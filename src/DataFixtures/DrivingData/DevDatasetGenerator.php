<?php

declare(strict_types=1);

namespace App\DataFixtures\DrivingData;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Makes invented driving data for the local site, one ISO week at a time.
 *
 * The volume and the patterns are like the school transport: two groups of sub-agreements, the
 * same citizens on a route each day, a full volume on a school day, a low volume in a holiday,
 * and only booked trips at the weekend. The punctuality comes from DatasetSettings, not from the
 * real data. The repository is public, so no value in this class comes from a real extract. Only
 * the codes of a cancellation are as in the extract, so that the pages can map them.
 *
 * The same settings give the same rows. Each day has its own random engine, so that a day is the
 * same also when its week is made alone.
 */
final readonly class DevDatasetGenerator
{
    /**
     * The route of the trips that are booked outside the fixed routes.
     */
    public const string BOOKED_ROUTE = 'Turbestilling';

    /**
     * Route names that are not routes, as in the real data. The import makes a sub-agreement of
     * each of them.
     */
    public const string MISSPELLED_BOOKED_ROUTE = 'Turebestilling';

    public const string ROUTE_WITH_NO_SEPARATOR = 'Ekstratur0042';

    public const array NOTE_ROUTES = ['Kørt i anden plan', 'Flyttet til anden rute'];

    /**
     * The number of citizens that ride on one route.
     */
    public const int CITIZENS_PER_ROUTE = 12;

    /**
     * The invented sub-agreements: the separator in the route names, the number of routes, the
     * fewest and the most runs of a route on a school day, the group, and the price for each
     * minute. Group A drives almost nothing in the summer holiday. Group B still drives some
     * routes.
     *
     * @var array<string, array{string, int, int, int, 'A'|'B', float}>
     */
    private const array SUB_AGREEMENTS = [
        'NV-TA' => ['-', 30, 2, 4, 'A', 15.4],
        'SØ-KL' => ['-', 27, 3, 4, 'A', 16.1],
        'MI-BU' => ['-', 50, 2, 4, 'A', 17.25],
        'VE-RO' => ['-', 66, 3, 4, 'A', 18.05],
        'Lund' => ['_', 40, 3, 5, 'B', 16.75],
        'Bakke' => ['_', 72, 4, 6, 'B', 17.6],
    ];

    /**
     * The invented supplier of each sub-agreement. The booked trips and the route names that are
     * not routes have no supplier, so that the pages show "Ukendt".
     *
     * @var array<string, string>
     */
    public const array SUPPLIERS = [
        'NV-TA' => 'Vognmand A',
        'SØ-KL' => 'Vognmand C',
        'MI-BU' => 'Vognmand C',
        'VE-RO' => 'Vognmand A',
        'Lund' => 'Vognmand D',
        'Bakke' => 'Vognmand B',
    ];

    private const float BOOKED_PRICE = 17.0;

    /**
     * The chance in percent that a route drives on a weekday in a holiday, for each group.
     */
    private const array SUMMER_CHANCE = ['A' => 5, 'B' => 35];

    private const array HOLIDAY_CHANCE = ['A' => 15, 'B' => 40];

    /**
     * The number of trips in a run, with its weight. Most runs have 1 trip.
     *
     * @var array<int, int>
     */
    private const array TRIPS = [1 => 45, 2 => 15, 3 => 12, 4 => 10, 5 => 6, 6 => 5, 7 => 3, 8 => 2, 10 => 2];

    /**
     * The chance in percent of a cancelled trip, and of a trip with no registration at the pickup.
     */
    private const int CANCELLED = 10;

    private const int PENDING = 4;

    /**
     * The channel, the type and the category of a cancellation, with a weight, and the fewest and
     * the most minutes of the cancellation before the first pickup of the run. A negative number
     * is after the pickup. The weights and the minutes are invented. The type follows from the
     * channel. The institution portal often gives no category. The driver registers a no-show at
     * the door, near the pickup. An early cancellation comes at least 2 hours before, and a late
     * cancellation in the last hour.
     *
     * @var list<array{string, string, ?string, int, int, int}>
     */
    private const array CANCELLATION_CAUSES = [
        ['Passenger app', 'By the passenger', 'Cancelled by a passenger or a relative', 30, 30, 2400],
        ['Driver app', 'Passenger did not show up', 'Afbud ved dør', 12, -15, 10],
        ['Driver app', 'Passenger did not show up', 'NoShow', 10, -30, 5],
        ['Office portal', 'Other reasons', 'Afbestilt tidligt', 14, 120, 2400],
        ['Office portal', 'Other reasons', 'Afbestilt sent', 6, 0, 60],
        ['Office portal', 'Other reasons', 'NoShow', 3, -60, 0],
        ['Institution portal', 'Other reasons', null, 12, 30, 2400],
        ['Institution portal', 'Other reasons', 'Afbestilt tidligt', 5, 120, 2400],
        ['Institution portal', 'Other reasons', 'NoShow', 2, -60, 0],
        ['System', 'By trip request', 'Trip request has expired', 4, 600, 2400],
        ['System', 'By trip request', 'Trip is paused', 2, 600, 2400],
    ];

    /**
     * The chance in percent that another driver than the fixed driver drives a route on a day.
     */
    private const int SUBSTITUTE_DRIVER = 10;

    /**
     * The delay bands of a late last drop-off in seconds, with the weight of each band.
     *
     * @var list<array{int, int, int}>
     */
    private const array LATE_BANDS = [[301, 900, 60], [901, 1800, 28], [1801, 3600, 12]];

    public function __construct(
        private SchoolCalendar $calendar,
    ) {
    }

    /**
     * Gives the rows of the ISO week that starts on a Monday.
     *
     * @return list<ExtractRow>
     */
    public function week(DatasetSettings $settings, \DateTimeImmutable $monday): array
    {
        $rows = [];
        for ($day = $monday, $end = $monday->modify('+7 days'); $day < $end; $day = $day->modify('+1 day')) {
            array_push($rows, ...$this->day($settings, $day));
        }

        return $rows;
    }

    /**
     * @return list<ExtractRow>
     */
    private function day(DatasetSettings $settings, \DateTimeImmutable $day): array
    {
        $day = new \DateTimeImmutable($day->format('Y-m-d'));
        $random = new Randomizer(new Mt19937(crc32($settings->seed.'-'.$day->format('Y-m-d'))));
        $punctuality = $settings->punctuality($day);
        $weekend = $this->calendar->isWeekend($day);

        $rows = [];
        // The booked trips: one citizen in each run, on all days.
        for ($run = 1, $runs = $weekend ? $random->getInt(0, 2) : $random->getInt(1, 3); $run <= $runs; ++$run) {
            $rows[] = $this->run($random, $day, $punctuality, self::BOOKED_ROUTE, 0, self::BOOKED_PRICE, $run, $random->getInt(8 * 60, 17 * 60), 1, false, $this->bookedDriver($random));
        }
        if ($weekend) {
            return $this->withRowIds($day, array_merge(...$rows));
        }

        $index = 1;
        foreach (self::SUB_AGREEMENTS as $reference => [$separator, $routes, $fewest, $most, $group, $price]) {
            $chance = match (true) {
                $this->calendar->isSummerHoliday($day) => self::SUMMER_CHANCE[$group],
                $this->calendar->isHoliday($day) => self::HOLIDAY_CHANCE[$group],
                default => 100,
            };
            for ($number = 1; $number <= $routes; ++$number, ++$index) {
                if ($random->getInt(1, 100) > $chance) {
                    continue;
                }
                $route = \sprintf('%s%s%02d', $reference, $separator, $number);
                // One driver drives all runs of a route on a day.
                $driver = $random->getInt(1, 100) <= self::SUBSTITUTE_DRIVER ? (string) $random->getInt(500, 599) : (string) (100 + $index);
                $runs = $random->getInt($fewest, $most);
                $mornings = intdiv($runs + 1, 2);
                for ($run = 1; $run <= $runs; ++$run) {
                    // The runs start in the morning, and then in the afternoon.
                    $start = $run <= $mornings
                        ? 6 * 60 + 15 + 35 * ($run - 1) + $random->getInt(0, 30)
                        : 12 * 60 + 30 + 40 * ($run - $mornings - 1) + $random->getInt(0, 40);
                    $rows[] = $this->run($random, $day, $punctuality, $route, $index, $price, $run, $start, $this->trips($random), $run <= $mornings, $driver);
                }
            }
        }

        // The route names that are not routes, on a few days.
        foreach ([self::MISSPELLED_BOOKED_ROUTE => 3, self::ROUTE_WITH_NO_SEPARATOR => 2, ...array_fill_keys(self::NOTE_ROUTES, 2)] as $route => $chance) {
            if ($random->getInt(1, 100) <= $chance) {
                $rows[] = $this->run($random, $day, $punctuality, $route, 0, self::BOOKED_PRICE, 1, $random->getInt(8 * 60, 15 * 60), 1, false, $this->bookedDriver($random));
            }
        }

        return $this->withRowIds($day, array_merge(...$rows));
    }

    /**
     * Makes the stops of one run: the pickups of all trips, then the drop-offs in the same order.
     * A morning run drives the citizens from home to the institution of the route. An afternoon
     * run drives them back. The institution end of a trip has a place name.
     *
     * @param int    $routeIndex the number of the route in the dataset, which selects its citizens and its institution. 0 is a route with no fixed citizens
     * @param int    $start      the planned time of the first pickup, in minutes after midnight
     * @param bool   $toSchool   true for a run from home to the institution
     * @param string $driver     the id of the driver
     *
     * @return list<ExtractRow>
     */
    private function run(Randomizer $random, \DateTimeImmutable $day, float $punctuality, string $route, int $routeIndex, float $price, int $run, int $start, int $tripCount, bool $toSchool, string $driver): array
    {
        $place = 0 === $routeIndex ? null : (0 === $routeIndex % 3 ? \sprintf('Center %d', $routeIndex % 30 + 1) : \sprintf('Skole %d', $routeIndex % 60 + 1));
        $citizens = 0 === $routeIndex
            ? [\sprintf('P-B%04d', $random->getInt(1, 400))]
            : \array_slice($random->shuffleArray(range(1, self::CITIZENS_PER_ROUTE)), 0, $tripCount);

        $trips = [];
        foreach ($citizens as $citizen) {
            $chance = $random->getInt(1, 100);
            $cancelled = $chance <= self::CANCELLED;
            $cause = $cancelled ? self::CANCELLATION_CAUSES[self::weighted($random, array_column(self::CANCELLATION_CAUSES, 3))] : null;
            $trips[] = [
                'person' => \is_string($citizen) ? $citizen : \sprintf('P-%05d', $routeIndex * self::CITIZENS_PER_ROUTE + $citizen),
                'status' => $cancelled ? 'cancelled' : ($chance <= self::CANCELLED + self::PENDING ? 'pending' : 'completed'),
                'cause' => $cause,
                // Up to 40 hours before the first pickup of the run, so that some trips are
                // cancelled on the day before.
                'cancelled at' => null === $cause ? null : $day->modify(\sprintf('%+d minutes', $start - $random->getInt($cause[4], $cause[5]))),
            ];
        }
        $solo = $random->getInt(1, 100) <= 5;
        $lastDelay = $this->lastDropOffDelay($random, $punctuality);
        $completedDropOffs = \count(array_filter($trips, static fn (array $trip): bool => 'cancelled' !== $trip['status']));

        $rows = [];
        $passengers = 0;
        $planned = 0;
        $dropOff = 0;
        $minutes = $start;
        foreach (['pickup', 'drop_off'] as $type) {
            foreach ($trips as $trip) {
                $cancelled = 'cancelled' === $trip['status'];
                // A citizen with no registration at the pickup is still driven to the drop-off.
                $status = 'pending' === $trip['status'] && 'drop_off' === $type ? 'completed' : $trip['status'];
                $passengers += $cancelled ? 0 : ('pickup' === $type ? 1 : -1);
                $planned += 'pickup' === $type ? 1 : -1;
                if ('drop_off' === $type && !$cancelled) {
                    ++$dropOff;
                }
                $delay = match (true) {
                    'pickup' === $type => $this->pickupDelay($random, $punctuality),
                    $dropOff === $completedDropOffs => $lastDelay,
                    // The delay grows from drop-off to drop-off, up to the delay of the last drop-off.
                    default => intdiv($lastDelay * $dropOff, max(1, $completedDropOffs)) + $random->getInt(-120, 60),
                };
                $km = $this->distance($random);
                $cancelledAt = $trip['cancelled at'];
                // The report calculates the notice from the time of day of the cancellation only.
                // The code "in time" is then "yes" for an earlier date, or for a notice of more
                // than 60 minutes. Thus the pickup and the drop-off of a trip can have other codes.
                $notice = null === $cancelledAt ? null : (float) ($minutes - (int) $cancelledAt->format('G') * 60 - (int) $cancelledAt->format('i'));

                $rows[] = new ExtractRow(
                    [
                        'route' => $route,
                        'person_id' => $trip['person'],
                        'service_date' => $day,
                        'run' => $run,
                        'stop_type' => $type,
                        'stop_status' => $status,
                        'passengers_after' => $passengers,
                        'next_distance_km' => $km,
                        'next_duration_min' => null === $km ? null : round($km * 2.1 + 1, 1),
                        'planned_time' => new \DateInterval(\sprintf('PT%dH%dM', intdiv($minutes, 60), $minutes % 60)),
                        'actual_time' => 'completed' === $status ? $day->modify(\sprintf('%+d seconds', $minutes * 60 + $delay)) : null,
                        'planned_solo' => $solo,
                        'location_name' => ('drop_off' === $type) === $toSchool ? $place : null,
                        // The extract has "no" on a row that is not cancelled.
                        'cancelled_in_time' => null !== $cancelledAt && ($cancelledAt < $day || $notice > 60),
                    ],
                    kept: [
                        'passengers' => $planned,
                        'prisprminut' => $price,
                        'aflysningstidspunkt' => $cancelledAt,
                        'aflysningsvarsel' => $notice,
                        'afbestillingskanal' => $trip['cause'][0] ?? null,
                        'aflysningstype' => $trip['cause'][1] ?? null,
                        'aflysningskategori' => $trip['cause'][2] ?? null,
                        'chauffør' => $driver,
                    ],
                );
                $minutes += 'pickup' === $type ? $random->getInt(4, 14) : $random->getInt(2, 8);
            }
            $minutes += $random->getInt(6, 20);
        }

        return $rows;
    }

    private function trips(Randomizer $random): int
    {
        return self::weighted($random, self::TRIPS);
    }

    /**
     * A booked trip has no fixed route, so each run has a driver from a pool.
     */
    private function bookedDriver(Randomizer $random): string
    {
        return (string) $random->getInt(600, 649);
    }

    /**
     * Gives the delay in seconds of the last drop-off of a run: on time with the chance of the
     * punctuality, or else in one of the late bands.
     */
    private function lastDropOffDelay(Randomizer $random, float $punctuality): int
    {
        if ($random->getFloat(0.0, 100.0) < $punctuality) {
            return $random->getInt(-480, 300);
        }
        [$least, $most] = self::LATE_BANDS[self::weighted($random, array_column(self::LATE_BANDS, 2))];

        return $random->getInt($least, $most);
    }

    /**
     * Selects a key with a chance that is its weight.
     *
     * @param non-empty-array<int, int> $weights
     */
    private static function weighted(Randomizer $random, array $weights): int
    {
        $pick = $random->getInt(1, array_sum($weights));
        foreach ($weights as $key => $weight) {
            $pick -= $weight;
            if ($pick <= 0) {
                break;
            }
        }

        return $key;
    }

    /**
     * Gives the delay in seconds of a pickup. A pickup is late less often than a last drop-off.
     */
    private function pickupDelay(Randomizer $random, float $punctuality): int
    {
        return $random->getFloat(0.0, 100.0) < (100.0 - $punctuality) / 2
            ? $random->getInt(301, 900)
            : $random->getInt(-600, 300);
    }

    /**
     * Gives the distance to the next stop in km. Some stops have no distance.
     */
    private function distance(Randomizer $random): ?float
    {
        $pick = $random->getInt(1, 100);

        return match (true) {
            $pick <= 5 => null,
            $pick <= 75 => round($random->getFloat(0.3, 10.0), 1),
            $pick <= 95 => round($random->getFloat(10.0, 25.0), 1),
            default => round($random->getFloat(25.0, 45.0), 1),
        };
    }

    /**
     * The date is in each row id. The import of a week then keeps the row ids of the other weeks
     * unique.
     *
     * @param list<ExtractRow> $rows
     *
     * @return list<ExtractRow>
     */
    private function withRowIds(\DateTimeImmutable $day, array $rows): array
    {
        return array_map(
            static fn (ExtractRow $row, int $index): ExtractRow => new ExtractRow($row->values, $day->format('Ymd').'-'.($index + 1), $row->raw, $row->kept),
            $rows,
            array_keys($rows),
        );
    }
}
