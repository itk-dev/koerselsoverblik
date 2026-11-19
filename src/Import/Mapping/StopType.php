<?php

declare(strict_types=1);

namespace App\Import\Mapping;

/**
 * The application values of the stop_type field.
 */
enum StopType: string
{
    case Pickup = 'pickup';
    case DropOff = 'drop_off';
}
