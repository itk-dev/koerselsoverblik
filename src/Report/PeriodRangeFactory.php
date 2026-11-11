<?php

declare(strict_types=1);

namespace App\Report;

/**
 * Gives the date ranges of a filter, as the prototype does:
 *
 * - The period ends today at the latest. A period that has not ended is partial.
 * - The comparison is the previous period or the same period last year. If the selected period
 *   is partial, the comparison is cut to the same number of days, so that a month that has not
 *   ended is not compared with a whole month.
 * - The trend has the 12 months up to the month of the period.
 *
 * All dates are calendar dates at 00:00 in the PHP default time zone (ADR 005).
 */
final readonly class PeriodRangeFactory
{
    public const int MONTHS = 12;

    public function create(PeriodFilter $filter, \DateTimeImmutable $today): PeriodSelectionDto
    {
        $today = new \DateTimeImmutable($today->format('Y-m-d'));
        $selected = self::period($filter->type, $filter->anchor, $today);
        $comparison = self::period($filter->type, self::comparisonAnchor($filter), $today);
        $next = self::shift($filter->type, $filter->anchor, 1);

        return new PeriodSelectionDto(
            $filter,
            $selected,
            $comparison,
            self::cut($comparison, $selected),
            self::shift($filter->type, $filter->anchor, -1),
            self::start($filter->type, $next) > $today ? null : $next,
            MonthDto::fromQuery($filter->anchor->format('Y-m'), $today)->trailing(self::MONTHS),
        );
    }

    private static function period(PeriodType $type, \DateTimeImmutable $anchor, \DateTimeImmutable $today): PeriodDto
    {
        $first = self::start($type, $anchor);
        $last = match ($type) {
            PeriodType::Day => $first,
            PeriodType::Week => $first->modify('+6 days'),
            PeriodType::Month => $first->modify('last day of this month'),
            PeriodType::Year => $first->setDate((int) $first->format('Y'), 12, 31),
        };

        return new PeriodDto($type, $first, $last, DateRangeDto::create($first, min($last, $today)), $last > $today);
    }

    private static function start(PeriodType $type, \DateTimeImmutable $anchor): \DateTimeImmutable
    {
        return match ($type) {
            PeriodType::Day => $anchor,
            PeriodType::Week => $anchor->modify('monday this week'),
            PeriodType::Month => $anchor->modify('first day of this month'),
            PeriodType::Year => $anchor->setDate((int) $anchor->format('Y'), 1, 1),
        };
    }

    /**
     * Gives a date in the period that is a number of periods before or after the anchor.
     */
    private static function shift(PeriodType $type, \DateTimeImmutable $anchor, int $periods): \DateTimeImmutable
    {
        return match ($type) {
            PeriodType::Day => $anchor->modify(\sprintf('%+d days', $periods)),
            PeriodType::Week => $anchor->modify(\sprintf('%+d days', 7 * $periods)),
            PeriodType::Month => $anchor->modify('first day of this month')->modify(\sprintf('%+d months', $periods)),
            PeriodType::Year => $anchor->setDate((int) $anchor->format('Y') + $periods, 1, 1),
        };
    }

    /**
     * Gives a date in the comparison period. The same day last year of 29 February is 28 February.
     */
    private static function comparisonAnchor(PeriodFilter $filter): \DateTimeImmutable
    {
        if (Comparison::Previous === $filter->comparison) {
            return self::shift($filter->type, $filter->anchor, -1);
        }
        $year = (int) $filter->anchor->format('Y') - 1;
        $month = (int) $filter->anchor->format('n');
        $daysInMonth = (int) $filter->anchor->setDate($year, $month, 1)->format('t');

        return $filter->anchor->setDate($year, $month, min((int) $filter->anchor->format('j'), $daysInMonth));
    }

    /**
     * Cuts the comparison to the number of days of a selected period that has not ended.
     */
    private static function cut(PeriodDto $comparison, PeriodDto $selected): DateRangeDto
    {
        if (!$selected->partial) {
            return $comparison->range;
        }
        $days = (int) $selected->range->firstDate->diff($selected->range->lastDate)->days;

        return DateRangeDto::create(
            $comparison->range->firstDate,
            min($comparison->range->firstDate->modify(\sprintf('+%d days', $days)), $comparison->range->lastDate),
        );
    }
}
