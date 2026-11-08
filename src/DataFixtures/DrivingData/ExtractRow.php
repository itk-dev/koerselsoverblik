<?php

namespace App\DataFixtures\DrivingData;

/**
 * One stop row for ExtractWriter, with application values.
 */
final readonly class ExtractRow
{
    /**
     * @param array<string, mixed>                 $values application values, keyed by the FieldName value
     * @param ?string                              $rowId  the value of the row id columns, or null for a generated id
     * @param array<string, float|int|string|null> $raw    cell values that replace the values, keyed by the FieldName value
     * @param array<string, float|int|string|null> $kept   cell values of the kept columns of the mapping, keyed by the column name
     */
    public function __construct(
        public array $values,
        public ?string $rowId = null,
        public array $raw = [],
        public array $kept = [],
    ) {
    }
}
