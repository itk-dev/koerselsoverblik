<?php

declare(strict_types=1);

namespace App\Tests\Integration\Import;

use App\DataFixtures\DrivingData\ExtractRow;
use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Entity\ImportBatch;
use App\Entity\RejectedStopRow;
use App\Entity\SourceRow;
use App\Entity\StopRow;
use App\Import\DrivingDataImporter;
use App\Import\Extract\MissingColumnsException;
use App\Import\Extract\RejectionReason;
use App\Import\Extract\StopRowParser;
use App\Import\ImportRangeDto;
use App\Import\LocationDayTotalsBuilder;
use App\Import\Mapping\FieldMappingDto;
use App\Import\Mapping\FieldName;
use App\Import\Mapping\MappingDto;
use App\Import\Mapping\MappingProvider;
use App\Import\MonthCitizensBuilder;
use App\Import\RouteBuilder;
use App\Import\RouteDayTotalsBuilder;
use App\Import\RowIdConflictException;
use App\Import\StopRowReplacer;
use App\Import\StoredCopyException;
use App\Import\StoredCopyReader;
use App\Report\DateRangeDto;
use App\Report\DayTotalsDto;
use App\Repository\RejectedStopRowRepository;
use App\Repository\RouteDayTotalsRepository;
use App\Repository\StopRowRepository;
use App\Tests\BaseScenarioTrait;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks that the stored copies of a range are read again through a mapping (ADR 006). The base
 * scenario has 14 stop rows on each date, with 7 trips, 1 of them cancelled too late.
 */
final class StoredCopyReaderTest extends KernelTestCase
{
    use BaseScenarioTrait;

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
     * With the same mapping, the read gives the same stop rows, copies and totals.
     */
    public function testReadWithTheSameMappingChangesNothing(): void
    {
        $this->importBaseOn('2026-01-05');
        $this->importBaseOn('2026-01-06');
        $copies = $this->copies();
        $totals = $this->totals();

        $result = $this->reader()->read(self::january(), $this->defaultMapping());

        self::assertSame([2, 28, 0, 28], [$result->dates, $result->stopRows, $result->rejectedRows, $result->deletedRows]);
        self::assertEquals($copies, $this->copies());
        self::assertEquals($totals, $this->totals());
        // Each date gets its own batch, so that the list of the uploaded dates shows the read.
        $batches = $this->entityManager->getRepository(ImportBatch::class)->findBy(['fileName' => StoredCopyReader::FILE_NAME]);
        self::assertCount(2, $batches);
    }

    /**
     * A stop row that was imported before a new field has no value. The read fills the field from
     * the copy, and builds the totals again.
     */
    public function testReadFillsANewField(): void
    {
        $this->importBaseOn('2026-01-05');
        $this->entityManager->getConnection()->executeStatement('UPDATE stop_row SET cancelled_in_time = NULL');
        $this->rebuildTotals();
        self::assertSame(0, $this->totals()->cancelledLateTrips);

        $this->reader()->read(self::january(), $this->defaultMapping());

        self::assertSame(1, $this->totals()->cancelledLateTrips);
    }

    /**
     * A row that a mapping error rejected becomes a stop row when the mapping is correct.
     */
    public function testRejectedRowBecomesAStopRow(): void
    {
        $this->importWithAnUnknownStatus();
        self::assertSame(1, $this->rowsOf(RejectedStopRow::class));

        $result = $this->reader()->read(self::january(), $this->mappingWithStatus('Missed', 'cancelled'));

        self::assertSame([15, 0], [$result->stopRows, $result->rejectedRows]);
        self::assertSame(0, $this->rowsOf(RejectedStopRow::class));
        self::assertSame(2, $this->totals()->cancelledTrips);
    }

    /**
     * The preview counts with the mapping, and changes no data.
     */
    public function testPreviewCountsAndChangesNoData(): void
    {
        $this->importWithAnUnknownStatus();

        $preview = $this->reader()->preview(self::january(), $this->mappingWithStatus('Missed', 'cancelled'));

        self::assertSame(['2026-01-05'], $preview->dates);
        self::assertSame([14, 1], [$preview->storedStopRows, $preview->storedRejectedRows]);
        self::assertSame([15, 0], [$preview->summary->stopRows, $preview->summary->rejectedRows]);
        self::assertSame([[], []], [$preview->missingRequiredColumns, $preview->missingOptionalColumns]);
        self::assertSame(1, $this->rowsOf(RejectedStopRow::class));
    }

    /**
     * A field column that is in no copy of the range: a required field stops the read before any
     * change, as a file with a missing column. An optional field is only named.
     */
    public function testFieldColumnInNoCopy(): void
    {
        $this->importBaseOn('2026-01-05');
        $mapping = $this->mappingWithColumns([FieldName::Route->value => 'NoRoute', FieldName::LocationName->value => 'NoPlace']);

        $preview = $this->reader()->preview(self::january(), $mapping);

        self::assertSame(['NoRoute'], $preview->missingRequiredColumns);
        self::assertSame(['NoPlace'], $preview->missingOptionalColumns);
        try {
            $this->reader()->read(self::january(), $mapping);
            self::fail('The read must stop.');
        } catch (MissingColumnsException $exception) {
            self::assertSame(['NoRoute'], $exception->columns);
        }
        self::assertSame(14, $this->rowsOf(StopRow::class));
        self::assertCount(0, $this->log->getRecords());
    }

    /**
     * An optional field column in no copy gives no value, and the read continues.
     */
    public function testOptionalFieldColumnInNoCopyGivesNoValue(): void
    {
        $this->importBaseOn('2026-01-05');

        $this->reader()->read(self::january(), $this->mappingWithColumns([FieldName::LocationName->value => 'NoPlace']));

        $locations = $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM stop_row WHERE location_name IS NOT NULL');
        self::assertIsNumeric($locations);
        self::assertSame(0, (int) $locations);
        self::assertSame(14, $this->rowsOf(StopRow::class));
    }

    /**
     * The read would only delete the stop rows of the date, so it stops, and the date keeps its
     * rows. A mapping with a wrong value map is the usual cause.
     */
    public function testDateWithNoStopRowsKeepsItsRows(): void
    {
        $this->importBaseOn('2026-01-05');
        $mapping = $this->changedMapping(static fn (FieldName $name, FieldMappingDto $field): FieldMappingDto => FieldName::StopStatus !== $name ? $field : new FieldMappingDto(
            $field->column,
            $field->type,
            $field->required,
            $field->format,
            ['Other' => 'completed'],
            $field->trueValues,
            $field->falseValues,
        ));

        $this->expectExceptionObject(StoredCopyException::noStopRows(new \DateTimeImmutable('2026-01-05')));
        try {
            $this->reader()->read(self::january(), $mapping);
        } finally {
            self::assertSame(14, $this->rowsOf(StopRow::class));
        }
    }

    /**
     * The read replaces one date at a time. A copy that gives another date would be lost, so the
     * read stops, and the date keeps its rows. The dates before it are read.
     */
    public function testCopyWithAnotherDateStops(): void
    {
        $this->importBaseOn('2026-01-05');
        $this->importBaseOn('2026-01-06');
        $this->entityManager->getConnection()->executeStatement(
            "UPDATE source_row SET source_values = JSON_SET(source_values, '$.Date', '2026-01-07') WHERE id = (SELECT MAX(source_row_id) FROM stop_row WHERE service_date = '2026-01-06')",
        );

        $this->expectExceptionObject(StoredCopyException::otherDate(new \DateTimeImmutable('2026-01-06'), 14));
        try {
            $this->reader()->read(self::january(), $this->defaultMapping());
        } finally {
            self::assertCount(1, $this->entityManager->getRepository(ImportBatch::class)->findBy(['fileName' => StoredCopyReader::FILE_NAME]));
            self::assertSame(28, $this->rowsOf(StopRow::class));
        }
    }

    /**
     * As in an import, a row id that is stored on another date stops the read of the date.
     */
    public function testRowIdOnAnotherDateStops(): void
    {
        $this->importBaseOn('2026-01-05');
        $this->importBaseOn('2026-01-06');
        $this->entityManager->getConnection()->executeStatement(
            "UPDATE source_row SET source_values = JSON_SET(source_values, '$.RouteStopID', '2026-01-05-0') WHERE id = (SELECT MAX(source_row_id) FROM stop_row WHERE service_date = '2026-01-06')",
        );

        $this->expectException(RowIdConflictException::class);
        try {
            $this->reader()->read(self::january(), $this->defaultMapping());
        } finally {
            self::assertSame(28, $this->rowsOf(StopRow::class));
        }
    }

    /**
     * A date with no stored rows is not changed, so the read does not empty it.
     */
    public function testRangeWithNoStoredRowsChangesNothing(): void
    {
        $preview = $this->reader()->preview(self::january(), $this->defaultMapping());
        $result = $this->reader()->read(self::january(), $this->defaultMapping());

        self::assertSame([], $preview->dates);
        self::assertSame([0, 0, 0, 0], [$result->dates, $result->stopRows, $result->rejectedRows, $result->deletedRows]);
    }

    /**
     * The log has the range, the mapping and the counts. It has no field values.
     */
    public function testReadLogsTheRangeAndTheCounts(): void
    {
        $this->importBaseOn('2026-01-05');

        $this->reader()->read(self::january(), $this->defaultMapping());

        $records = $this->log->getRecords();
        self::assertCount(1, $records);
        self::assertSame(Level::Info, $records[0]->level);
        self::assertSame('console read the stored copies from 2026-01-01 to 2026-01-31 again through the mapping "default": 1 dates, 14 stop rows and 0 rejected rows', $records[0]->message);
        self::assertSame(
            ['user' => 'console', 'first_date' => '2026-01-01', 'last_date' => '2026-01-31', 'mapping' => 'default', 'dates' => 1, 'stop_rows' => 14, 'rejected_rows' => 0],
            $records[0]->context,
        );
    }

    private function reader(): StoredCopyReader
    {
        $container = self::getContainer();
        $services = [];
        foreach ([StopRowParser::class, StopRowReplacer::class, RouteBuilder::class, RouteDayTotalsBuilder::class, LocationDayTotalsBuilder::class, MonthCitizensBuilder::class, ClockInterface::class] as $class) {
            $service = $container->get($class);
            self::assertInstanceOf($class, $service);
            $services[] = $service;
        }
        $stopRows = $this->entityManager->getRepository(StopRow::class);
        self::assertInstanceOf(StopRowRepository::class, $stopRows);
        $rejectedRows = $this->entityManager->getRepository(RejectedStopRow::class);
        self::assertInstanceOf(RejectedStopRowRepository::class, $rejectedRows);

        return new StoredCopyReader(...$services, ...[
            'stopRows' => $stopRows,
            'rejectedRows' => $rejectedRows,
            'entityManager' => $this->entityManager,
            'auditLogger' => new Logger('test', [$this->log]),
        ]);
    }

    private static function january(): DateRangeDto
    {
        return DateRangeDto::create(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-01-31'));
    }

    private function defaultMapping(): MappingDto
    {
        $provider = self::getContainer()->get(MappingProvider::class);
        self::assertInstanceOf(MappingProvider::class, $provider);

        return $provider->default();
    }

    /**
     * Gives the default mapping with another column for some fields.
     *
     * @param array<string, string> $columns the new column, keyed by the FieldName value
     */
    private function mappingWithColumns(array $columns): MappingDto
    {
        return $this->changedMapping(static fn (FieldName $name, FieldMappingDto $field): FieldMappingDto => new FieldMappingDto(
            $columns[$name->value] ?? $field->column,
            $field->type,
            $field->required,
            $field->format,
            $field->valueMap,
            $field->trueValues,
            $field->falseValues,
        ));
    }

    /**
     * Gives the default mapping with one more code of the stop status.
     */
    private function mappingWithStatus(string $code, string $status): MappingDto
    {
        return $this->changedMapping(static fn (FieldName $name, FieldMappingDto $field): FieldMappingDto => FieldName::StopStatus !== $name ? $field : new FieldMappingDto(
            $field->column,
            $field->type,
            $field->required,
            $field->format,
            [...$field->valueMap, $code => $status],
            $field->trueValues,
            $field->falseValues,
        ));
    }

    /**
     * @param \Closure(FieldName, FieldMappingDto): FieldMappingDto $change
     */
    private function changedMapping(\Closure $change): MappingDto
    {
        $mapping = $this->defaultMapping();
        $fields = [];
        foreach (FieldName::cases() as $name) {
            $fields[$name->value] = $change($name, $mapping->field($name));
        }

        return new MappingDto($mapping->name, $mapping->sheet, $mapping->headerRow, $mapping->rowId, $fields, $mapping->keepColumns, $mapping->excludeColumns);
    }

    /**
     * Imports the base scenario and one more pickup with a status code that the default mapping
     * does not know. The import rejects that row.
     */
    private function importWithAnUnknownStatus(): void
    {
        $rows = new ScenarioLoader()->load(__DIR__.'/../../fixtures/driving_data/base.yaml')->rows;
        $rows[] = new ExtractRow(['person_id' => 'P-008'] + $rows[0]->values, 'S-MISSED', ['stop_status' => 'Missed']);
        $path = sys_get_temp_dir().'/'.uniqid('extract_', true).'.xlsx';
        new ExtractWriter()->write($path, $this->defaultMapping(), $rows);
        $importer = self::getContainer()->get(DrivingDataImporter::class);
        self::assertInstanceOf(DrivingDataImporter::class, $importer);
        $day = new \DateTimeImmutable('2026-01-05');
        $importer->import($path, ImportRangeDto::create($day, $day, 31), $this->defaultMapping(), null, 'base.xlsx');
        unlink($path);
        $rejected = $this->entityManager->getRepository(RejectedStopRow::class)->findAll();
        self::assertSame(RejectionReason::UnknownCode->value, $rejected[0]->getReason()->value);
    }

    /**
     * @return list<array<array-key, mixed>> the values of all copies, in the order of the values
     */
    private function copies(): array
    {
        $this->entityManager->clear();
        $copies = array_map(static fn (SourceRow $row): array => $row->getValues(), $this->entityManager->getRepository(SourceRow::class)->findAll());
        usort($copies, static fn (array $a, array $b): int => serialize($a) <=> serialize($b));

        return $copies;
    }

    private function totals(): DayTotalsDto
    {
        $totals = self::getContainer()->get(RouteDayTotalsRepository::class);
        self::assertInstanceOf(RouteDayTotalsRepository::class, $totals);

        return $totals->sum(self::january());
    }

    private function rebuildTotals(): void
    {
        $builder = self::getContainer()->get(RouteDayTotalsBuilder::class);
        self::assertInstanceOf(RouteDayTotalsBuilder::class, $builder);
        $builder->rebuild(ImportRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-05'), 31));
    }

    /**
     * @param class-string $class
     */
    private function rowsOf(string $class): int
    {
        return $this->entityManager->getRepository($class)->count([]);
    }
}
