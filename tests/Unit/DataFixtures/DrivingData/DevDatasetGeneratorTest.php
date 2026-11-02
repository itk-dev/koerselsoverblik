<?php

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
            (new DevDatasetGenerator())->month(7, $month),
            (new DevDatasetGenerator())->month(7, $month),
        );
    }

    public function testOtherSeedGivesOtherRows(): void
    {
        $month = new \DateTimeImmutable('2026-02-01');

        self::assertNotEquals(
            (new DevDatasetGenerator())->month(7, $month),
            (new DevDatasetGenerator())->month(8, $month),
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
        $rows = (new DevDatasetGenerator())->month(7, new \DateTimeImmutable('2026-02-01'));

        $days = array_unique(array_map(static fn (ExtractRow $row): string => self::date($row, 'service_date')->format('Y-m-d'), $rows));
        sort($days);

        self::assertCount(28, $days);
        self::assertSame('2026-02-01', $days[0]);
        self::assertSame('2026-02-28', $days[27]);
    }

    public function testRowsUseTheRoutesOfTheDataset(): void
    {
        $rows = (new DevDatasetGenerator())->month(7, new \DateTimeImmutable('2026-02-01'));

        $routes = array_unique(array_map(static fn (ExtractRow $row): string => self::string($row, 'route'), $rows));
        sort($routes);
        $expected = DevDatasetGenerator::ROUTES;
        sort($expected);

        self::assertSame($expected, $routes);
    }

    public function testEachTripHasAPickupAndADropOff(): void
    {
        $rows = (new DevDatasetGenerator())->month(7, new \DateTimeImmutable('2026-02-01'));

        $types = array_count_values(array_map(static fn (ExtractRow $row): string => self::string($row, 'stop_type'), $rows));

        self::assertSame($types['pickup'], $types['drop_off']);
    }

    public function testEachRunEndsWithNoPassengers(): void
    {
        $rows = (new DevDatasetGenerator())->month(7, new \DateTimeImmutable('2026-02-01'));

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
        $rows = (new DevDatasetGenerator())->month(7, new \DateTimeImmutable('2026-02-01'));

        $statuses = array_unique(array_map(static fn (ExtractRow $row): string => self::string($row, 'stop_status'), $rows));
        sort($statuses);

        self::assertSame(['cancelled', 'completed', 'pending'], $statuses);
    }

    /**
     * The rows have values that the default mapping accepts.
     */
    public function testDefaultMappingCanWriteTheRows(): void
    {
        $path = sys_get_temp_dir().'/'.uniqid('dev_dataset_', true).'.xlsx';
        $rows = (new DevDatasetGenerator())->month(7, new \DateTimeImmutable('2026-02-01'));

        (new ExtractWriter())->write($path, (new MappingLoader())->load(self::DEFAULT_MAPPING), $rows);

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
