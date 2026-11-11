<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The length of the period of a page. The prototype has a day and a year. The application has a
 * quarter and a half year instead, because the data starts on 18 May 2026, and a longer period
 * shows a trend sooner.
 */
enum PeriodType: string
{
    case Week = 'week';
    case Month = 'month';
    case Quarter = 'quarter';
    case HalfYear = 'half_year';

    /**
     * Gives the number of months of the period, or null for a week.
     */
    public function months(): ?int
    {
        return match ($this) {
            self::Week => null,
            self::Month => 1,
            self::Quarter => 3,
            self::HalfYear => 6,
        };
    }
}
