<?php

namespace App\Import\Extract;

/**
 * A cell value does not agree with its field, so the parser rejects the row.
 *
 * The exception has the reason. It never has the value.
 */
final class RejectedValueException extends \RuntimeException
{
    public function __construct(public readonly RejectionReason $reason)
    {
    }
}
