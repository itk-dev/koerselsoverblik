<?php

namespace App\Tests\Unit\Import\Extract;

use App\Import\Extract\ExtractColumnsDto;
use App\Import\Mapping\FieldMappingDto;
use App\Import\Mapping\FieldName;
use App\Import\Mapping\FieldType;
use App\Import\Mapping\MappingDto;
use PHPUnit\Framework\TestCase;

/**
 * Checks that the columns of a file are compared with the columns of a mapping.
 */
final class ExtractColumnsDtoTest extends TestCase
{
    public function testFindsTheMissingKeptColumnsAndTheColumnsThatAreNotRead(): void
    {
        $header = ['Labels', 'Id', 'Col route', 'Note', 'Col run', 'Adresse', ...self::otherFieldColumns()];

        $columns = ExtractColumnsDto::compare($header, self::mapping(rowId: ['Id'], keepColumns: ['Trip', 'Note', 'Price']));

        self::assertSame(['Trip', 'Price'], $columns->missingKeptColumns);
        self::assertSame(['Labels', 'Adresse'], $columns->unreadColumns);
    }

    public function testFileWithTheColumnsOfTheMapping(): void
    {
        $columns = ExtractColumnsDto::compare(['Col route', 'Col run', ...self::otherFieldColumns(), 'Note'], self::mapping(keepColumns: ['Note']));

        self::assertSame([], $columns->missingKeptColumns);
        self::assertSame([], $columns->unreadColumns);
    }

    /**
     * A column name occurs one time in each list, also when the header has it two times.
     */
    public function testColumnThatOccursTwoTimesIsNamedOneTime(): void
    {
        $columns = ExtractColumnsDto::compare(['Labels', 'Col route', 'Labels', 'Col run', ...self::otherFieldColumns()], self::mapping());

        self::assertSame(['Labels'], $columns->unreadColumns);
    }

    /**
     * @return list<string>
     */
    private static function otherFieldColumns(): array
    {
        return array_map(
            static fn (FieldName $name): string => 'Col '.$name->value,
            array_values(array_filter(FieldName::cases(), static fn (FieldName $name): bool => !\in_array($name, [FieldName::Route, FieldName::Run], true))),
        );
    }

    /**
     * @param list<string> $rowId
     * @param list<string> $keepColumns
     */
    private static function mapping(array $rowId = [], array $keepColumns = []): MappingDto
    {
        $fields = [];
        foreach (FieldName::cases() as $name) {
            $fields[$name->value] = new FieldMappingDto('Col '.$name->value, FieldType::String, false, null, [], [], []);
        }

        return new MappingDto('test', 0, 1, $rowId, $fields, $keepColumns);
    }
}
