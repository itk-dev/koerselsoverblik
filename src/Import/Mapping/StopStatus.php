<?php

namespace App\Import\Mapping;

/**
 * The application values of the stop_status field.
 */
enum StopStatus: string
{
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Pending = 'pending';
}
