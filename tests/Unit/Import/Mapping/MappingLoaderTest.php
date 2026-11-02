<?php

namespace App\Tests\Unit\Import\Mapping;

use App\Import\Mapping\FieldName;
use App\Import\Mapping\FieldType;
use App\Import\Mapping\InvalidMappingException;
use App\Import\Mapping\Mapping;
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
        $mapping = (new MappingLoader())->load(self::FIXTURES.'/valid.yaml');

        self::assertSame('valid', $mapping->name);
        self::assertSame(1, $mapping->sheet);
        self::assertSame(2, $mapping->headerRow);
        self::assertSame(['Id', 'Run'], $mapping->rowId);
        self::assertTrue($mapping->hasRowId());

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
        $mapping = (new MappingLoader())->load(self::FIXTURES.'/valid.yaml');

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
        $mapping = (new MappingLoader())->load(self::FIXTURES.'/valid_without_row_id.yaml');

        self::assertSame('valid_without_row_id', $mapping->name);
        self::assertSame(0, $mapping->sheet);
        self::assertSame(1, $mapping->headerRow);
        self::assertSame([], $mapping->rowId);
        self::assertFalse($mapping->hasRowId());
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
            'map' => ['P-1' => 'pickup', 'D-1' => 'drop_off'],
        ]);

        $mapping = $this->loadArray($raw);

        self::assertSame(['P-1' => 'pickup', 'D-1' => 'drop_off'], $mapping->field(FieldName::StopType)->valueMap);
    }

    public function testServiceDateCanHaveAnotherTypeWithoutTimeField(): void
    {
        $raw = self::withField(self::validMapping(), 'service_date', ['column' => 'Day', 'type' => 'string']);
        $raw = self::withField($raw, 'planned_time', ['column' => 'Planned', 'type' => 'datetime']);

        $mapping = $this->loadArray($raw);

        self::assertSame(FieldType::String, $mapping->field(FieldName::ServiceDate)->type);
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
        yield 'time field when service date is not a date' => [
            static fn (array $raw): array => self::withField($raw, 'service_date', ['column' => 'Day', 'type' => 'string']),
            'A "time" field needs a "service_date" field with the type "date".',
        ];
    }

    public function testFileThatDoesNotExistIsRejected(): void
    {
        $this->expectException(InvalidMappingException::class);
        $this->expectExceptionMessageMatches('/missing\.yaml.*does not exist/s');

        (new MappingLoader())->load(self::FIXTURES.'/missing.yaml');
    }

    public function testFileThatIsNotValidYamlIsRejected(): void
    {
        $this->expectException(InvalidMappingException::class);
        $this->expectExceptionMessageMatches('/The import mapping ".*invalid_syntax\.yaml" is not valid: /');

        (new MappingLoader())->load(self::FIXTURES.'/invalid_syntax.yaml');
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
    private function loadArray(array $raw): Mapping
    {
        $this->temporaryFile = sys_get_temp_dir().'/'.uniqid('test_mapping_', true).'.yaml';
        file_put_contents($this->temporaryFile, Yaml::dump($raw, 4));

        return (new MappingLoader())->load($this->temporaryFile);
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
