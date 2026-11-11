<?php

declare(strict_types=1);

namespace App\Report;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Gives the Danish labels of the periods, as in the prototype:
 *
 * - a day: "fre. 18. sep 2026"
 * - a week: "Uge 38 · 14. sep–20. sep 2026"
 * - a month: "september 2026"
 * - a year: "2026"
 * - a period that has not ended: "1.–18. september 2026 (til og med i dag)"
 */
final readonly class PeriodLabeler
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * Gives the label of the period, for the filter bar.
     */
    public function label(PeriodDto $period): string
    {
        $first = $period->firstDate;

        return match ($period->type) {
            PeriodType::Day => $this->translator->trans('period.day', [
                'weekday' => $this->translator->trans('weekday.short.'.$first->format('N')),
                'day' => $first->format('j'),
                'month' => $this->monthShort($first),
                'year' => $first->format('Y'),
            ]),
            PeriodType::Week => $this->translator->trans('period.week', [
                'week' => (int) $first->format('W'),
                'first' => $this->dayShort($first),
                'last' => $this->dayShort($period->lastDate),
                'year' => $period->lastDate->format('Y'),
            ]),
            PeriodType::Month => $this->translator->trans('period.month', [
                'month' => $this->translator->trans('month.long.'.$first->format('n')),
                'year' => $first->format('Y'),
            ]),
            PeriodType::Year => $first->format('Y'),
        };
    }

    /**
     * Gives the period in a sentence. A period that has not ended names its dates.
     */
    public function phrase(PeriodDto $period): string
    {
        return $period->partial
            ? $this->translator->trans('period.until_today', ['range' => $this->range($period->range)])
            : mb_strtolower($this->label($period));
    }

    /**
     * Gives the comparison period in a sentence. A cut comparison names its dates.
     */
    public function comparisonPhrase(PeriodSelectionDto $selection): string
    {
        return $selection->isComparisonCut()
            ? $this->range($selection->comparisonRange)
            : mb_strtolower($this->label($selection->comparison));
    }

    /**
     * Gives a span of days: "1.–18. september 2026", or "28. dec–3. jan 2027" across months.
     */
    private function range(DateRangeDto $range): string
    {
        $first = $range->firstDate;
        $last = $range->lastDate;
        $month = $this->translator->trans('month.long.'.$last->format('n'));

        return match (true) {
            $first == $last => $this->translator->trans('period.one_day', ['day' => $last->format('j'), 'month' => $month, 'year' => $last->format('Y')]),
            $first->format('Y-m') === $last->format('Y-m') => $this->translator->trans('period.days_of_month', [
                'first' => $first->format('j'),
                'last' => $last->format('j'),
                'month' => $month,
                'year' => $last->format('Y'),
            ]),
            default => $this->translator->trans('period.days', [
                'first' => $this->dayShort($first),
                'last' => $this->dayShort($last),
                'year' => $last->format('Y'),
            ]),
        };
    }

    private function dayShort(\DateTimeImmutable $date): string
    {
        return $this->translator->trans('period.day_short', ['day' => $date->format('j'), 'month' => $this->monthShort($date)]);
    }

    private function monthShort(\DateTimeImmutable $date): string
    {
        return $this->translator->trans('month.short.'.$date->format('n'));
    }
}
