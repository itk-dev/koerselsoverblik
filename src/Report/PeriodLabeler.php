<?php

declare(strict_types=1);

namespace App\Report;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Gives the Danish labels of the periods, in the style of the prototype:
 *
 * - a week: "Uge 38 · 14. sep–20. sep 2026"
 * - a month: "september 2026"
 * - a quarter: "3. kvartal 2026"
 * - a half year: "2. halvår 2026"
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
        $month = (int) $first->format('n');

        return match ($period->type) {
            PeriodType::Week => $this->translator->trans('period.week', [
                'week' => (int) $first->format('W'),
                'first' => $this->dayShort($first),
                'last' => $this->dayShort($period->lastDate),
                'year' => $period->lastDate->format('Y'),
            ]),
            PeriodType::Month => $this->translator->trans('period.month', [
                'month' => $this->translator->trans('month.long.'.$month),
                'year' => $first->format('Y'),
            ]),
            // The first month of a quarter is 1, 4, 7 or 10, and of a half year 1 or 7.
            PeriodType::Quarter => $this->translator->trans('period.quarter', ['quarter' => intdiv($month, 3) + 1, 'year' => $first->format('Y')]),
            PeriodType::HalfYear => $this->translator->trans('period.half_year', ['half' => 1 === $month ? 1 : 2, 'year' => $first->format('Y')]),
        };
    }

    /**
     * Gives the selected period in a sentence. A partial period names its dates: "1.–18. september
     * 2026 (til og med i dag)", or "18.–31. maj 2026 (delvis periode)" from the start of the data.
     */
    public function phrase(PeriodSelectionDto $selection): string
    {
        return match (true) {
            // Only the label of a week starts with a capital letter: "Uge".
            !$selection->selected->isPartial() => lcfirst($this->label($selection->selected)),
            $selection->selected->endsAfterToday => $this->translator->trans('period.until_today', ['range' => $this->range($selection->selectedRange)]),
            default => $this->translator->trans('period.partial', ['period' => $this->range($selection->selectedRange)]),
        };
    }

    /**
     * Gives the comparison period in a sentence. A cut comparison names its dates. A comparison
     * that starts before the start of the data is partial, as in the prototype.
     */
    public function comparisonPhrase(PeriodSelectionDto $selection): string
    {
        return match (true) {
            null === $selection->comparisonRange => $this->translator->trans('period.no_data'),
            $selection->isComparisonCut() => $this->range($selection->comparisonRange),
            $selection->comparison->startsBeforeData => $this->translator->trans('period.partial', ['period' => lcfirst($this->label($selection->comparison))]),
            default => lcfirst($this->label($selection->comparison)),
        };
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
