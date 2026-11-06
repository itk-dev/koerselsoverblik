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
     * @param list<string> $missingKeptColumns the kept columns of the mapping that the extract does not have, in the order of the mapping
     * @param list<string> $unreadColumns      the columns of the extract that are not a field, a row id or a kept column, in the order of the header
     */
    private function __construct(
        public array $missingKeptColumns,
        public array $unreadColumns,
    ) {
    }

    /**
     * @param list<string> $header the column names of the extract
     */
    public static function compare(array $header, MappingDto $mapping): self
    {
        $readColumns = [
            ...array_map(static fn (FieldName $name): string => $mapping->field($name)->column, FieldName::cases()),
            ...$mapping->rowId,
            ...$mapping->keepColumns,
        ];

        return new self(
            array_values(array_diff($mapping->keepColumns, $header)),
            array_values(array_unique(array_diff($header, $readColumns))),
        );
    }
}
