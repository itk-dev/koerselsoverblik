<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The figures of the punctuality page: the report of the selected period, the total of the
 * comparison period, and the totals of the 12 months up to the period for the history.
 */
final readonly class PunctualityPageDto
{
    /**
     * @param non-empty-list<MonthDto>              $months     the months of the history, the oldest first
     * @param non-empty-list<PunctualityFiguresDto> $history    the total of each month, in the order of the months
     * @param PunctualityReportDto                  $selected   the report of the selected period
     * @param ?PunctualityFiguresDto                $comparison the total of the comparison period, or null if the page has none
     * @param int                                   $target     the value of APP_PUNCTUALITY_TARGET
     */
    public function __construct(
        public array $months,
        public array $history,
        public PunctualityReportDto $selected,
        public ?PunctualityFiguresDto $comparison,
        public int $target,
    ) {
        // The page reads the measures several times, so they are calculated one time.
        $this->measures = [
            $this->measure('pickup_per_citizen', 'series-info', false, static fn (PunctualityFiguresDto $figures): PunctualityCountDto => $figures->pickupPerCitizen),
            $this->measure('drop_off_per_citizen', 'series-blue', false, static fn (PunctualityFiguresDto $figures): PunctualityCountDto => $figures->dropOffPerCitizen),
            $this->measure('pickup_per_run', 'series-info', true, static fn (PunctualityFiguresDto $figures): PunctualityCountDto => $figures->pickupPerRun),
            $this->measure('drop_off_per_run', 'series-blue', true, static fn (PunctualityFiguresDto $figures): PunctualityCountDto => $figures->dropOffPerRun),
        ];
    }

    /**
     * @var list<MeasureDto>
     */
    private array $measures;

    /**
     * Gives the report of the selected period.
     */
    public function current(): PunctualityReportDto
    {
        return $this->selected;
    }

    /**
     * Gives the four measures. The pickups and the drop-offs have their own colour, and the
     * measures for each route run are dashed.
     *
     * @return list<MeasureDto>
     */
    public function measures(): array
    {
        return $this->measures;
    }

    /**
     * Gives the drop-off per route run, the measure that the transport office uses.
     */
    public function headline(): MeasureDto
    {
        return $this->measures[3];
    }

    /**
     * Gives the number of measures that reach the target. A measure with no value does not.
     */
    public function measuresAtTarget(): int
    {
        return \count(array_filter(
            $this->measures(),
            fn (MeasureDto $measure): bool => PunctualityTone::Ok === PunctualityTone::fromPercent($measure->percent(), $this->target),
        ));
    }

    /**
     * Gives the measured last drop-offs that are later than the tolerance.
     */
    public function lateRuns(): CountTrendDto
    {
        return $this->countTrend(static fn (DelayBandsDto $bands): int => $bands->upTo15 + $bands->upTo30 + $bands->over30);
    }

    /**
     * Gives the measured last drop-offs that are more than 30 minutes late.
     */
    public function over30(): CountTrendDto
    {
        return $this->countTrend(static fn (DelayBandsDto $bands): int => $bands->over30);
    }

    /**
     * Gives the three late bands of each month: more than the tolerance to 15, more than 15 to 30,
     * and more than 30 minutes.
     *
     * @return array{list<float>, list<float>, list<float>}
     */
    public function lateBandHistory(): array
    {
        $bands = array_map(static fn (PunctualityFiguresDto $figures): DelayBandsDto => $figures->bands, $this->history);

        return [
            array_map(static fn (DelayBandsDto $band): float => $band->upTo15, $bands),
            array_map(static fn (DelayBandsDto $band): float => $band->upTo30, $bands),
            array_map(static fn (DelayBandsDto $band): float => $band->over30, $bands),
        ];
    }

    /**
     * @param \Closure(PunctualityFiguresDto): PunctualityCountDto $count
     */
    private function measure(string $key, string $cssClass, bool $dashed, \Closure $count): MeasureDto
    {
        $current = $count($this->selected->total)->percent();
        $before = null === $this->comparison ? null : $count($this->comparison)->percent();

        return new MeasureDto(
            $key,
            $cssClass,
            $dashed,
            $count($this->selected->total),
            null === $current || null === $before ? null : $current - $before,
            array_map(static fn (PunctualityFiguresDto $figures): ?float => $count($figures)->percent(), $this->history),
        );
    }

    /**
     * A period with no measured last drop-offs has no data, so its count is null, not 0.
     *
     * @param \Closure(DelayBandsDto): int $count
     */
    private function countTrend(\Closure $count): CountTrendDto
    {
        $countOf = static fn (?PunctualityFiguresDto $figures): ?int => null === $figures || 0 === $figures->dropOffPerRun->measured
            ? null
            : $count($figures->bands);
        $current = $countOf($this->selected->total);
        $before = $countOf($this->comparison);

        return new CountTrendDto(
            $current,
            null === $current || null === $before ? null : $current - $before,
            array_map(static fn (PunctualityFiguresDto $figures): ?float => null === ($value = $countOf($figures)) ? null : (float) $value, $this->history),
        );
    }
}
