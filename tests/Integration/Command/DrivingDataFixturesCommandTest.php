<?php

namespace App\Tests\Integration\Command;

use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\ExecutionResult;

/**
 * Checks that the command writes the scenario files and the development dataset in the layout of
 * the default mapping.
 */
final class DrivingDataFixturesCommandTest extends KernelTestCase
{
    private const array DEFAULT_HEADER = [
        'RouteStopID', 'RouteName', 'PersonID', 'Date', 'blocknumber', 'StopType', 'Status',
        'passengersaftercancel', 'ToNextDistance', 'ToNextDuration', 'PlannedArrival', 'StatusUpdateTime',
        'kørselstype',
    ];

    public function testCommandWritesTheScenariosAndThirteenMonths(): void
    {
        $result = self::runFixturesCommand();

        $this->assertCommandIsSuccessful($result);
        self::assertStringContainsString('17 files', $result->getDisplay());

        $directory = self::directory();
        foreach (['base', 'range_edges', 'bad_values', 'duplicate_row_ids'] as $scenario) {
            self::assertFileExists($directory.'/'.$scenario.'.xlsx');
        }
        self::assertFileExists($directory.'/dev-2025-02.xlsx');
        self::assertFileExists($directory.'/dev-2026-02.xlsx');
        self::assertCount(13, glob($directory.'/dev-*.xlsx') ?: []);
    }

    public function testBaseFileHasTheLayoutOfTheDefaultMapping(): void
    {
        self::runFixturesCommand();

        $rows = self::readRows(self::directory().'/base.xlsx');

        self::assertSame(self::DEFAULT_HEADER, $rows[2]);
        self::assertCount(14, array_slice($rows, 3));
    }

    public function testNextRunReplacesTheMonthsOfTheEarlierRun(): void
    {
        self::runFixturesCommand();
        self::runCommand('app:fixtures:driving-data', ['--seed' => '7', '--last-month' => '2026-05']);

        $directory = self::directory();
        self::assertCount(13, glob($directory.'/dev-*.xlsx') ?: []);
        self::assertFileDoesNotExist($directory.'/dev-2025-02.xlsx');
        self::assertFileExists($directory.'/dev-2026-05.xlsx');
    }

    public function testLastMonthThatIsNotYearAndMonthIsRejected(): void
    {
        $result = self::runCommand('app:fixtures:driving-data', ['--last-month' => 'February']);

        $this->assertCommandIsInvalid($result);
        self::assertStringContainsString('YYYY-MM', $result->getDisplay());
    }

    /**
     * A fixed last month gives fixed file names.
     */
    private static function runFixturesCommand(): ExecutionResult
    {
        return self::runCommand('app:fixtures:driving-data', ['--seed' => '7', '--last-month' => '2026-02']);
    }

    private static function directory(): string
    {
        return self::getContainer()->getParameter('kernel.project_dir').'/var/fixtures/driving_data';
    }

    /**
     * @return list<array<int, mixed>>
     */
    private static function readRows(string $path): array
    {
        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));
        $reader->open($path);

        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }
        $reader->close();

        return $rows;
    }
}
