<?php

declare(strict_types=1);

namespace App\Report;

/**
 * Calculates the conclusion of the overview from the figures that the overview already has. It
 * runs no queries.
 *
 * A measure is better or worse only if its change from the comparison period is at least the
 * threshold, in percentage points. A smaller change is noise, so it counts as unchanged.
 */
final readonly class ConclusionCalculator
{
    public function calculate(PeriodFilter $filter, OverviewPageDto $page, PunctualityPageDto $punctuality): ConclusionDto
    {
        $readings = [];
        foreach ($filter->measures as $measure) {
            [$now, $before] = self::values($measure, $page, $punctuality);
            if (null === $now || null === $before) {
                return new ConclusionDto(ConclusionTone::NoComparison, []);
            }
            $readings[] = new ConclusionReadingDto($measure, $now, $before, self::compare($measure, $now, $before, $filter->threshold));
        }

        $tones = array_map(static fn (ConclusionReadingDto $reading): ConclusionTone => $reading->tone, $readings);
        $better = \in_array(ConclusionTone::Better, $tones, true);
        $worse = \in_array(ConclusionTone::Worse, $tones, true);

        return new ConclusionDto(match (true) {
            $better && $worse => ConclusionTone::Mixed,
            $better => ConclusionTone::Better,
            $worse => ConclusionTone::Worse,
            default => ConclusionTone::Unchanged,
        }, $readings);
    }

    /**
     * Gives better, worse or unchanged for the values of a measure in percent. The change is in
     * percentage points, the unit of the changes on the tiles.
     *
     * The change is rounded to one decimal, as the tiles show it. Thus the conclusion agrees with
     * the tiles, and a change of exactly one point from counts (0.9999999999999991) is one point.
     */
    public static function compare(ConclusionMeasure $measure, float $now, float $before, ConclusionThreshold $threshold): ConclusionTone
    {
        $change = round($now - $before, 1);
        if (0.0 === $change || abs($change) < $threshold->points()) {
            return ConclusionTone::Unchanged;
        }

        return ($change > 0) === $measure->higherIsBetter() ? ConclusionTone::Better : ConclusionTone::Worse;
    }

    /**
     * Gives the value of a measure in the selected period and in the comparison period, in
     * percent. A period with no data has no value.
     *
     * @return array{?float, ?float}
     */
    private static function values(ConclusionMeasure $measure, OverviewPageDto $page, PunctualityPageDto $punctuality): array
    {
        return match ($measure) {
            // The drop-off per route run, as in the punctuality tile of the overview.
            ConclusionMeasure::Punctuality => [$punctuality->current()->total->dropOffPerRun->percent(), $punctuality->comparison?->dropOffPerRun->percent()],
            ConclusionMeasure::RealSolo => [$page->selectedSolo->percent(), $page->comparisonSolo?->percent()],
            // Of all trips, as the cancelled trips in the table of the suppliers.
            ConclusionMeasure::Cancelled => [self::cancelledPercent($page->selected), self::cancelledPercent($page->comparison)],
        };
    }

    private static function cancelledPercent(?TripCountDto $count): ?float
    {
        return null === $count || 0 === $count->trips ? null : $count->cancelled / $count->trips * 100;
    }
}
