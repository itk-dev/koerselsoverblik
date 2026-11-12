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
 * real data. The repository is public, so no value in this class comes from a real extract.
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
            $rows[] = $this->run($random, $day, $punctuality, self::BOOKED_ROUTE, 0, self::BOOKED_PRICE, $run, $random->getInt(8 * 60, 17 * 60), 1, false);
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
                $runs = $random->getInt($fewest, $most);
                $mornings = intdiv($runs + 1, 2);
                for ($run = 1; $run <= $runs; ++$run) {
                    // The runs start in the morning, and then in the afternoon.
                    $start = $run <= $mornings
                        ? 6 * 60 + 15 + 35 * ($run - 1) + $random->getInt(0, 30)
                        : 12 * 60 + 30 + 40 * ($run - $mornings - 1) + $random->getInt(0, 40);
                    $rows[] = $this->run($random, $day, $punctuality, $route, $index, $price, $run, $start, $this->trips($random), $run <= $mornings);
                }
            }
        }

        // The route names that are not routes, on a few days.
        foreach ([self::MISSPELLED_BOOKED_ROUTE => 3, self::ROUTE_WITH_NO_SEPARATOR => 2, ...array_fill_keys(self::NOTE_ROUTES, 2)] as $route => $chance) {
            if ($random->getInt(1, 100) <= $chance) {
                $rows[] = $this->run($random, $day, $punctuality, $route, 0, self::BOOKED_PRICE, 1, $random->getInt(8 * 60, 15 * 60), 1, false);
            }
        }

        return $this->withRowIds($day, array_merge(...$rows));
    }

    /**
     * Makes the stops of one run: the pickups of all trips, then the drop-offs in the same order.
     * A morning run drives the citizens from home to the institution of the route. An afternoon
     * run drives them back. The institution end of a trip has a place name.
     *
     * @param int  $routeIndex the number of the route in the dataset, which selects its citizens and its institution. 0 is a route with no fixed citizens
     * @param int  $start      the planned time of the first pickup, in minutes after midnight
     * @param bool $toSchool   true for a run from home to the institution
     *
     * @return list<ExtractRow>
     */
    private function run(Randomizer $random, \DateTimeImmutable $day, float $punctuality, string $route, int $routeIndex, float $price, int $run, int $start, int $tripCount, bool $toSchool): array
    {
        $place = 0 === $routeIndex ? null : (0 === $routeIndex % 3 ? \sprintf('Center %d', $routeIndex % 30 + 1) : \sprintf('Skole %d', $routeIndex % 60 + 1));
        $citizens = 0 === $routeIndex
            ? [\sprintf('P-B%04d', $random->getInt(1, 400))]
            : \array_slice($random->shuffleArray(range(1, self::CITIZENS_PER_ROUTE)), 0, $tripCount);

        $trips = [];
        foreach ($citizens as $citizen) {
            $chance = $random->getInt(1, 100);
            $trips[] = [
                'person' => \is_string($citizen) ? $citizen : \sprintf('P-%05d', $routeIndex * self::CITIZENS_PER_ROUTE + $citizen),
                'status' => $chance <= self::CANCELLED ? 'cancelled' : ($chance <= self::CANCELLED + self::PENDING ? 'pending' : 'completed'),
                'notice' => round($random->getFloat(-600.0, 600.0), 1),
                'in time' => $random->getInt(1, 100) <= 60,
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
                        'passengers' => $planned,
                        'price_per_minute' => $price,
                        'cancellation_notice_min' => $cancelled ? $trip['notice'] : null,
                        'cancellation_in_time' => $cancelled && $trip['in time'],
                        'location_name' => ('drop_off' === $type) === $toSchool ? $place : null,
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
