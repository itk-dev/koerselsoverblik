<?php

declare(strict_types=1);

namespace App\Tests\Integration\Import;

use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Entity\RejectedStopRow;
use App\Entity\RouteRun;
use App\Entity\SourceRow;
use App\Entity\StopRow;
use App\Import\ClearedRowsDto;
use App\Import\DrivingDataClearer;
use App\Import\DrivingDataImporter;
use App\Import\ImportRangeDto;
use App\Import\Mapping\MappingProvider;
use App\Import\MonthCitizensBuilder;
use App\Import\StopRowRangeDeleter;
use App\Repository\LocationDayTotalsRepository;
use App\Repository\RejectedStopRowRepository;
use App\Repository\RouteDayTotalsRepository;
use App\Repository\RouteRunRepository;
use App\Repository\SourceRowRepository;
use App\Repository\StopRowRepository;
use App\Tests\BaseScenarioTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks the clear of the driving data of a range: it deletes the data of the range only, in one
 * transaction, and logs who cleared which range with the counts.
 */
final class DrivingDataClearerTest extends KernelTestCase
{
    use BaseScenarioTrait;

    private const string SCENARIOS = __DIR__.'/../../fixtures/driving_data';

    private EntityManagerInterface $entityManager;

    private TestHandler $log;

    protected function setUp(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        $this->log = new TestHandler();
    }

    /**
     * The count changes no data.
     */
    public function testCountGivesTheRowsOfTheRange(): void
    {
        $this->importBaseOn('2026-01-05');
        $this->importBaseOn('2026-01-06');

        $counts = $this->clearer()->count(self::range('2026-01-05'));

        self::assertEquals(new ClearedRowsDto(14, 4, 0), $counts);
        self::assertSame(28, $this->countRows('stop_row'));
    }

    /**
     * The clear deletes the stop rows, their copies, the route runs and the totals of the range.
     * It builds the citizens of the month again. A month with no stop rows then has no count. The
     * other dates and the batches do not change.
     */
    public function testClearDeletesTheDrivingDataOfTheRangeOnly(): void
    {
        $this->importBaseOn('2025-12-05');
        $this->importBaseOn('2026-01-05');

        $cleared = $this->clearer()->clear(self::range('2025-12-05'), 'admin@example.com');

        self::assertEquals(new ClearedRowsDto(14, 4, 0), $cleared);
        foreach (['stop_row', 'route_run', 'route_day_totals', 'location_day_totals'] as $table) {
            self::assertSame(0, $this->countRows($table, '2025-12-05'), $table);
            self::assertGreaterThan(0, $this->countRows($table, '2026-01-05'), $table);
        }
        self::assertSame(14, $this->countRows('source_row'));
        self::assertSame(2, $this->countRows('import_batch'));
        self::assertSame(['2026-01-01'], $this->entityManager->getConnection()->fetchFirstColumn('SELECT first_date FROM month_citizens WHERE supplier_id IS NULL'));
    }

    /**
     * The clear deletes the rejected rows of the range and their copies. A rejected row with no
     * date belongs to the range when the whole range of its batch is in the range.
     */
    public function testClearDeletesTheRejectedRowsOfTheRange(): void
    {
        $this->importScenario('bad_values', '2026-01-05');
        $rejected = $this->countRows('rejected_stop_row');

        $cleared = $this->clearer()->clear(self::range('2026-01-05'), 'admin@example.com');

        self::assertSame($rejected, $cleared->rejectedRows);
        self::assertGreaterThan(0, $cleared->rejectedRows);
        self::assertSame(0, $this->countRows('rejected_stop_row'));
        self::assertSame(0, $this->countRows('source_row'));
    }

    /**
     * A rejected row with no date of a batch that is larger than the cleared range stays, because
     * its date is not known.
     */
    public function testClearKeepsARejectedRowWithNoDateOfALargerBatch(): void
    {
        $this->importScenario('bad_values', '2026-01-05', '2026-01-06');
        $noDate = $this->countRows('rejected_stop_row', null, 'service_date IS NULL');

        $cleared = $this->clearer()->clear(self::range('2026-01-05'), 'admin@example.com');

        self::assertGreaterThan(0, $noDate);
        self::assertSame($noDate, $this->countRows('rejected_stop_row'));
        self::assertSame($noDate, $this->countRows('source_row'));
        self::assertGreaterThan(0, $cleared->rejectedRows);
    }

    /**
     * The log has who cleared which range, and the counts. It has no field values.
     */
    public function testClearLogsTheUserTheRangeAndTheCounts(): void
    {
        $this->importBaseOn('2026-01-05');

        $this->clearer()->clear(self::range('2026-01-05', '2026-01-06'), 'admin@example.com');

        $records = $this->log->getRecords();
        self::assertCount(1, $records);
        self::assertSame(Level::Info, $records[0]->level);
        self::assertSame('admin@example.com cleared the driving data from 2026-01-05 to 2026-01-06: 14 stop rows, 4 route runs and 0 rejected rows', $records[0]->message);
        self::assertSame(
            ['user' => 'admin@example.com', 'first_date' => '2026-01-05', 'last_date' => '2026-01-06', 'stop_rows' => 14, 'route_runs' => 4, 'rejected_rows' => 0],
            $records[0]->context,
        );
    }

    public function testClearFromTheConsoleLogsTheConsole(): void
    {
        $this->clearer()->clear(self::range('2026-01-05'), null);

        self::assertSame('console cleared the driving data from 2026-01-05 to 2026-01-05: 0 stop rows, 0 route runs and 0 rejected rows', $this->log->getRecords()[0]->message);
    }

    /**
     * The citizens of the months are built last. A failure there undoes the deletes, and the clear
     * writes no log.
     */
    public function testFailedClearLeavesTheDataAsItWas(): void
    {
        $this->importBaseOn('2026-01-05');
        $tables = ['stop_row', 'source_row', 'route_run', 'route_day_totals', 'location_day_totals', 'month_citizens', 'import_batch'];
        $before = array_map($this->countRows(...), $tables);
        $this->entityManager->getEventManager()->addEventListener([Events::onFlush], new class {
            public function onFlush(): never
            {
                throw new \RuntimeException('The flush failed.');
            }
        });

        try {
            $this->clearer()->clear(self::range('2026-01-05'), 'admin@example.com');
            self::fail('The clear did not fail.');
        } catch (\RuntimeException $exception) {
            self::assertSame('The flush failed.', $exception->getMessage());
        }

        self::assertSame($before, array_map($this->countRows(...), $tables));
        self::assertSame([], $this->log->getRecords());
    }

    private function clearer(): DrivingDataClearer
    {
        $stopRows = $this->entityManager->getRepository(StopRow::class);
        self::assertInstanceOf(StopRowRepository::class, $stopRows);
        $routeRuns = $this->entityManager->getRepository(RouteRun::class);
        self::assertInstanceOf(RouteRunRepository::class, $routeRuns);
        $rejectedRows = $this->entityManager->getRepository(RejectedStopRow::class);
        self::assertInstanceOf(RejectedStopRowRepository::class, $rejectedRows);
        $sourceRows = $this->entityManager->getRepository(SourceRow::class);
        self::assertInstanceOf(SourceRowRepository::class, $sourceRows);
        $routeTotals = self::getContainer()->get(RouteDayTotalsRepository::class);
        self::assertInstanceOf(RouteDayTotalsRepository::class, $routeTotals);
        $locationTotals = self::getContainer()->get(LocationDayTotalsRepository::class);
        self::assertInstanceOf(LocationDayTotalsRepository::class, $locationTotals);
        $monthCitizens = self::getContainer()->get(MonthCitizensBuilder::class);
        self::assertInstanceOf(MonthCitizensBuilder::class, $monthCitizens);

        return new DrivingDataClearer(
            new StopRowRangeDeleter($stopRows, $rejectedRows, $sourceRows),
            $stopRows,
            $routeRuns,
            $rejectedRows,
            $routeTotals,
            $locationTotals,
            $monthCitizens,
            $this->entityManager,
            new Logger('driving_data_audit', [$this->log]),
        );
    }

    private function importScenario(string $name, string $firstDate, ?string $lastDate = null): void
    {
        $path = sys_get_temp_dir().'/'.uniqid('extract_', true).'.xlsx';
        $provider = self::getContainer()->get(MappingProvider::class);
        self::assertInstanceOf(MappingProvider::class, $provider);
        new ExtractWriter()->write($path, $provider->default(), new ScenarioLoader()->load(self::SCENARIOS.'/'.$name.'.yaml')->rows);
        $importer = self::getContainer()->get(DrivingDataImporter::class);
        self::assertInstanceOf(DrivingDataImporter::class, $importer);
        $importer->import($path, self::range($firstDate, $lastDate), $provider->default(), null, $name.'.xlsx');
        unlink($path);
    }

    /**
     * ORM closes the entity manager after a failed transaction, so the count uses the connection.
     */
    private function countRows(string $table, ?string $date = null, ?string $condition = null): int
    {
        $condition ??= null === $date ? null : 'service_date = ?';
        $sql = 'SELECT COUNT(*) FROM '.$table.(null === $condition ? '' : ' WHERE '.$condition);
        $count = $this->entityManager->getConnection()->fetchOne($sql, null === $date ? [] : [$date]);
        self::assertIsNumeric($count);

        return (int) $count;
    }

    private static function range(string $firstDate, ?string $lastDate = null): ImportRangeDto
    {
        return ImportRangeDto::create(new \DateTimeImmutable($firstDate), new \DateTimeImmutable($lastDate ?? $firstDate), 31);
    }
}
