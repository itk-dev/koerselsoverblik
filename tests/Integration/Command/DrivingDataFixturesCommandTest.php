<?php

namespace App\Tests\Integration\Command;

use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\ExecutionResult;

/**
 * Checks that the command writes the scenario files and the development dataset in the layout of
 * the default mapping.
 *
 * Only one test writes all 13 months, because the files are slow to write.
 */
final class DrivingDataFixturesCommandTest extends KernelTestCase
{
    private const array DEFAULT_HEADER = [
        'RouteStopID', 'RouteName', 'PersonID', 'Date', 'blocknumber', 'StopType', 'Status',
        'passengersaftercancel', 'ToNextDistance', 'ToNextDuration', 'PlannedDeparture', 'StatusUpdateTime',
        'kørselstype',
    ];

    /**
     * Files from an earlier run must not hide a file that the command did not write.
     */
    protected function setUp(): void
    {
        foreach (glob(self::directory().'/*.xlsx') ?: [] as $file) {
            unlink($file);
        }
    }

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
        self::runFixturesCommand(['--months' => '1']);

        $rows = self::readRows(self::directory().'/base.xlsx');

        self::assertSame(self::DEFAULT_HEADER, $rows[2]);
        self::assertCount(14, array_slice($rows, 3));
    }

    public function testNextRunReplacesTheMonthsOfTheEarlierRun(): void
    {
        self::runFixturesCommand(['--months' => '2']);
        self::runFixturesCommand(['--months' => '2', '--last-month' => '2026-05']);

        self::assertSame(
            ['dev-2026-04.xlsx', 'dev-2026-05.xlsx'],
            array_map(basename(...), glob(self::directory().'/dev-*.xlsx') ?: []),
        );
    }

    /**
     * A scenario that is renamed or removed must not leave its old file.
     */
    public function testNextRunDeletesTheFileOfAScenarioThatIsGone(): void
    {
        self::runFixturesCommand(['--months' => '1']);
        copy(self::directory().'/base.xlsx', self::directory().'/old_scenario.xlsx');

        self::runFixturesCommand(['--months' => '1']);

        self::assertFileDoesNotExist(self::directory().'/old_scenario.xlsx');
    }

    public function testLastMonthIsTheCurrentMonthByDefault(): void
    {
        $result = self::runCommand('app:fixtures:driving-data', ['--months' => '1']);

        $this->assertCommandIsSuccessful($result);
        self::assertFileExists(self::directory().'/dev-'.(new \DateTimeImmutable())->format('Y-m').'.xlsx');
    }

    public function testLastMonthThatIsNotYearAndMonthIsRejected(): void
    {
        $result = self::runCommand('app:fixtures:driving-data', ['--last-month' => 'February']);

        $this->assertCommandIsInvalid($result);
        self::assertStringContainsString('YYYY-MM', $result->getDisplay());
    }

    public function testMonthsBelowOneIsRejected(): void
    {
        $result = self::runCommand('app:fixtures:driving-data', ['--months' => '0']);

        $this->assertCommandIsInvalid($result);
        self::assertStringContainsString('at least 1', $result->getDisplay());
    }

    /**
     * A fixed seed and last month give fixed files.
     *
     * @param array<string, string> $options
     */
    private static function runFixturesCommand(array $options = []): ExecutionResult
    {
        return self::runCommand('app:fixtures:driving-data', [...['--seed' => '7', '--last-month' => '2026-02'], ...$options]);
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
