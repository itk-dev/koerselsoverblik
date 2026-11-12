<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\ExecutionResult;

/**
 * Checks that the command writes the scenario files and the development dataset in the layout of
 * the default mapping, with one file for each ISO week.
 *
 * The tests write few months, because a week of the development dataset is slow to write.
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

    /**
     * January and February 2026 are in the ISO weeks from Monday 29 December 2025 to Monday
     * 23 February 2026.
     */
    public function testCommandWritesTheScenariosAndOneFileForEachWeek(): void
    {
        $result = self::runFixturesCommand(['--months' => '2']);

        $this->assertCommandIsSuccessful($result);
        self::assertStringContainsString('13 files', $result->getDisplay());

        $directory = self::directory();
        foreach (['base', 'range_edges', 'bad_values', 'duplicate_row_ids'] as $scenario) {
            self::assertFileExists($directory.'/'.$scenario.'.xlsx');
        }
        $weeks = array_map(basename(...), glob($directory.'/dev-*.xlsx') ?: []);
        self::assertCount(9, $weeks);
        self::assertSame('dev-2025-12-29.xlsx', $weeks[0]);
        self::assertSame('dev-2026-02-23.xlsx', $weeks[8]);
    }

    public function testPunctualityComesFromTheEnvironment(): void
    {
        $result = self::runFixturesCommand(['--months' => '1']);

        self::assertStringContainsString('Punctuality: 65 % in the first month, +2.5 percentage points for each month.', $result->getDisplay());
    }

    public function testOptionsOverrideThePunctuality(): void
    {
        $result = self::runFixturesCommand(['--months' => '1', '--punctuality-start' => '80', '--punctuality-trend' => '-1.5']);

        self::assertStringContainsString('Punctuality: 80 % in the first month, -1.5 percentage points for each month.', $result->getDisplay());
    }

    public function testPunctualityOutsideZeroToHundredIsRejected(): void
    {
        $result = self::runFixturesCommand(['--punctuality-start' => '101']);

        $this->assertCommandIsInvalid($result);
        self::assertStringContainsString('from 0 to 100', $result->getDisplay());
    }

    public function testBaseFileHasTheLayoutOfTheDefaultMapping(): void
    {
        self::runFixturesCommand(['--months' => '1']);

        $rows = self::readRows(self::directory().'/base.xlsx');

        self::assertSame(self::DEFAULT_HEADER, array_slice($rows[2], 0, 13));
        // The 43 kept columns and the 6 excluded columns of the default mapping follow the field
        // columns.
        self::assertCount(62, $rows[2]);
        self::assertSame(['PersonName', 'Adresse', 'Post-nr', 'By', 'Labels', 'Comment'], array_slice($rows[2], 56));
        self::assertCount(14, array_slice($rows, 3));
    }

    /**
     * May 2026 is in the ISO weeks from Monday 27 April to Monday 25 May.
     */
    public function testNextRunReplacesTheWeeksOfTheEarlierRun(): void
    {
        self::runFixturesCommand(['--months' => '1']);
        self::runFixturesCommand(['--months' => '1', '--last-month' => '2026-05']);

        self::assertSame(
            ['dev-2026-04-27.xlsx', 'dev-2026-05-04.xlsx', 'dev-2026-05-11.xlsx', 'dev-2026-05-18.xlsx', 'dev-2026-05-25.xlsx'],
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

    /**
     * At 22:30 UTC on 30 June it is 1 July in Copenhagen.
     */
    public function testLastMonthIsTheCurrentMonthInTheApplicationTimeZoneByDefault(): void
    {
        self::getContainer()->set('clock', new MockClock('2026-06-30 22:30:00', 'UTC'));

        $result = self::runCommand('app:fixtures:driving-data', ['--months' => '1']);

        $this->assertCommandIsSuccessful($result);
        $weeks = array_map(basename(...), glob(self::directory().'/dev-*.xlsx') ?: []);
        self::assertSame('dev-2026-06-29.xlsx', $weeks[0]);
        self::assertSame('dev-2026-07-27.xlsx', end($weeks));
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
