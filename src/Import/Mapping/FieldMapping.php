<?php

namespace App\Import\Mapping;

/**
 * The source column of one application field, and how to read it.
 */
final readonly class FieldMapping
{
    /**
     * @param array<string, string> $valueMap    source code to application value
     * @param list<string>          $trueValues
     * @param list<string>          $falseValues
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
