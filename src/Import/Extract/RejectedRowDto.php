<?php

namespace App\Import\Extract;

use App\Import\Mapping\FieldName;

/**
 * A row of an extract that the parser rejects. It never has a value from the extract.
 */
final readonly class RejectedRowDto
{
    /**
     * @param int        $rowNumber the 1-based row number in the sheet
     * @param ?FieldName $field     the field with the problem, or null if the problem is the row id
     */
    public function __construct(
        public int $rowNumber,
        public RejectionReason $reason,
        public ?FieldName $field,
    ) {
    }
}
