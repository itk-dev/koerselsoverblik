<?php

namespace App\Tests\Unit\Import\Extract;

use App\DataFixtures\DrivingData\ExtractRow;
use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\Scenario;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Import\Extract\CellConverter;
use App\Import\Extract\ExtractInspector;
use App\Import\Extract\ExtractLimitsDto;
use App\Import\Extract\FieldWarningDto;
use App\Import\Extract\InvalidExtractException;
use App\Import\Extract\MissingColumnsException;
use App\Import\Extract\RejectedRowDto;
use App\Import\Extract\RejectionReason;
use App\Import\Extract\SourceCells;
use App\Import\Extract\StopRowDto;
use App\Import\Extract\StopRowParser;
use App\Import\ImportRangeDto;
use App\Import\Mapping\FieldMappingDto;
use App\Import\Mapping\FieldName;
use App\Import\Mapping\FieldType;
use App\Import\Mapping\MappingDto;
use App\Import\Mapping\MappingLoader;
use App\Import\Mapping\StopStatus;
use App\Import\Mapping\StopType;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\DateIntervalCell;
use OpenSpout\Common\Entity\Cell\DateTimeCell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\TestCase;

/**
 * Checks that the parser reads an extract through a mapping.
 *
 * The scenario tests write the scenarios of tests/fixtures/driving_data/ with the default
 * mapping, and compare the result with the "expected" section. The layout tests write simple
 * rows of text and numbers, and read them with a mapping for these rows.
 */
final class StopRowParserTest extends TestCase
{
    private const string DEFAULT_MAPPING = __DIR__.'/../../../../config/import/driving_data/default.yaml';

    private const string SCENARIOS = __DIR__.'/../../../fixtures/driving_data';

    /**
     * The columns of the simple mapping, in the order of the fields.
     */
    private const array HEADER = ['Route', 'Person', 'Day', 'Run', 'Kind', 'State', 'Load', 'Km', 'Minutes', 'Planned', 'Actual', 'Solo'];

    /**
     * One row for the simple mapping. The dates and times are Excel serial numbers of 5 January 2026.
     */
    private const array ROW = ['R-01', 'P-001', 46027, 1, 'P', 'C', 1, 2.5, 5, 46027.3, 46027.31, 'Y'];

    private string $path = '';

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/'.uniqid('extract_', true).'.xlsx';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function testBaseScenario(): void
    {
        $scenario = $this->writeScenario('base');

        [$rows, $rejected] = $this->parse(self::defaultMapping(), self::range($scenario));

        self::assertSame($scenario->expected['stop_rows'], \count($rows));
        self::assertSame($scenario->expected['rejected_rows'], \count($rejected));
        self::assertSame($scenario->expected['citizens'], \count(array_unique(array_map(static fn (StopRowDto $row): ?string => $row->personId, $rows))));

        $first = $rows[0];
        // The header is in row 3, so the first stop is in row 4.
        self::assertSame(4, $first->rowNumber);
        self::assertSame('1', $first->rowKey);
        self::assertSame('R-01', $first->route);
        self::assertSame('P-001', $first->personId);
        self::assertSame('2026-01-05 00:00:00', $first->serviceDate->format('Y-m-d H:i:s'));
        self::assertSame(1, $first->run);
        self::assertSame(StopType::Pickup, $first->stopType);
        self::assertSame(StopStatus::Completed, $first->stopStatus);
        self::assertSame(1, $first->passengersAfter);
        self::assertSame(2.0, $first->nextDistanceKm);
        self::assertSame(5.0, $first->nextDurationMin);
        // The extract has wall-clock times in Europe/Copenhagen. The stop row has them in UTC.
        self::assertSame('2026-01-05 06:00:00 UTC', $first->plannedTime->format('Y-m-d H:i:s e'));
        self::assertNotNull($first->actualTime);
        self::assertSame('2026-01-05 06:02:00 UTC', $first->actualTime->format('Y-m-d H:i:s e'));
        self::assertFalse($first->plannedSolo);
    }

    public function testTimeAfterMidnightIsOnTheNextDay(): void
    {
        $scenario = $this->writeScenario('base');

        [$rows] = $this->parse(self::defaultMapping(), self::range($scenario));
        $dropOff = self::find($rows, 'P-004', StopType::DropOff);

        self::assertSame('2026-01-05', $dropOff->serviceDate->format('Y-m-d'));
        // 00:20 on 6 January in Copenhagen is 23:20 on 5 January in UTC.
        self::assertSame('2026-01-05 23:20:00 UTC', $dropOff->plannedTime->format('Y-m-d H:i:s e'));
        self::assertNotNull($dropOff->actualTime);
        self::assertSame('2026-01-05 23:22:00 UTC', $dropOff->actualTime->format('Y-m-d H:i:s e'));
    }

    public function testStopWithNoRegistrationHasNoActualTime(): void
    {
        $scenario = $this->writeScenario('base');

        [$rows] = $this->parse(self::defaultMapping(), self::range($scenario));
        $pickup = self::find($rows, 'P-005', StopType::Pickup);

        self::assertSame(StopStatus::Pending, $pickup->stopStatus);
        self::assertNull($pickup->actualTime);
    }

    public function testEmptyOptionalNumberIsNull(): void
    {
        $scenario = $this->writeScenario('base');

        [$rows] = $this->parse(self::defaultMapping(), self::range($scenario));
        $dropOff = self::find($rows, 'P-007', StopType::DropOff);

        self::assertSame(0, $dropOff->passengersAfter);
        self::assertNull($dropOff->nextDistanceKm);
        self::assertNull($dropOff->nextDurationMin);
    }

    public function testBadValuesScenario(): void
    {
        $scenario = $this->writeScenario('bad_values');

        [$rows, $rejected] = $this->parse(self::defaultMapping(), self::range($scenario));

        self::assertSame($scenario->expected['stop_rows'], \count($rows));
        self::assertSame($scenario->expected['rejected_rows'], \count($rejected));
        self::assertSame(
            [
                [5, RejectionReason::Empty, FieldName::Route],
                [6, RejectionReason::NotWholeNumber, FieldName::Run],
                [9, RejectionReason::NotDate, FieldName::ServiceDate],
                [10, RejectionReason::NotTime, FieldName::PlannedTime],
                [12, RejectionReason::UnknownCode, FieldName::StopType],
            ],
            self::reasons($rejected),
        );
        // A bad value in an optional field gives a warning, not a rejected row.
        self::assertSame($scenario->expected['warnings'], array_sum(array_map(static fn (StopRowDto $row): int => \count($row->warnings), $rows)));
        self::assertSame(
            [
                [7, FieldName::NextDistanceKm, RejectionReason::NotNumber],
                [8, FieldName::PlannedSolo, RejectionReason::UnknownBoolValue],
                [11, FieldName::ActualTime, RejectionReason::NotDateTime],
            ],
            self::warnings($rows),
        );
    }

    /**
     * The copy of a row has the values on purpose. The reason of a rejection and a warning never
     * has one.
     */
    public function testReasonsAndWarningsHaveNoValueFromTheExtract(): void
    {
        $scenario = $this->writeScenario('bad_values');

        [$rows, $rejected] = $this->parse(self::defaultMapping(), self::range($scenario));
        $text = json_encode([self::reasons($rejected), self::warnings($rows)], \JSON_THROW_ON_ERROR);

        foreach (['P-00', 'R-01', 'abc', 'far', 'maybe', 'not-a-date', 'late', 'soon', 'UNKNOWN'] as $value) {
            self::assertStringNotContainsString($value, $text);
        }
    }

    public function testBadValueInAnOptionalFieldIsEmptyAndTheCopyKeepsIt(): void
    {
        $this->writeRows([self::HEADER, [...\array_slice(self::ROW, 0, 7), 'far', ...\array_slice(self::ROW, 8)]]);

        [$rows, $rejected] = $this->parse(self::simpleMapping());

        self::assertSame([], $rejected);
        self::assertCount(1, $rows);
        self::assertNull($rows[0]->nextDistanceKm);
        self::assertEquals([new FieldWarningDto(FieldName::NextDistanceKm, RejectionReason::NotNumber)], $rows[0]->warnings);
        self::assertSame('far', $rows[0]->sourceValues['Km']);
    }

    /**
     * A required field is still rejected, also when the mapping could make it optional.
     */
    public function testBadValueInARequiredFieldRejectsTheRow(): void
    {
        $this->writeRows([self::HEADER, [...\array_slice(self::ROW, 0, 6), 'many', ...\array_slice(self::ROW, 7)]]);
        $mapping = self::simpleMapping(required: [FieldName::PassengersAfter]);

        [$rows, $rejected] = $this->parse($mapping);

        self::assertSame([], $rows);
        self::assertSame([[2, RejectionReason::NotWholeNumber, FieldName::PassengersAfter]], self::reasons($rejected));
    }

    public function testRowHasACopyOfTheMappedAndTheKeptColumns(): void
    {
        $this->writeRows([
            [...self::HEADER, 'Note', 'Other'],
            [...self::ROW, ' first ', 'not kept'],
        ]);

        [$rows] = $this->parse(self::simpleMapping(keepColumns: ['Note', 'Missing']));

        self::assertSame(
            [
                'Route' => 'R-01',
                'Person' => 'P-001',
                'Day' => 46027,
                'Run' => 1,
                'Kind' => 'P',
                'State' => 'C',
                'Load' => 1,
                'Km' => 2.5,
                'Minutes' => 5,
                'Planned' => 46027.3,
                'Actual' => 46027.31,
                'Solo' => 'Y',
                // A kept column that is not in the file is not an error.
                'Note' => ' first ',
            ],
            $rows[0]->sourceValues,
        );
    }

    /**
     * A row id column is in the copy one time, also when it is the column of a field.
     */
    public function testCopyHasTheRowIdColumns(): void
    {
        $this->writeRows([['Id', ...self::HEADER], ['S-1', ...self::ROW]]);

        [$rows] = $this->parse(self::simpleMapping(rowId: ['Id', 'Run']));

        self::assertSame('S-1', $rows[0]->sourceValues['Id']);
        self::assertSame(1, $rows[0]->sourceValues['Run']);
        self::assertCount(13, $rows[0]->sourceValues);
    }

    public function testRejectedRowHasACopyAndTheServiceDate(): void
    {
        $this->writeRows([self::HEADER, [...\array_slice(self::ROW, 0, 3), 'abc', ...\array_slice(self::ROW, 4)]]);

        [, $rejected] = $this->parse(self::simpleMapping());

        self::assertSame([[2, RejectionReason::NotWholeNumber, FieldName::Run]], self::reasons($rejected));
        self::assertNotNull($rejected[0]->serviceDate);
        self::assertSame('2026-01-05 00:00:00 UTC', $rejected[0]->serviceDate->format('Y-m-d H:i:s e'));
        self::assertSame('abc', $rejected[0]->sourceValues['Run']);
        self::assertSame('R-01', $rejected[0]->sourceValues['Route']);
    }

    public function testRejectedRowWithABadDateHasACopyAndNoServiceDate(): void
    {
        $this->writeRows([self::HEADER, [...\array_slice(self::ROW, 0, 2), 'soon', ...\array_slice(self::ROW, 3)]]);

        [, $rejected] = $this->parse(self::simpleMapping());

        self::assertSame([[2, RejectionReason::NotDate, FieldName::ServiceDate]], self::reasons($rejected));
        self::assertNull($rejected[0]->serviceDate);
        self::assertSame('soon', $rejected[0]->sourceValues['Day']);
    }

    public function testRejectedRowWithoutRowIdHasACopy(): void
    {
        $this->writeRows([['Id', ...self::HEADER], ['', ...self::ROW]]);

        [, $rejected] = $this->parse(self::simpleMapping(rowId: ['Id']));

        self::assertSame([[2, RejectionReason::InvalidRowId, null]], self::reasons($rejected));
        self::assertSame('R-01', $rejected[0]->sourceValues['Route']);
        self::assertArrayNotHasKey('Id', $rejected[0]->sourceValues);
    }

    /**
     * A row outside the range belongs to another date. The upload for that date imports it, so
     * the row has no copy.
     */
    public function testRowOutsideTheRangeHasNoCopy(): void
    {
        $scenario = $this->writeScenario('range_edges');

        [, $rejected] = $this->parse(self::defaultMapping(), self::range($scenario));

        self::assertSame([[8, RejectionReason::OutsideRange, FieldName::ServiceDate]], self::reasons($rejected));
        self::assertSame([], $rejected[0]->sourceValues);
        self::assertNotNull($rejected[0]->serviceDate);
        self::assertSame('2026-01-08', $rejected[0]->serviceDate->format('Y-m-d'));
    }

    /**
     * The copies, written to a new file and read again through the same mapping, give the same
     * stop rows.
     */
    public function testCopiesReadAgainGiveTheSameRows(): void
    {
        $scenario = $this->writeScenario('base');
        $mapping = self::defaultMapping();
        [$rows] = $this->parse($mapping, self::range($scenario));

        $columns = array_keys($rows[0]->sourceValues);
        // The file needs a number format, so that the reader gives a duration or a date again.
        $styles = [DateIntervalCell::class => new Style(format: '[hh]:mm:ss'), DateTimeCell::class => new Style(format: 'yyyy-mm-dd hh:mm:ss')];
        $copies = [];
        foreach ($rows as $row) {
            $cells = SourceCells::cells($row->sourceValues);
            $copies[] = new Row(array_map(
                static function (string $column) use ($cells, $styles): Cell {
                    $cell = $cells[$column] ?? new EmptyCell(null);

                    return isset($styles[$cell::class]) ? $cell->withStyle($styles[$cell::class]) : $cell;
                },
                $columns,
            ));
        }
        $writer = new Writer();
        $writer->openToFile($this->path);
        $writer->addRow(Row::fromValues([ExtractWriter::TITLE]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues($columns));
        $writer->addRows($copies);
        $writer->close();

        [$again] = $this->parse($mapping, self::range($scenario));

        self::assertEquals($rows, $again);
    }

    public function testRangeEdgesScenario(): void
    {
        $scenario = $this->writeScenario('range_edges');

        [$rows, $rejected] = $this->parse(self::defaultMapping(), self::range($scenario));

        self::assertSame($scenario->expected['stop_rows'], \count($rows));
        self::assertSame([[8, RejectionReason::OutsideRange, FieldName::ServiceDate]], self::reasons($rejected));
    }

    /**
     * The validate command has no range, so the parser accepts all dates.
     */
    public function testParserWithoutRangeAcceptsAllDates(): void
    {
        $this->writeScenario('range_edges');

        $rows = iterator_to_array($this->parser()->parse($this->path, self::defaultMapping(), null), false);

        // The 4 rows in the range of the scenario, and the 1 row outside it.
        self::assertCount(5, $rows);
        self::assertContainsOnlyInstancesOf(StopRowDto::class, $rows);
    }

    /**
     * The parser gives each row its key. The replacement of the stop rows rejects a key that
     * occurs more than once.
     */
    public function testDuplicateRowIdsScenario(): void
    {
        $scenario = $this->writeScenario('duplicate_row_ids');

        [$rows, $rejected] = $this->parse(self::defaultMapping(), self::range($scenario));

        self::assertSame(['D-1', 'D-2', 'D-1'], array_map(static fn (StopRowDto $row): ?string => $row->rowKey, $rows));
        self::assertSame([], $rejected);
    }

    public function testEmptyFieldThatTheMappingRequiresIsRejected(): void
    {
        $values = self::scenarioValues();
        $values['passengers_after'] = null;
        (new ExtractWriter())->write($this->path, self::defaultMapping(), [new ExtractRow($values)]);

        [, $rejected] = $this->parse(self::defaultMapping());

        self::assertSame([[4, RejectionReason::Empty, FieldName::PassengersAfter]], self::reasons($rejected));
    }

    public function testRowIdHasNoSpacesAtTheStartAndTheEnd(): void
    {
        (new ExtractWriter())->write($this->path, self::defaultMapping(), [
            new ExtractRow(self::scenarioValues(), rowId: ' D-1 '),
            new ExtractRow(self::scenarioValues(), rowId: '  '),
        ]);

        [$rows, $rejected] = $this->parse(self::defaultMapping());

        self::assertSame('D-1', $rows[0]->rowKey);
        self::assertSame([[5, RejectionReason::InvalidRowId, null]], self::reasons($rejected));
    }

    public function testRowIdThatIsMissingOrADecimalNumberIsRejected(): void
    {
        $this->writeRows([
            [...self::HEADER, 'Id'],
            self::ROW,
            [...self::ROW, 1.5],
        ]);

        [, $rejected] = $this->parse(self::simpleMapping(rowId: ['Id']));

        self::assertSame([[2, RejectionReason::InvalidRowId, null], [3, RejectionReason::InvalidRowId, null]], self::reasons($rejected));
    }

    public function testRowWithOnlyTextIsNotEmpty(): void
    {
        $this->writeRows([self::HEADER, ['R-01', 'P-001']]);

        [, $rejected] = $this->parse(self::simpleMapping());

        self::assertSame([[2, RejectionReason::Empty, FieldName::ServiceDate]], self::reasons($rejected));
    }

    public function testMissingColumnRejectsTheFile(): void
    {
        (new ExtractWriter())->write($this->path, self::defaultMapping(), [new ExtractRow(self::scenarioValues())], [FieldName::Run]);

        $this->expectException(MissingColumnsException::class);
        $this->expectExceptionMessage('The extract has no columns "blocknumber".');

        $this->parse(self::defaultMapping());
    }

    /**
     * The exception names all the missing columns, so that one run of the validate command can
     * show them. The names come from the mapping. A column for a field and the row id is named
     * one time.
     */
    public function testAllMissingColumnsAreNamed(): void
    {
        $this->writeRows([\array_slice(self::HEADER, 2), \array_slice(self::ROW, 2)]);

        try {
            $this->parse(self::simpleMapping(rowId: ['Id', 'Run', 'Route']));
            self::fail('The parser accepted the extract.');
        } catch (MissingColumnsException $exception) {
            self::assertSame(['Route', 'Person', 'Id'], $exception->columns);
            self::assertSame('The extract has no columns "Route", "Person", "Id".', $exception->getMessage());
        }
    }

    public function testRowWithoutRowIdHasNoKey(): void
    {
        $this->writeRows([self::HEADER, self::ROW]);

        [$rows] = $this->parse(self::simpleMapping());

        self::assertNull($rows[0]->rowKey);
    }

    public function testRowKeyJoinsTheRowIdColumns(): void
    {
        $this->writeRows([[...self::HEADER, 'Id'], [...self::ROW, 'A-7']]);

        [$rows] = $this->parse(self::simpleMapping(rowId: ['Id', 'Run']));

        self::assertSame("A-7\u{1F}1", $rows[0]->rowKey);
    }

    public function testMissingRowIdColumnRejectsTheFile(): void
    {
        $this->writeRows([self::HEADER, self::ROW]);

        $this->expectException(MissingColumnsException::class);
        $this->expectExceptionMessage('The extract has no columns "Id".');

        $this->parse(self::simpleMapping(rowId: ['Id']));
    }

    public function testRowsAboveTheHeaderAreNotRead(): void
    {
        $this->writeRows([['Title'], [], self::HEADER, self::ROW]);

        [$rows, $rejected] = $this->parse(self::simpleMapping(headerRow: 3));

        self::assertSame([4], array_map(static fn (StopRowDto $row): int => $row->rowNumber, $rows));
        self::assertSame([], $rejected);
    }

    public function testEmptyRowsAreSkippedAndTheRowNumbersStayTheSame(): void
    {
        $this->writeRows([self::HEADER, self::ROW, [], ['  ', null], self::ROW]);

        [$rows, $rejected] = $this->parse(self::simpleMapping());

        self::assertSame([2, 5], array_map(static fn (StopRowDto $row): int => $row->rowNumber, $rows));
        self::assertSame([], $rejected);
    }

    public function testHeaderNamesHaveNoSpacesAndTheFirstColumnWithANameIsUsed(): void
    {
        $header = self::HEADER;
        $header[0] = ' Route ';
        $this->writeRows([[...$header, 'Route', new \DateTimeImmutable('2026-01-05')], [...self::ROW, 'R-99', 'X']]);

        [$rows] = $this->parse(self::simpleMapping());

        self::assertSame('R-01', $rows[0]->route);
    }

    public function testShortRowHasEmptyCells(): void
    {
        $this->writeRows([self::HEADER, \array_slice(self::ROW, 0, 10)]);

        [$rows] = $this->parse(self::simpleMapping());

        self::assertNull($rows[0]->actualTime);
        self::assertNull($rows[0]->plannedSolo);
    }

    public function testMissingSheetRejectsTheFile(): void
    {
        $this->writeRows([self::HEADER, self::ROW]);

        $this->expectException(InvalidExtractException::class);
        $this->expectExceptionMessage('The extract has no sheet with the index 1.');

        $this->parse(self::simpleMapping(sheet: 1));
    }

    public function testMissingHeaderRowRejectsTheFile(): void
    {
        $this->writeRows([self::HEADER, self::ROW]);

        $this->expectException(InvalidExtractException::class);
        $this->expectExceptionMessage('The extract has no header row 5.');

        $this->parse(self::simpleMapping(headerRow: 5));
    }

    /**
     * The inspector checks the whole file before the first row, so the parser gives no rows.
     */
    public function testExtractAboveALimitGivesNoRows(): void
    {
        $this->writeRows([self::HEADER, self::ROW, self::ROW]);

        $rows = [];
        try {
            foreach ($this->parser(new ExtractLimitsDto(1_000_000, 1_000_000, 2, 100))->parse($this->path, self::simpleMapping(), self::defaultRange()) as $row) {
                $rows[] = $row;
            }
            self::fail('The parser accepted the extract.');
        } catch (InvalidExtractException $exception) {
            self::assertSame('The extract has more than 2 rows.', $exception->getMessage());
        }

        self::assertSame([], $rows);
    }

    /**
     * The header has the names with no spaces at the start and the end. A cell with no name is
     * left out, and a name occurs one time.
     */
    public function testHeaderGivesTheColumnNames(): void
    {
        $this->writeRows([['Title'], [' Route ', '', 'Note', 'Route', 7, new \DateTimeImmutable('2026-01-05'), '2026'], self::ROW]);

        // A name that is a number in text stays text.
        self::assertSame(['Route', 'Note', '2026'], $this->parser()->header($this->path, self::simpleMapping(headerRow: 2)));
    }

    public function testHeaderOfAFileWithoutTheHeaderRowIsRejected(): void
    {
        $this->writeRows([self::HEADER]);

        $this->expectException(InvalidExtractException::class);
        $this->expectExceptionMessage('The extract has no header row 2.');

        $this->parser()->header($this->path, self::simpleMapping(headerRow: 2));
    }

    public function testHeaderOfAFileAboveALimitIsRejected(): void
    {
        $this->writeRows([self::HEADER, self::ROW, self::ROW]);

        $this->expectException(InvalidExtractException::class);
        $this->expectExceptionMessage('The extract has more than 2 rows.');

        $this->parser(new ExtractLimitsDto(1_000_000, 1_000_000, 2, 100))->header($this->path, self::simpleMapping());
    }

    public function testArchiveThatOpenSpoutCannotReadIsRejected(): void
    {
        $zip = new \ZipArchive();
        $zip->open($this->path, \ZipArchive::CREATE);
        $zip->addFromString('notes.txt', 'Not a workbook');
        $zip->close();

        $this->expectException(InvalidExtractException::class);
        $this->expectExceptionMessage('The extract is not a valid xlsx file.');

        $this->parse(self::simpleMapping());
    }

    /**
     * @return array{list<StopRowDto>, list<RejectedRowDto>}
     */
    private function parse(MappingDto $mapping, ?ImportRangeDto $range = null): array
    {
        $rows = [];
        $rejected = [];
        foreach ($this->parser()->parse($this->path, $mapping, $range ?? self::defaultRange()) as $row) {
            if ($row instanceof StopRowDto) {
                $rows[] = $row;
            } else {
                $rejected[] = $row;
            }
        }

        return [$rows, $rejected];
    }

    private function parser(?ExtractLimitsDto $limits = null): StopRowParser
    {
        return new StopRowParser(new CellConverter(), new ExtractInspector($limits ?? new ExtractLimitsDto(1_000_000, 10_000_000, 1000, 100)), 'Europe/Copenhagen');
    }

    private static function defaultRange(): ImportRangeDto
    {
        return ImportRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-05'), 31);
    }

    private function writeScenario(string $name): Scenario
    {
        $scenario = (new ScenarioLoader())->load(self::SCENARIOS.'/'.$name.'.yaml');
        (new ExtractWriter())->write($this->path, self::defaultMapping(), $scenario->rows, $scenario->omittedFields);

        return $scenario;
    }

    /**
     * @param list<list<\DateTimeImmutable|float|int|string|null>> $rows
     */
    private function writeRows(array $rows): void
    {
        $writer = new Writer();
        $writer->openToFile($this->path);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();
    }

    private static function range(Scenario $scenario): ImportRangeDto
    {
        return ImportRangeDto::create($scenario->firstDate, $scenario->lastDate, 31);
    }

    /**
     * @param list<StopRowDto> $rows
     */
    private static function find(array $rows, string $personId, StopType $type): StopRowDto
    {
        foreach ($rows as $row) {
            if ($personId === $row->personId && $type === $row->stopType) {
                return $row;
            }
        }

        self::fail(\sprintf('The rows have no %s for %s.', $type->value, $personId));
    }

    /**
     * @param list<RejectedRowDto> $rejected
     *
     * @return list<array{int, RejectionReason, ?FieldName}>
     */
    private static function reasons(array $rejected): array
    {
        return array_map(static fn (RejectedRowDto $row): array => [$row->rowNumber, $row->reason, $row->field], $rejected);
    }

    /**
     * @param list<StopRowDto> $rows
     *
     * @return list<array{int, FieldName, RejectionReason}>
     */
    private static function warnings(array $rows): array
    {
        $warnings = [];
        foreach ($rows as $row) {
            foreach ($row->warnings as $warning) {
                $warnings[] = [$row->rowNumber, $warning->field, $warning->reason];
            }
        }

        return $warnings;
    }

    /**
     * @return array<string, mixed>
     */
    private static function scenarioValues(): array
    {
        return (new ScenarioLoader())->load(self::SCENARIOS.'/base.yaml')->rows[0]->values;
    }

    private static function defaultMapping(): MappingDto
    {
        return (new MappingLoader())->load(self::DEFAULT_MAPPING);
    }

    /**
     * A mapping for rows of text and numbers. The dates and times are Excel serial numbers.
     *
     * @param list<string>    $rowId
     * @param list<string>    $keepColumns
     * @param list<FieldName> $required    the optional fields to make required
     */
    private static function simpleMapping(int $sheet = 0, int $headerRow = 1, array $rowId = [], array $keepColumns = [], array $required = []): MappingDto
    {
        $isRequired = static fn (FieldName $name): bool => \in_array($name, $required, true);

        return new MappingDto(
            name: 'simple',
            sheet: $sheet,
            headerRow: $headerRow,
            rowId: $rowId,
            fields: [
                'route' => self::simpleField(FieldName::Route, FieldType::String, true),
                'person_id' => self::simpleField(FieldName::PersonId, FieldType::String, $isRequired(FieldName::PersonId)),
                'service_date' => self::simpleField(FieldName::ServiceDate, FieldType::Date, true),
                'run' => self::simpleField(FieldName::Run, FieldType::Int, true),
                'stop_type' => self::simpleField(FieldName::StopType, FieldType::String, true, ['P' => 'pickup', 'D' => 'drop_off']),
                'stop_status' => self::simpleField(FieldName::StopStatus, FieldType::String, true, ['C' => 'completed', 'X' => 'cancelled']),
                'passengers_after' => self::simpleField(FieldName::PassengersAfter, FieldType::Int, $isRequired(FieldName::PassengersAfter)),
                'next_distance_km' => self::simpleField(FieldName::NextDistanceKm, FieldType::Float, $isRequired(FieldName::NextDistanceKm)),
                'next_duration_min' => self::simpleField(FieldName::NextDurationMin, FieldType::Float, $isRequired(FieldName::NextDurationMin)),
                'planned_time' => self::simpleField(FieldName::PlannedTime, FieldType::DateTime, true),
                'actual_time' => self::simpleField(FieldName::ActualTime, FieldType::DateTime, $isRequired(FieldName::ActualTime)),
                'planned_solo' => self::simpleField(FieldName::PlannedSolo, FieldType::Bool, $isRequired(FieldName::PlannedSolo), [], ['Y'], ['N']),
            ],
            keepColumns: $keepColumns,
        );
    }

    /**
     * @param array<string, string> $map
     * @param list<string>          $true
     * @param list<string>          $false
     */
    private static function simpleField(FieldName $name, FieldType $type, bool $required, array $map = [], array $true = [], array $false = []): FieldMappingDto
    {
        return new FieldMappingDto(
            column: self::HEADER[array_search($name, FieldName::cases(), true)],
            type: $type,
            required: $required,
            format: null,
            valueMap: $map,
            trueValues: $true,
            falseValues: $false,
        );
    }
}
