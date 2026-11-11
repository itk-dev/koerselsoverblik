<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import\Mapping;

use App\Import\Mapping\FieldName;
use App\Import\Mapping\FieldType;
use App\Import\Mapping\InvalidMappingException;
use App\Import\Mapping\MappingDto;
use App\Import\Mapping\MappingLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Checks that the loader reads valid mappings and rejects invalid mappings.
 *
 * Most cases change one value of tests/fixtures/mappings/valid.yaml and load the result from a
 * temporary file.
 */
final class MappingLoaderTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../../../fixtures/mappings';

    private string $temporaryFile = '';

    protected function tearDown(): void
    {
        if ('' !== $this->temporaryFile && is_file($this->temporaryFile)) {
            unlink($this->temporaryFile);
        }
    }

    public function testValidMappingLoads(): void
    {
        $mapping = new MappingLoader()->load(self::FIXTURES.'/valid.yaml');

        self::assertSame('valid', $mapping->name);
        self::assertSame(1, $mapping->sheet);
        self::assertSame(2, $mapping->headerRow);
        self::assertSame(['Id', 'Run'], $mapping->rowId);
        self::assertTrue($mapping->hasRowId());
        self::assertSame(['Note', 'Trip'], $mapping->keepColumns);
        self::assertSame(['Name'], $mapping->excludeColumns);

        $route = $mapping->field(FieldName::Route);
        self::assertSame('Route', $route->column);
        self::assertSame(FieldType::String, $route->type);
        self::assertTrue($route->required);
        self::assertNull($route->format);
        self::assertSame([], $route->valueMap);
        self::assertSame([], $route->trueValues);
        self::assertSame([], $route->falseValues);

        $serviceDate = $mapping->field(FieldName::ServiceDate);
        self::assertSame(FieldType::Date, $serviceDate->type);
        self::assertSame('Y-m-d', $serviceDate->format);

        self::assertSame(['P' => 'pickup', 'D' => 'drop_off'], $mapping->field(FieldName::StopType)->valueMap);
        self::assertFalse($mapping->field(FieldName::PassengersAfter)->required);
        self::assertSame(FieldType::Time, $mapping->field(FieldName::PlannedTime)->type);

        $plannedSolo = $mapping->field(FieldName::PlannedSolo);
        self::assertSame(FieldType::Bool, $plannedSolo->type);
        self::assertSame(['Y'], $plannedSolo->trueValues);
        self::assertSame(['N', '-'], $plannedSolo->falseValues);
    }

    public function testEachFieldHasItsOwnColumn(): void
    {
        $mapping = new MappingLoader()->load(self::FIXTURES.'/valid.yaml');

        $columns = array_map(
            static fn (FieldName $name): string => $mapping->field($name)->column,
            FieldName::cases(),
        );

        self::assertSame(
            ['Route', 'Person', 'Day', 'Run', 'Kind', 'State', 'Load', 'Km', 'Minutes', 'Planned', 'Actual', 'Solo'],
            $columns,
        );
    }

    public function testMappingWithoutRowIdLoads(): void
    {
        $mapping = new MappingLoader()->load(self::FIXTURES.'/valid_without_row_id.yaml');

        self::assertSame('valid_without_row_id', $mapping->name);
        self::assertSame(0, $mapping->sheet);
        self::assertSame(1, $mapping->headerRow);
        self::assertSame([], $mapping->rowId);
        self::assertFalse($mapping->hasRowId());
        self::assertSame([], $mapping->keepColumns);
        self::assertSame([], $mapping->excludeColumns);
    }

    public function testFirstSheetAndFirstRowAreAccepted(): void
    {
        $raw = self::validMapping();
        $raw['sheet'] = 0;
        $raw['header_row'] = 1;

        $mapping = $this->loadArray($raw);

        self::assertSame(0, $mapping->sheet);
        self::assertSame(1, $mapping->headerRow);
    }

    public function testCodeWithDashIsKept(): void
    {
        $raw = self::withField(self::validMapping(), 'stop_type', [
            'column' => 'Kind',
            'type' => 'string',
            'required' => true,
            'map' => ['P-1' => 'pickup', 'D-1' => 'drop_off'],
        ]);

        $mapping = $this->loadArray($raw);

        self::assertSame(['P-1' => 'pickup', 'D-1' => 'drop_off'], $mapping->field(FieldName::StopType)->valueMap);
    }

    public function testFieldWithoutFixedValuesAcceptsAnyMapValue(): void
    {
        $raw = self::withField(self::validMapping(), 'route', [
            'column' => 'Route',
            'type' => 'string',
            'required' => true,
            'map' => ['R1' => 'route-one'],
        ]);

        $mapping = $this->loadArray($raw);

        self::assertSame(['R1' => 'route-one'], $mapping->field(FieldName::Route)->valueMap);
    }

    public function testServiceDateCanBeADateTimeWithoutTimeField(): void
    {
        $raw = self::withField(self::validMapping(), 'service_date', ['column' => 'Day', 'type' => 'datetime', 'required' => true]);
        $raw = self::withField($raw, 'planned_time', ['column' => 'Planned', 'type' => 'datetime', 'required' => true]);

        $mapping = $this->loadArray($raw);

        self::assertSame(FieldType::DateTime, $mapping->field(FieldName::ServiceDate)->type);
        self::assertSame(FieldType::DateTime, $mapping->field(FieldName::PlannedTime)->type);
    }

    public function testFieldThatCanBeEmptyCanBeRequired(): void
    {
        $raw = self::withField(self::validMapping(), 'passengers_after', ['column' => 'Load', 'type' => 'int', 'required' => true]);

        $mapping = $this->loadArray($raw);

        self::assertTrue($mapping->field(FieldName::PassengersAfter)->required);
    }

    /**
     * @param \Closure(array<array-key, mixed>): array<array-key, mixed> $change
     */
    #[DataProvider('invalidMappings')]
    public function testInvalidMappingIsRejected(\Closure $change, string $reason): void
    {
        $this->expectException(InvalidMappingException::class);
        $this->expectExceptionMessageMatches('/test_mapping_.*\.yaml.*'.preg_quote($reason, '/').'/s');

        $this->loadArray($change(self::validMapping()));
    }

    /**
     * @return iterable<string, array{\Closure(array<array-key, mixed>): array<array-key, mixed>, string}>
     */
    public static function invalidMappings(): iterable
    {
        yield 'missing header row' => [
            static fn (array $raw): array => self::without($raw, 'header_row'),
            'The child config "header_row" under "mapping" must be configured.',
        ];
        yield 'header row 0' => [
            static fn (array $raw): array => self::with($raw, ['header_row' => 0]),
            'path "mapping.header_row"',
        ];
        yield 'negative sheet' => [
            static fn (array $raw): array => self::with($raw, ['sheet' => -1]),
            'path "mapping.sheet"',
        ];
        yield 'empty row id' => [
            static fn (array $raw): array => self::with($raw, ['row_id' => []]),
            'The path "mapping.row_id" should have at least 1 element(s) defined.',
        ];
        yield 'empty kept column' => [
            static fn (array $raw): array => self::with($raw, ['keep_columns' => ['Note', '']]),
            'The path "mapping.keep_columns.1" cannot contain an empty value',
        ];
        yield 'kept column two times' => [
            static fn (array $raw): array => self::with($raw, ['keep_columns' => ['Note', 'Trip', 'Note']]),
            'The column "Note" is in "keep_columns" more than one time.',
        ];
        yield 'kept column that is a field column' => [
            static fn (array $raw): array => self::with($raw, ['keep_columns' => ['Note', 'Solo']]),
            'The column "Solo" in "keep_columns" is a field column or a row id column.',
        ];
        yield 'kept column that is a row id column' => [
            static fn (array $raw): array => self::with($raw, ['keep_columns' => ['Id']]),
            'The column "Id" in "keep_columns" is a field column or a row id column.',
        ];
        yield 'empty excluded column' => [
            static fn (array $raw): array => self::with($raw, ['exclude_columns' => ['Name', '']]),
            'The path "mapping.exclude_columns.1" cannot contain an empty value',
        ];
        yield 'excluded column two times' => [
            static fn (array $raw): array => self::with($raw, ['exclude_columns' => ['Name', 'Name']]),
            'The column "Name" is in "exclude_columns" more than one time.',
        ];
        yield 'excluded column that is a field column' => [
            static fn (array $raw): array => self::with($raw, ['exclude_columns' => ['Name', 'Solo']]),
            'The column "Solo" in "exclude_columns" is a field column, a row id column or a kept column.',
        ];
        yield 'excluded column that is a row id column' => [
            static fn (array $raw): array => self::with($raw, ['exclude_columns' => ['Id']]),
            'The column "Id" in "exclude_columns" is a field column, a row id column or a kept column.',
        ];
        yield 'excluded column that is a kept column' => [
            static fn (array $raw): array => self::with($raw, ['exclude_columns' => ['Note']]),
            'The column "Note" in "exclude_columns" is a field column, a row id column or a kept column.',
        ];
        yield 'excluded column with a space at the start' => [
            static fn (array $raw): array => self::with($raw, ['exclude_columns' => [' Name']]),
            'The column " Name" has a space at the start or the end.',
        ];
        // The parser removes the spaces at the ends of the header names, so such a name never matches.
        yield 'field column with a space at the start' => [
            static fn (array $raw): array => self::withField($raw, 'route', ['column' => ' Route', 'type' => 'string', 'required' => true]),
            'The column " Route" has a space at the start or the end.',
        ];
        yield 'second field column with a space at the end' => [
            static fn (array $raw): array => self::withField($raw, 'passengers_after', ['column' => 'Load ', 'type' => 'int', 'required' => true]),
            'The column "Load " has a space at the start or the end.',
        ];
        yield 'row id column with a space at the end' => [
            static fn (array $raw): array => self::with($raw, ['row_id' => ['Id ', 'Run']]),
            'The column "Id " has a space at the start or the end.',
        ];
        yield 'kept column with a tab at the end' => [
            static fn (array $raw): array => self::with($raw, ['keep_columns' => ["Note\t"]]),
            "The column \"Note\t\" has a space at the start or the end.",
        ];
        yield 'missing fields' => [
            static fn (array $raw): array => self::without($raw, 'fields'),
            'The child config "fields" under "mapping" must be configured.',
        ];
        yield 'missing application field' => [
            static fn (array $raw): array => self::withoutField($raw, 'route'),
            'The child config "route" under "mapping.fields" must be configured.',
        ];
        yield 'unknown field name' => [
            static fn (array $raw): array => self::withField($raw, 'colour', ['column' => 'Colour', 'type' => 'string']),
            'Unrecognized option "colour" under "mapping.fields"',
        ];
        yield 'field without column' => [
            static fn (array $raw): array => self::withField($raw, 'route', ['type' => 'string']),
            'The child config "column" under "mapping.fields.route" must be configured.',
        ];
        yield 'field with empty column' => [
            static fn (array $raw): array => self::withField($raw, 'route', ['column' => '', 'type' => 'string']),
            'The path "mapping.fields.route.column" cannot contain an empty value',
        ];
        yield 'field without type' => [
            static fn (array $raw): array => self::withField($raw, 'route', ['column' => 'Route']),
            'The child config "type" under "mapping.fields.route" must be configured.',
        ];
        yield 'unknown type' => [
            static fn (array $raw): array => self::withField($raw, 'route', ['column' => 'Route', 'type' => 'text']),
            'path "mapping.fields.route.type"',
        ];
        yield 'map on an int field' => [
            static fn (array $raw): array => self::withField($raw, 'run', ['column' => 'Run', 'type' => 'int', 'map' => ['1' => 'one']]),
            'Only a "string" field can have a "map".',
        ];
        yield 'format on a string field' => [
            static fn (array $raw): array => self::withField($raw, 'route', ['column' => 'Route', 'type' => 'string', 'format' => 'Y-m-d']),
            'Only a "date" or "datetime" field can have a "format".',
        ];
        yield 'true values on a string field' => [
            static fn (array $raw): array => self::withField($raw, 'route', ['column' => 'Route', 'type' => 'string', 'true_values' => ['Y']]),
            'Only a "bool" field can have "true_values" and "false_values".',
        ];
        yield 'false values on a string field' => [
            static fn (array $raw): array => self::withField($raw, 'route', ['column' => 'Route', 'type' => 'string', 'false_values' => ['N']]),
            'Only a "bool" field can have "true_values" and "false_values".',
        ];
        yield 'bool field without true values' => [
            static fn (array $raw): array => self::withField($raw, 'planned_solo', ['column' => 'Solo', 'type' => 'bool', 'false_values' => ['N']]),
            'A "bool" field must have "true_values" and "false_values".',
        ];
        yield 'bool field without false values' => [
            static fn (array $raw): array => self::withField($raw, 'planned_solo', ['column' => 'Solo', 'type' => 'bool', 'true_values' => ['Y']]),
            'A "bool" field must have "true_values" and "false_values".',
        ];
        yield 'value in both lists' => [
            static fn (array $raw): array => self::withField($raw, 'planned_solo', ['column' => 'Solo', 'type' => 'bool', 'true_values' => ['Y'], 'false_values' => ['N', 'Y']]),
            'A value cannot be in "true_values" and in "false_values".',
        ];
        yield 'stop type without map' => [
            static fn (array $raw): array => self::withField($raw, 'stop_type', ['column' => 'Kind', 'type' => 'string']),
            'A "stop_type" field must have a "map".',
        ];
        yield 'stop status without map' => [
            static fn (array $raw): array => self::withField($raw, 'stop_status', ['column' => 'State', 'type' => 'string']),
            'A "stop_status" field must have a "map".',
        ];
        yield 'unknown stop type value' => [
            static fn (array $raw): array => self::withField($raw, 'stop_type', ['column' => 'Kind', 'type' => 'string', 'map' => ['P' => 'pickup', 'D' => 'dropoff']]),
            'The "map" of "stop_type" can only have the values "pickup", "drop_off".',
        ];
        yield 'unknown stop status value' => [
            static fn (array $raw): array => self::withField($raw, 'stop_status', ['column' => 'State', 'type' => 'string', 'map' => ['C' => 'completd']]),
            'The "map" of "stop_status" can only have the values "completed", "cancelled", "pending".',
        ];
        yield 'time field when service date is not a date' => [
            static fn (array $raw): array => self::withField($raw, 'service_date', ['column' => 'Day', 'type' => 'datetime', 'required' => true]),
            'A "time" field needs a "service_date" field with the type "date".',
        ];
        yield 'run that is text' => [
            static fn (array $raw): array => self::withField($raw, 'run', ['column' => 'Run', 'type' => 'string', 'required' => true]),
            'The type of "run" must be "int".',
        ];
        yield 'service date that is text' => [
            static fn (array $raw): array => self::withField($raw, 'service_date', ['column' => 'Day', 'type' => 'string', 'required' => true]),
            'The type of "service_date" must be "date" or "datetime".',
        ];
        yield 'planned time that is a date' => [
            static fn (array $raw): array => self::withField($raw, 'planned_time', ['column' => 'Planned', 'type' => 'date', 'required' => true]),
            'The type of "planned_time" must be "time" or "datetime".',
        ];
        yield 'distance that is a whole number' => [
            static fn (array $raw): array => self::withField($raw, 'next_distance_km', ['column' => 'Km', 'type' => 'int']),
            'The type of "next_distance_km" must be "float".',
        ];
        yield 'solo that is text' => [
            static fn (array $raw): array => self::withField($raw, 'planned_solo', ['column' => 'Solo', 'type' => 'string']),
            'The type of "planned_solo" must be "bool".',
        ];
        yield 'route that is not required' => [
            static fn (array $raw): array => self::withField($raw, 'route', ['column' => 'Route', 'type' => 'string']),
            'The "route" field must be required.',
        ];
        yield 'service date that is not required' => [
            static fn (array $raw): array => self::withField($raw, 'service_date', ['column' => 'Day', 'type' => 'date']),
            'The "service_date" field must be required.',
        ];
        yield 'run that is not required' => [
            static fn (array $raw): array => self::withField($raw, 'run', ['column' => 'Run', 'type' => 'int']),
            'The "run" field must be required.',
        ];
        yield 'stop type that is not required' => [
            static fn (array $raw): array => self::withField($raw, 'stop_type', ['column' => 'Kind', 'type' => 'string', 'map' => ['P' => 'pickup']]),
            'The "stop_type" field must be required.',
        ];
        yield 'stop status that is not required' => [
            static fn (array $raw): array => self::withField($raw, 'stop_status', ['column' => 'State', 'type' => 'string', 'map' => ['C' => 'completed']]),
            'The "stop_status" field must be required.',
        ];
        yield 'planned time that is not required' => [
            static fn (array $raw): array => self::withField($raw, 'planned_time', ['column' => 'Planned', 'type' => 'time']),
            'The "planned_time" field must be required.',
        ];
    }

    public function testFileThatDoesNotExistIsRejected(): void
    {
        $this->expectException(InvalidMappingException::class);
        $this->expectExceptionMessageMatches('/missing\.yaml.*does not exist/s');

        new MappingLoader()->load(self::FIXTURES.'/missing.yaml');
    }

    public function testFileThatIsNotValidYamlIsRejected(): void
    {
        $this->expectException(InvalidMappingException::class);
        $this->expectExceptionMessageMatches('/The import mapping ".*test_mapping_.*\.yaml" is not valid: /');

        new MappingLoader()->load($this->writeFile("header_row: [1\n"));
    }

    public function testFileThatIsNotAMapIsRejected(): void
    {
        $this->expectException(InvalidMappingException::class);
        $this->expectExceptionMessageMatches('/test_mapping_.*\.yaml.*"mapping"/s');

        $this->loadArray(['header_row', 'fields']);
    }

    public function testRejectionKeepsTheReason(): void
    {
        try {
            $this->loadArray(self::without(self::validMapping(), 'header_row'));
            self::fail('The mapping was not rejected.');
        } catch (InvalidMappingException $exception) {
            self::assertNotNull($exception->getPrevious());
        }
    }

    /**
     * @param array<array-key, mixed> $raw
     */
    private function loadArray(array $raw): MappingDto
    {
        return new MappingLoader()->load($this->writeFile(Yaml::dump($raw, 4)));
    }

    /**
     * Writes a temporary mapping file. Invalid YAML is not in a fixture file, because the YAML
     * coding standards check would reject it.
     */
    private function writeFile(string $content): string
    {
        $this->temporaryFile = sys_get_temp_dir().'/'.uniqid('test_mapping_', true).'.yaml';
        file_put_contents($this->temporaryFile, $content);

        return $this->temporaryFile;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function validMapping(): array
    {
        /** @var array<array-key, mixed> $raw */
        $raw = Yaml::parseFile(self::FIXTURES.'/valid.yaml');

        return $raw;
    }

    /**
     * @param array<array-key, mixed> $raw
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    private static function with(array $raw, array $values): array
    {
        return array_replace($raw, $values);
    }

    /**
     * @param array<array-key, mixed> $raw
     *
     * @return array<array-key, mixed>
     */
    private static function without(array $raw, string $key): array
    {
        unset($raw[$key]);

        return $raw;
    }

    /**
     * @param array<array-key, mixed> $raw
     * @param array<array-key, mixed> $field
     *
     * @return array<array-key, mixed>
     */
    private static function withField(array $raw, string $name, array $field): array
    {
        /** @var array<array-key, mixed> $fields */
        $fields = $raw['fields'];
        $fields[$name] = $field;
        $raw['fields'] = $fields;

        return $raw;
    }

    /**
     * @param array<array-key, mixed> $raw
     *
     * @return array<array-key, mixed>
     */
    private static function withoutField(array $raw, string $name): array
    {
        /** @var array<array-key, mixed> $fields */
        $fields = $raw['fields'];
        unset($fields[$name]);
        $raw['fields'] = $fields;

        return $raw;
    }
}
