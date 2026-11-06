<?php

namespace App\Import\Extract;

use App\Import\Mapping\FieldName;

/**
 * A value of an optional field that the parser cannot read. The field is empty, and the row is
 * imported. The warning never has the value. The copy of the row keeps it.
 */
final readonly class FieldWarningDto
{
    public function __construct(
        public FieldName $field,
        public RejectionReason $reason,
    ) {
    }
}
