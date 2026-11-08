<?php

namespace App\Import\Mapping;

/**
 * How the columns of an extract map to the application fields.
 *
 * The parser reads an extract only through this object.
 */
final readonly class MappingDto
{
    /**
     * @param int                            $sheet          0-based index of the sheet
     * @param int                            $headerRow      1-based row number of the column headers
     * @param list<string>                   $rowId          the columns that identify a row, or none
     * @param array<string, FieldMappingDto> $fields         keyed by the FieldName value
     * @param list<string>                   $keepColumns    the other columns that the copy of a row keeps
     * @param list<string>                   $excludeColumns the columns that the mapping leaves out on purpose
     */
    public function __construct(
        public string $name,
        public int $sheet,
        public int $headerRow,
        public array $rowId,
        private array $fields,
        public array $keepColumns = [],
        public array $excludeColumns = [],
    ) {
    }

    public function field(FieldName $name): FieldMappingDto
    {
        return $this->fields[$name->value];
    }

    public function hasRowId(): bool
    {
        return [] !== $this->rowId;
    }
}
