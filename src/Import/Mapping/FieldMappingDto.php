<?php

namespace App\Import\Mapping;

/**
 * The source column of one application field, and how to read it.
 */
final readonly class FieldMappingDto
{
    /**
     * The format of a date or datetime field that is a date cell or an Excel serial number.
     */
    public const string EXCEL_FORMAT = 'excel';

    /**
     * @param array<array-key, string> $valueMap    source code to application value. A code that is a whole number has an int key
     * @param list<string>             $trueValues
     * @param list<string>             $falseValues
     */
    public function __construct(
        public string $column,
        public FieldType $type,
        public bool $required,
        public ?string $format,
        public array $valueMap,
        public array $trueValues,
        public array $falseValues,
    ) {
    }
}
