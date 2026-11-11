<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The length of the period of a page, as in the filter bar of the prototype.
 */
enum PeriodType: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';
}
