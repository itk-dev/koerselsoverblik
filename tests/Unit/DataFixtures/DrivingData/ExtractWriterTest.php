<?php

namespace App\Tests\Unit\DataFixtures\DrivingData;

use App\DataFixtures\DrivingData\ExtractRow;
use App\DataFixtures\DrivingData\ExtractWriter;
use App\Import\Mapping\FieldName;
use App\Import\Mapping\MappingDto;
use App\Import\Mapping\MappingLoader;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks that the writer follows the mapping, and that each value reads back with its type.
 *
 * The mapping tests/fixtures/mappings/valid.yaml has the data on sheet 1, the header in row 2,
 * and the row id columns "Id" and "Run". "Run" is also the column of the run field.
 */
final class ExtractWriterTest extends TestCase
{
    private const string MAPPINGS = __DIR__.'/../../../fixtures/mappings';

    private const array HEADER = ['Id', 'Route', 'Person', 'Day', 'Run', 'Kind', 'State', 'Load', 'Km', 'Minutes', 'Planned', 'Actual', 'Solo'];

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

    public function testRowsAboveTheHeaderAndTheHeaderFollowTheMapping(): void
    {
        (new ExtractWriter())->write($this->path, self::mapping('valid'), []);

        self::assertSame([], $this->readSheet(0));
        $rows = $this->readSheet(1);
        self::assertCount(2, $rows);
        // The reader fills each row with empty cells to the width of the sheet.
        self::assertSame(ExtractWriter::TITLE, $rows[0][0]);
        self::assertSame(self::HEADER, $rows[1]);
    }

    public function testHeaderIsTheFirstRowWhenTheMappingSaysSo(): void
    {
        (new ExtractWriter())->write($this->path, self::mapping('valid_without_row_id'), []);

        $rows = $this->readSheet(0);

        self::assertCount(1, $rows);
        self::assertSame(array_slice(self::HEADER, 1), $rows[0]);
    }

    public function testValuesReadBackWithTheirTypes(): void
    {
        (new ExtractWriter())->write($this->path, self::mapping('valid'), [new ExtractRow(self::values())]);

        $row = $this->readSheet(1)[2];

        self::assertSame(['1', 'R-01', 'P-001', '2026-01-05', 3, 'P', 'X', 2, 3.5, 7.5], array_slice($row, 0, 10));
        self::assertInstanceOf(\DateInterval::class, $row[10]);
        self::assertSame('25:10', $row[10]->format('%h:%I'));
        self::assertInstanceOf(\DateTimeImmutable::class, $row[11]);
        self::assertSame('2026-01-05 07:12', $row[11]->format('Y-m-d H:i'));
        self::assertSame('Y', $row[12]);
    }

    public function testFalseIsTheFirstFalseValue(): void
    {
        $values = self::values();
        $values['planned_solo'] = false;

        (new ExtractWriter())->write($this->path, self::mapping('valid'), [new ExtractRow($values)]);

        self::assertSame('N', $this->readSheet(1)[2][12]);
    }

    public function testDateWithoutFormatIsADateCell(): void
    {
        $values = self::values();
        $values['stop_status'] = 'completed';

        (new ExtractWriter())->write($this->path, self::mapping('valid_without_row_id'), [new ExtractRow($values)]);

        $day = $this->readSheet(0)[1][2];

        self::assertInstanceOf(\DateTimeImmutable::class, $day);
        self::assertSame('2026-01-05', $day->format('Y-m-d'));
    }

    public function testEmptyValueIsAnEmptyCell(): void
    {
        $values = self::values();
        $values['next_distance_km'] = null;
        unset($values['actual_time']);

        (new ExtractWriter())->write($this->path, self::mapping('valid'), [new ExtractRow($values)]);

        $row = $this->readSheet(1)[2];
        self::assertSame('', $row[8]);
        self::assertSame('', $row[11]);
    }

    public function testRowIdsAreUniqueUnlessTheRowSetsOne(): void
    {
        $rows = [new ExtractRow(self::values()), new ExtractRow(self::values(), rowId: 'X-9'), new ExtractRow(self::values())];

        (new ExtractWriter())->write($this->path, self::mapping('valid'), $rows);

        self::assertSame(['1', 'X-9', '3'], array_column(array_slice($this->readSheet(1), 2), 0));
    }

    /**
     * OpenSpout uses the array keys of the cells as the column positions.
     */
    public function testRowIdColumnAfterAFieldColumnIsTheFirstColumn(): void
    {
        $yaml = str_replace('row_id: [Id, Run]', 'row_id: [Run, Id]', (string) file_get_contents(self::MAPPINGS.'/valid.yaml'));
        $mappingPath = sys_get_temp_dir().'/'.uniqid('mapping_', true).'.yaml';
        file_put_contents($mappingPath, $yaml);
        try {
            $mapping = (new MappingLoader())->load($mappingPath);
        } finally {
            unlink($mappingPath);
        }

        (new ExtractWriter())->write($this->path, $mapping, [new ExtractRow(self::values())]);

        $rows = $this->readSheet(1);
        self::assertSame(self::HEADER, $rows[1]);
        self::assertSame(['1', 'R-01', 'P-001'], array_slice($rows[2], 0, 3));
    }

    public function testRawValueReplacesTheValue(): void
    {
        (new ExtractWriter())->write($this->path, self::mapping('valid'), [new ExtractRow(self::values(), raw: ['run' => 'abc'])]);

        self::assertSame('abc', $this->readSheet(1)[2][4]);
    }

    public function testOmittedFieldHasNoColumn(): void
    {
        (new ExtractWriter())->write($this->path, self::mapping('valid'), [new ExtractRow(self::values())], [FieldName::PlannedSolo]);

        $rows = $this->readSheet(1);
        self::assertSame(array_slice(self::HEADER, 0, 12), $rows[1]);
        self::assertCount(12, $rows[2]);
    }

    public function testValueThatIsNotInTheMapIsRejected(): void
    {
        $values = self::values();
        $values['stop_type'] = 'boarding';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The value of "stop_type" is not in the mapping.');

        (new ExtractWriter())->write($this->path, self::mapping('valid'), [new ExtractRow($values)]);
    }

    /**
     * A misspelt name would otherwise give an empty cell with no error.
     */
    public function testValueForANameThatIsNotAFieldIsRejected(): void
    {
        $values = self::values();
        $values['persond_id'] = 'P-001';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The row has a value for "persond_id", which is not a field.');

        (new ExtractWriter())->write($this->path, self::mapping('valid'), [new ExtractRow($values)]);
    }

    public function testRawValueForANameThatIsNotAFieldIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The row has a value for "rnu", which is not a field.');

        (new ExtractWriter())->write($this->path, self::mapping('valid'), [new ExtractRow(self::values(), raw: ['rnu' => 'abc'])]);
    }

    #[DataProvider('valuesWithTheWrongType')]
    public function testValueWithTheWrongTypeIsRejected(string $field, mixed $value): void
    {
        $values = self::values();
        $values[$field] = $value;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The value of "%s" does not have the type of the field.', $field));

        (new ExtractWriter())->write($this->path, self::mapping('valid'), [new ExtractRow($values)]);
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function valuesWithTheWrongType(): iterable
    {
        yield 'string' => ['route', 5];
        yield 'mapped string' => ['stop_type', 5];
        yield 'int' => ['run', '3'];
        yield 'float' => ['next_distance_km', '3.5'];
        yield 'bool' => ['planned_solo', 'yes'];
        yield 'date' => ['service_date', '2026-01-05'];
        yield 'time' => ['planned_time', '07:10'];
        yield 'datetime' => ['actual_time', '2026-01-05 07:12'];
    }

    /**
     * @return array<string, mixed>
     */
    private static function values(): array
    {
        return [
            'route' => 'R-01',
            'person_id' => 'P-001',
            'service_date' => new \DateTimeImmutable('2026-01-05'),
            'run' => 3,
            'stop_type' => 'pickup',
            'stop_status' => 'cancelled',
            'passengers_after' => 2,
            'next_distance_km' => 3.5,
            'next_duration_min' => 7.5,
            'planned_time' => new \DateInterval('PT25H10M'),
            'actual_time' => new \DateTimeImmutable('2026-01-05 07:12:00'),
            'planned_solo' => true,
        ];
    }

    private static function mapping(string $name): MappingDto
    {
        return (new MappingLoader())->load(self::MAPPINGS.'/'.$name.'.yaml');
    }

    /**
     * @return list<array<int, mixed>>
     */
    private function readSheet(int $index): array
    {
        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));
        $reader->open($this->path);

        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getIndex() === $index) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }
            }
        }
        $reader->close();

        return $rows;
    }
}
