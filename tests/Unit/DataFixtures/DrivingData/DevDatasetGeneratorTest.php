<?php

declare(strict_types=1);

namespace App\Tests\Unit\DataFixtures\DrivingData;

use App\DataFixtures\DrivingData\DevDatasetGenerator;
use App\DataFixtures\DrivingData\ExtractRow;
use App\DataFixtures\DrivingData\ExtractWriter;
use App\Import\Mapping\MappingLoader;
use PHPUnit\Framework\TestCase;

/**
 * Checks that the development dataset is the same for the same seed, and that each month file
 * holds the days of one month.
 */
final class DevDatasetGeneratorTest extends TestCase
{
    private const string DEFAULT_MAPPING = __DIR__.'/../../../../config/import/driving_data/default.yaml';

    public function testSameSeedGivesTheSameRows(): void
    {
        $month = new \DateTimeImmutable('2026-02-01');

        self::assertEquals(
            new DevDatasetGenerator()->month(7, $month),
            new DevDatasetGenerator()->month(7, $month),
        );
    }

    public function testOtherSeedGivesOtherRows(): void
    {
        $month = new \DateTimeImmutable('2026-02-01');

        self::assertNotEquals(
            new DevDatasetGenerator()->month(7, $month),
            new DevDatasetGenerator()->month(8, $month),
        );
    }

    public function testOtherMonthGivesOtherRows(): void
    {
        $generator = new DevDatasetGenerator();

        self::assertNotEquals(
            self::times($generator->month(7, new \DateTimeImmutable('2026-01-01'))),
            self::times($generator->month(7, new \DateTimeImmutable('2026-02-01'))),
        );
    }

    public function testEachDayOfTheMonthHasRowsAndNoOtherDayHas(): void
    {
        $rows = new DevDatasetGenerator()->month(7, new \DateTimeImmutable('2026-02-01'));

        $days = array_unique(array_map(static fn (ExtractRow $row): string => self::date($row, 'service_date')->format('Y-m-d'), $rows));
        sort($days);

        self::assertCount(28, $days);
        self::assertSame('2026-02-01', $days[0]);
        self::assertSame('2026-02-28', $days[27]);
    }

    public function testRowsUseTheRoutesOfTheDataset(): void
    {
        $rows = new DevDatasetGenerator()->month(7, new \DateTimeImmutable('2026-02-01'));

        $routes = array_unique(array_map(static fn (ExtractRow $row): string => self::string($row, 'route'), $rows));
        sort($routes);
        $expected = DevDatasetGenerator::ROUTES;
        sort($expected);

        self::assertSame($expected, $routes);
    }

    public function testEachTripHasAPickupAndADropOff(): void
    {
        $rows = new DevDatasetGenerator()->month(7, new \DateTimeImmutable('2026-02-01'));

        $types = array_count_values(array_map(static fn (ExtractRow $row): string => self::string($row, 'stop_type'), $rows));

        self::assertSame($types['pickup'], $types['drop_off']);
    }

    public function testEachRunEndsWithNoPassengers(): void
    {
        $rows = new DevDatasetGenerator()->month(7, new \DateTimeImmutable('2026-02-01'));

        $last = [];
        foreach ($rows as $row) {
            $run = $row->values['run'];
            self::assertIsInt($run);
            $key = self::string($row, 'route').'|'.self::date($row, 'service_date')->format('Y-m-d').'|'.$run;
            $passengers = $row->values['passengers_after'];
            self::assertIsInt($passengers);
            $last[$key] = $passengers;
        }

        self::assertSame([0], array_values(array_unique($last)));
    }

    public function testRowsHaveCancelledAndPendingStops(): void
    {
        $rows = new DevDatasetGenerator()->month(7, new \DateTimeImmutable('2026-02-01'));

        $statuses = array_unique(array_map(static fn (ExtractRow $row): string => self::string($row, 'stop_status'), $rows));
        sort($statuses);

        self::assertSame(['cancelled', 'completed', 'pending'], $statuses);
    }

    /**
     * The import of a month replaces only the rows of that month. A row id that is also in another
     * month then breaks the unique index of the row keys.
     */
    public function testRowIdsAreUniqueAcrossMonths(): void
    {
        $generator = new DevDatasetGenerator();
        $rows = [
            ...$generator->month(7, new \DateTimeImmutable('2026-01-01')),
            ...$generator->month(7, new \DateTimeImmutable('2026-02-01')),
        ];

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
        }, self::lastDropOffDelays('2026-02-01')));
        ksort($bands);

        self::assertSame(['more than 15 to 30', 'more than 30', 'more than 5 to 15', 'on time'], array_keys($bands));
    }

    /**
     * The months have different punctuality, so that the page shows the tones "ok" (90 % or more),
     * "warn" (70 % or more) and "danger". The month number gives the profile.
     */
    public function testMonthsHaveDifferentPunctuality(): void
    {
        self::assertGreaterThanOrEqual(90.0, self::onTimePercent('2026-03-01'));
        $fair = self::onTimePercent('2026-01-01');
        self::assertGreaterThanOrEqual(70.0, $fair);
        self::assertLessThan(90.0, $fair);
        self::assertLessThan(70.0, self::onTimePercent('2026-02-01'));
    }

    /**
     * Gives the last drop-offs of the month that are at most 5 minutes late, in percent.
     */
    private static function onTimePercent(string $month): float
    {
        $delays = self::lastDropOffDelays($month);

        return \count(array_filter($delays, static fn (int $delay): bool => $delay <= 300)) / \count($delays) * 100;
    }

    /**
     * Gives the delay in seconds of the last completed drop-off of each run of the month.
     *
     * @return array<string, int> keyed by the route, the date and the run
     */
    private static function lastDropOffDelays(string $month): array
    {
        $delays = [];
        foreach (new DevDatasetGenerator()->month(7, new \DateTimeImmutable($month)) as $row) {
            if ('drop_off' !== self::string($row, 'stop_type') || 'completed' !== self::string($row, 'stop_status')) {
                continue;
            }
            $serviceDate = self::date($row, 'service_date');
            $planned = $row->values['planned_time'];
            self::assertInstanceOf(\DateInterval::class, $planned);
            $run = $row->values['run'];
            self::assertIsInt($run);
            // The drop-offs of a run are in the order of their planned times.
            $delays[self::string($row, 'route').'|'.$serviceDate->format('Y-m-d').'|'.$run] = self::date($row, 'actual_time')->getTimestamp() - $serviceDate->add($planned)->getTimestamp();
        }

        return $delays;
    }

    /**
     * The rows have values that the default mapping accepts.
     */
    public function testDefaultMappingCanWriteTheRows(): void
    {
        $path = sys_get_temp_dir().'/'.uniqid('dev_dataset_', true).'.xlsx';
        $rows = new DevDatasetGenerator()->month(7, new \DateTimeImmutable('2026-02-01'));

        new ExtractWriter()->write($path, new MappingLoader()->load(self::DEFAULT_MAPPING), $rows);

        self::assertFileExists($path);
        unlink($path);
    }

    /**
     * @param list<ExtractRow> $rows
     *
     * @return list<string>
     */
    private static function times(array $rows): array
    {
        return array_map(static fn (ExtractRow $row): string => self::date($row, 'actual_time')->format('d H:i'), array_values(array_filter(
            $rows,
            static fn (ExtractRow $row): bool => null !== $row->values['actual_time'],
        )));
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
