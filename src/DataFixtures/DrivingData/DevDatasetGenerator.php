<?php

declare(strict_types=1);

namespace App\DataFixtures\DrivingData;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Makes invented driving data for the local site, one month at a time.
 *
 * The same seed gives the same rows. Each month has its own random engine, so that a month is
 * the same also when it is made alone.
 */
final class DevDatasetGenerator
{
    /**
     * Invented route names. The development fixtures use the same routes.
     */
    public const array ROUTES = ['R-101', 'R-102', 'R-201', 'R-202', 'R-301', 'BOOKED'];

    private const int CITIZENS = 150;

    /**
     * The punctuality profiles of the months, so that the punctuality page shows the tones "ok",
     * "warn" and "danger". The month number modulo 3 selects the profile.
     *
     * - most: the most minutes that a stop is late, with no late run
     * - late runs: the runs with a delay that grows after the pickups, in percent
     */
    private const array PROFILES = [
        0 => ['most' => 5, 'late runs' => 4],
        1 => ['most' => 6, 'late runs' => 10],
        2 => ['most' => 12, 'late runs' => 25],
    ];

    /**
     * @return list<ExtractRow>
     */
    public function month(int $seed, \DateTimeImmutable $month): array
    {
        $day = new \DateTimeImmutable($month->format('Y-m-01'));
        $random = new Randomizer(new Mt19937(crc32($seed.'-'.$day->format('Y-m'))));
        $profile = self::PROFILES[(int) $day->format('n') % 3];

        $rows = [];
        for ($next = $day->modify('+1 month'); $day < $next; $day = $day->modify('+1 day')) {
            foreach (self::ROUTES as $route) {
                $runs = $random->getInt(1, 3);
                for ($run = 1; $run <= $runs; ++$run) {
                    // The runs of a route start in the morning, at noon and in the afternoon.
                    $start = (6 + 4 * ($run - 1)) * 60 + $random->getInt(0, 120);
                    array_push($rows, ...$this->run($random, $profile, $route, $run, $day, $start));
                }
            }
        }

        // The month is in each row id. The import of a month then keeps the row ids of the other
        // months unique.
        return array_map(
            static fn (ExtractRow $row, int $index): ExtractRow => new ExtractRow($row->values, $month->format('Y-m').'-'.($index + 1)),
            $rows,
            array_keys($rows),
        );
    }

    /**
     * Makes the stops of one run: the pickups of all trips, then the drop-offs in the same order.
     *
     * In some runs, the delay grows after the pickups, so that the last drop-offs are in all the
     * delay bands of the punctuality page.
     *
     * @param array{most: int, 'late runs': int} $profile the punctuality profile of the month
     *
     * @return list<ExtractRow>
     */
    private function run(Randomizer $random, array $profile, string $route, int $run, \DateTimeImmutable $day, int $start): array
    {
        $lateMinutes = $random->getInt(1, 100) <= $profile['late runs'] ? $random->getInt(5, 45) : 0;
        $trips = [];
        for ($trip = $random->getInt(1, 3); $trip > 0; --$trip) {
            $chance = $random->getInt(1, 100);
            $trips[] = [
                'person' => \sprintf('P-%03d', $random->getInt(1, self::CITIZENS)),
                'status' => $chance <= 4 ? 'cancelled' : ($chance <= 7 ? 'pending' : 'completed'),
            ];
        }
        $solo = 1 === \count($trips);

        $rows = [];
        $passengers = 0;
        $minutes = $start;
        foreach (['pickup', 'drop_off'] as $type) {
            foreach ($trips as $trip) {
                $cancelled = 'cancelled' === $trip['status'];
                // A citizen with no registration at the pickup is still driven to the drop-off.
                $status = 'pending' === $trip['status'] && 'drop_off' === $type ? 'completed' : $trip['status'];
                $passengers += $cancelled ? 0 : ('pickup' === $type ? 1 : -1);
                $km = $cancelled ? null : round($random->getFloat(0.5, 8.0), 1);

                $rows[] = new ExtractRow([
                    'route' => $route,
                    'person_id' => $trip['person'],
                    'service_date' => $day,
                    'run' => $run,
                    'stop_type' => $type,
                    'stop_status' => $status,
                    'passengers_after' => $passengers,
                    'next_distance_km' => $km,
                    'next_duration_min' => null === $km ? null : round($km * 2.5, 1),
                    'planned_time' => new \DateInterval(\sprintf('PT%dH%dM', intdiv($minutes, 60), $minutes % 60)),
                    'actual_time' => 'completed' === $status
                        ? $day->modify(\sprintf('+%d minutes', $minutes + $random->getInt(-2, $profile['most']) + ('drop_off' === $type ? $lateMinutes : 0)))
                        : null,
                    'planned_solo' => $solo,
                ]);
                $minutes += $random->getInt(5, 20);
            }
        }

        return $rows;
    }
}
