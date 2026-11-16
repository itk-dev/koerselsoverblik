<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The period that the selected period is compared with.
 */
enum Comparison: string
{
    case Previous = 'previous';
    case LastYear = 'last_year';
}
