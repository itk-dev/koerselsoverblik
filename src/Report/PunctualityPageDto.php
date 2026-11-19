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
    public function lateRuns(): LateTrendDto
    {
        return $this->lateTrend(static fn (DelayBandsDto $bands): int => $bands->upTo15 + $bands->upTo30 + $bands->over30);
    }

    /**
     * Gives the measured last drop-offs that are more than 30 minutes late.
     */
    public function over30(): LateTrendDto
    {
        return $this->lateTrend(static fn (DelayBandsDto $bands): int => $bands->over30);
    }

    /**
     * Gives the share of the three late bands in each month, in percent of the measured runs of
     * the month: more than the tolerance to 15, more than 15 to 30, and more than 30 minutes. A
     * month with no measured runs has no share.
     *
     * @return array{list<float|null>, list<float|null>, list<float|null>}
     */
    public function lateBandShares(): array
    {
        [$upTo15, $upTo30, $over30] = self::lateBands();

        return [$this->bandShares($upTo15), $this->bandShares($upTo30), $this->bandShares($over30)];
    }

    /**
     * Gives the count of the three late bands in each month, in the order of lateBandShares(). A
     * month with no measured runs has no count.
     *
     * @return array{list<int|null>, list<int|null>, list<int|null>}
     */
    public function lateBandCounts(): array
    {
        [$upTo15, $upTo30, $over30] = self::lateBands();

        return [$this->bandCounts($upTo15), $this->bandCounts($upTo30), $this->bandCounts($over30)];
    }

    /**
     * Gives the three late bands: more than the tolerance to 15, more than 15 to 30, and more than
     * 30 minutes.
     *
     * @return array{\Closure(DelayBandsDto): int, \Closure(DelayBandsDto): int, \Closure(DelayBandsDto): int}
     */
    private static function lateBands(): array
    {
        return [
            static fn (DelayBandsDto $bands): int => $bands->upTo15,
            static fn (DelayBandsDto $bands): int => $bands->upTo30,
            static fn (DelayBandsDto $bands): int => $bands->over30,
        ];
    }

    /**
     * @param \Closure(DelayBandsDto): int $band
     *
     * @return list<float|null>
     */
    private function bandShares(\Closure $band): array
    {
        return array_map(static fn (PunctualityFiguresDto $figures): ?float => self::share($band($figures->bands), $figures), $this->history);
    }

    /**
     * @param \Closure(DelayBandsDto): int $band
     *
     * @return list<int|null>
     */
    private function bandCounts(\Closure $band): array
    {
        return array_map(
            static fn (PunctualityFiguresDto $figures): ?int => 0 === $figures->dropOffPerRun->measured ? null : $band($figures->bands),
            $this->history,
        );
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
     * A period with no measured last drop-offs has no data, so its count and share are null, not
     * 0. The change is of the share, so that periods with different volumes can be compared.
     *
     * @param \Closure(DelayBandsDto): int $count
     */
    private function lateTrend(\Closure $count): LateTrendDto
    {
        $total = $this->selected->total;
        $current = self::share($count($total->bands), $total);
        $before = null === $this->comparison ? null : self::share($count($this->comparison->bands), $this->comparison);

        return new LateTrendDto(
            null === $current ? null : $count($total->bands),
            $current,
            $total->dropOffPerRun->measured,
            null === $current || null === $before ? null : $current - $before,
            array_map(static fn (PunctualityFiguresDto $figures): ?float => self::share($count($figures->bands), $figures), $this->history),
        );
    }

    /**
     * Gives a count in percent of the measured last drop-offs, or null if none is measured.
     */
    private static function share(int $count, PunctualityFiguresDto $figures): ?float
    {
        $runs = $figures->dropOffPerRun->measured;

        return 0 === $runs ? null : $count / $runs * 100;
    }
}
