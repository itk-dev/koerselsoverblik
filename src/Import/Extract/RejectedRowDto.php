<?php

namespace App\Import\Extract;

use App\Import\Mapping\FieldName;

/**
 * A row of an extract that the parser rejects. The reason never has a value from the extract.
 *
 * The copy of the row has the values, so that a later read through a corrected mapping can import
 * the row. A row outside the range of the upload has no copy, because the upload for its date
 * imports it.
 *
 * @phpstan-import-type SourceValue from SourceCells
 */
final readonly class RejectedRowDto
{
    /**
     * @param int                        $rowNumber    the 1-based row number in the sheet
     * @param ?FieldName                 $field        the field with the problem, or null if the problem is the row id
     * @param ?\DateTimeImmutable        $serviceDate  the service date, if the date field of the row is valid
     * @param array<string, SourceValue> $sourceValues the copy of the row, keyed by the column name
     */
    public function __construct(
        public int $rowNumber,
        public RejectionReason $reason,
        public ?FieldName $field,
        public ?\DateTimeImmutable $serviceDate = null,
        public array $sourceValues = [],
    ) {
    }
}
