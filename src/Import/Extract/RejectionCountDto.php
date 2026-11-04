<?php

namespace App\Import\Extract;

use App\Import\Mapping\FieldName;

/**
 * The rejected rows of an extract with the same field and reason.
 */
final readonly class RejectionCountDto
{
    /**
     * @param ?FieldName $field      the field, or null for the row id
     * @param list<int>  $rowNumbers the first row numbers, so that a person can find the rows
     */
    public function __construct(
        public ?FieldName $field,
        public RejectionReason $reason,
        public int $count,
        public array $rowNumbers,
    ) {
    }
}
