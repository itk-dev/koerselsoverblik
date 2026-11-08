<?php

namespace App\Import\Extract;

use App\Import\Mapping\FieldName;
use App\Import\Mapping\MappingDto;

/**
 * The columns of an extract that do not agree with the copy of a mapping. They are not an error.
 * The validate command shows them, so that a person can add a new column to the mapping.
 */
final readonly class ExtractColumnsDto
{
    /**
     * @param list<string> $missingKeptColumns     the kept columns of the mapping that the extract does not have, in the order of the mapping
     * @param list<string> $unreadColumns          the columns of the extract that are not a field, a row id, a kept or an excluded column, in the order of the header
     * @param list<string> $missingExcludedColumns the excluded columns of the mapping that the extract does not have, in the order of the mapping
     */
    private function __construct(
        public array $missingKeptColumns,
        public array $unreadColumns,
        public array $missingExcludedColumns,
    ) {
    }

    /**
     * An excluded column is a decision, so it is not a column that is not read. An excluded column
     * that the extract does not have can have a new name in the extract.
     *
     * @param list<string> $header the column names of the extract
     */
    public static function compare(array $header, MappingDto $mapping): self
    {
        $knownColumns = [
            ...array_map(static fn (FieldName $name): string => $mapping->field($name)->column, FieldName::cases()),
            ...$mapping->rowId,
            ...$mapping->keepColumns,
            ...$mapping->excludeColumns,
        ];

        return new self(
            array_values(array_diff($mapping->keepColumns, $header)),
            array_values(array_unique(array_diff($header, $knownColumns))),
            array_values(array_diff($mapping->excludeColumns, $header)),
        );
    }
}
