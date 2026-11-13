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
 * - The trend has the 12 months up to the month of the last date of the period.
 *
 * All the ranges come from the first date of the period, so each date in a period gives the same
 * result. All dates are calendar dates at 00:00 in the PHP default time zone (ADR 005).
 */
final readonly class PeriodRangeFactory
{
    public const int MONTHS = 12;

    public function create(PeriodFilter $filter): PeriodSelectionDto
    {
        $type = $filter->type;
        $today = $filter->today;
        $first = $type->start($filter->anchor);
        $selected = self::period($type, $first, $today);
        $comparison = self::period($type, self::comparisonStart($type, $first, $filter->comparison), $today);
        $next = self::shift($type, $first, 1);

        return new PeriodSelectionDto(
            $filter,
            $selected,
            $comparison,
            self::cut($comparison, $selected),
            self::shift($type, $first, -1),
            $next > $today ? null : $next,
            MonthDto::of($selected->range->lastDate)->trailing(self::MONTHS),
        );
    }

    private static function period(PeriodType $type, \DateTimeImmutable $first, \DateTimeImmutable $today): PeriodDto
    {
        $last = match ($type) {
            PeriodType::Day => $first,
            PeriodType::Week => $first->modify('+6 days'),
            PeriodType::Month => $first->modify('last day of this month'),
            PeriodType::Year => $first->setDate((int) $first->format('Y'), 12, 31),
        };

        return new PeriodDto($type, $first, $last, DateRangeDto::create($first, min($last, $today)), $last > $today);
    }

    /**
     * Gives the first date of the period that is a number of periods before or after a period.
     */
    private static function shift(PeriodType $type, \DateTimeImmutable $first, int $periods): \DateTimeImmutable
    {
        return $first->modify(\sprintf('%+d %s', $periods, match ($type) {
            PeriodType::Day => 'days',
            PeriodType::Week => 'weeks',
            PeriodType::Month => 'months',
            PeriodType::Year => 'years',
        }));
    }

    /**
     * Gives the first date of the comparison period.
     *
     * - The same day last year of 29 February is 28 February.
     * - The same week last year has the same ISO week number. Week 53 gives week 52 in a year
     *   that has 52 weeks.
     */
    private static function comparisonStart(PeriodType $type, \DateTimeImmutable $first, Comparison $comparison): \DateTimeImmutable
    {
        if (Comparison::Previous === $comparison) {
            return self::shift($type, $first, -1);
        }
        if (PeriodType::Week === $type) {
            $year = (int) $first->format('o') - 1;
            // 28 December is always in the last ISO week of its year.
            $weeks = (int) $first->setDate($year, 12, 28)->format('W');

            return $first->setISODate($year, min((int) $first->format('W'), $weeks));
        }
        $year = (int) $first->format('Y') - 1;
        $month = (int) $first->format('n');
        $daysInMonth = (int) $first->setDate($year, $month, 1)->format('t');

        return $first->setDate($year, $month, min((int) $first->format('j'), $daysInMonth));
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
