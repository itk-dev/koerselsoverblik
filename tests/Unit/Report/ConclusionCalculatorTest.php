<?php

declare(strict_types=1);

namespace App\Tests\Unit\Report;

use App\Report\ConclusionCalculator;
use App\Report\ConclusionDto;
use App\Report\ConclusionMeasure;
use App\Report\ConclusionReadingDto;
use App\Report\ConclusionThreshold;
use App\Report\ConclusionTone;
use App\Report\DelayBandsDto;
use App\Report\MonthDto;
use App\Report\OverviewPageDto;
use App\Report\PeriodFilter;
use App\Report\PunctualityCountDto;
use App\Report\PunctualityFiguresDto;
use App\Report\PunctualityPageDto;
use App\Report\PunctualityReportDto;
use App\Report\SoloCountDto;
use App\Report\TripCountDto;
use App\Report\WorstLocationsDto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks the rules of the conclusion of the overview: the direction of each measure, the
 * threshold, and the headline for each combination of better, worse and unchanged.
 *
 * @phpstan-type Counts array{punctuality: array{int, int}, solo: array{int, int}, cancelled: array{int, int}}
 */
final class ConclusionCalculatorTest extends TestCase
{
    /**
     * The values of a period: the drop-offs per run on time and measured, the real solo runs and
     * the runs, and the cancelled trips and the trips. Each gives 50 %.
     */
    private const array SAME = ['punctuality' => [50, 100], 'solo' => [50, 100], 'cancelled' => [50, 100]];

    /**
     * @param Counts $now
     */
    #[DataProvider('headlines')]
    public function testHeadline(array $now, ConclusionTone $expected): void
    {
        $conclusion = self::calculate('punctuality,solo,cancelled', '1', $now, self::SAME);

        self::assertSame($expected, $conclusion->tone);
    }

    /**
     * @return iterable<string, array{Counts, ConclusionTone}>
     */
    public static function headlines(): iterable
    {
        yield 'all unchanged' => [self::SAME, ConclusionTone::Unchanged];
        // A higher punctuality is better. Lower shares of real solo runs and cancelled trips are better.
        yield 'one better, the others unchanged' => [['punctuality' => [60, 100]] + self::SAME, ConclusionTone::Better];
        yield 'all better' => [['punctuality' => [60, 100], 'solo' => [40, 100], 'cancelled' => [40, 100]], ConclusionTone::Better];
        yield 'one worse, the others unchanged' => [['solo' => [60, 100]] + self::SAME, ConclusionTone::Worse];
        yield 'all worse' => [['punctuality' => [40, 100], 'solo' => [60, 100], 'cancelled' => [60, 100]], ConclusionTone::Worse];
        yield 'better and worse' => [['punctuality' => [60, 100], 'cancelled' => [60, 100]] + self::SAME, ConclusionTone::Mixed];
        yield 'better, worse and unchanged' => [['solo' => [40, 100], 'cancelled' => [60, 100]] + self::SAME, ConclusionTone::Mixed];
    }

    /**
     * The conclusion has a reading for each selected measure, in the order of the measures.
     */
    public function testReadings(): void
    {
        $conclusion = self::calculate('punctuality,solo,cancelled', '1', ['punctuality' => [75, 100], 'solo' => [1, 4], 'cancelled' => [3, 30]], self::SAME);

        self::assertEquals([
            new ConclusionReadingDto(ConclusionMeasure::Punctuality, 75.0, 50.0, ConclusionTone::Better),
            new ConclusionReadingDto(ConclusionMeasure::RealSolo, 25.0, 50.0, ConclusionTone::Better),
            new ConclusionReadingDto(ConclusionMeasure::Cancelled, 10.0, 50.0, ConclusionTone::Better),
        ], $conclusion->readings);
    }

    /**
     * Only the selected measures count.
     */
    public function testOnlyTheSelectedMeasuresCount(): void
    {
        $conclusion = self::calculate('cancelled', '1', ['punctuality' => [10, 100], 'cancelled' => [40, 100]] + self::SAME, self::SAME);

        self::assertSame(ConclusionTone::Better, $conclusion->tone);
        self::assertCount(1, $conclusion->readings);
        self::assertSame(ConclusionMeasure::Cancelled, $conclusion->readings[0]->measure);
    }

    /**
     * A selected measure with no value in the comparison period gives no comparison. So does a
     * selected measure with no value in the selected period.
     *
     * @param Counts  $now
     * @param ?Counts $before
     */
    #[DataProvider('noComparisons')]
    public function testNoComparison(string $measures, array $now, ?array $before): void
    {
        $conclusion = self::calculate($measures, '1', $now, $before);

        self::assertSame(ConclusionTone::NoComparison, $conclusion->tone);
        self::assertSame([], $conclusion->readings);
    }

    /**
     * @return iterable<string, array{string, Counts, ?Counts}>
     */
    public static function noComparisons(): iterable
    {
        yield 'no comparison period' => ['punctuality', self::SAME, null];
        yield 'no measured runs before' => ['punctuality', self::SAME, ['punctuality' => [0, 0]] + self::SAME];
        yield 'no solo runs before' => ['solo', self::SAME, ['solo' => [0, 0]] + self::SAME];
        yield 'no trips before' => ['cancelled', self::SAME, ['cancelled' => [0, 0]] + self::SAME];
        yield 'no measured runs now' => ['punctuality', ['punctuality' => [0, 0]] + self::SAME, self::SAME];
        yield 'no trips now' => ['cancelled', ['cancelled' => [0, 0]] + self::SAME, self::SAME];
        // One measure with no value is sufficient.
        yield 'one of more measures' => ['punctuality,cancelled', self::SAME, ['cancelled' => [0, 0]] + self::SAME];
    }

    /**
     * A measure counts as better or worse only if its change is at least the threshold, in
     * percentage points.
     */
    #[DataProvider('comparisons')]
    public function testCompare(ConclusionMeasure $measure, float $now, float $before, ConclusionThreshold $threshold, ConclusionTone $expected): void
    {
        self::assertSame($expected, ConclusionCalculator::compare($measure, $now, $before, $threshold));
    }

    /**
     * @return iterable<string, array{ConclusionMeasure, float, float, ConclusionThreshold, ConclusionTone}>
     */
    public static function comparisons(): iterable
    {
        $punctuality = ConclusionMeasure::Punctuality;
        $solo = ConclusionMeasure::RealSolo;
        $cancelled = ConclusionMeasure::Cancelled;

        yield 'higher punctuality' => [$punctuality, 51.0, 50.0, ConclusionThreshold::One, ConclusionTone::Better];
        yield 'lower punctuality' => [$punctuality, 49.0, 50.0, ConclusionThreshold::One, ConclusionTone::Worse];
        yield 'higher share of real solo runs' => [$solo, 51.0, 50.0, ConclusionThreshold::One, ConclusionTone::Worse];
        yield 'lower share of real solo runs' => [$solo, 49.0, 50.0, ConclusionThreshold::One, ConclusionTone::Better];
        yield 'higher share of cancelled trips' => [$cancelled, 51.0, 50.0, ConclusionThreshold::One, ConclusionTone::Worse];
        yield 'lower share of cancelled trips' => [$cancelled, 49.0, 50.0, ConclusionThreshold::One, ConclusionTone::Better];
        yield 'at the threshold' => [$punctuality, 50.5, 50.0, ConclusionThreshold::Half, ConclusionTone::Better];
        yield 'below the threshold' => [$punctuality, 50.25, 50.0, ConclusionThreshold::Half, ConclusionTone::Unchanged];
        yield 'below the threshold, lower' => [$punctuality, 49.75, 50.0, ConclusionThreshold::Half, ConclusionTone::Unchanged];
        yield 'at two points' => [$punctuality, 48.0, 50.0, ConclusionThreshold::Two, ConclusionTone::Worse];
        yield 'below two points' => [$punctuality, 48.25, 50.0, ConclusionThreshold::Two, ConclusionTone::Unchanged];
        yield 'at five points' => [$solo, 55.0, 50.0, ConclusionThreshold::Five, ConclusionTone::Worse];
        yield 'below five points' => [$solo, 54.75, 50.0, ConclusionThreshold::Five, ConclusionTone::Unchanged];
        yield 'one point of a share of 45 %' => [$solo, 46.25, 45.25, ConclusionThreshold::One, ConclusionTone::Worse];
        // 0.875 percentage points is about 2 % of 45 %, but it is below a threshold of 1 point.
        yield 'points, not a relative change' => [$solo, 46.25, 45.375, ConclusionThreshold::One, ConclusionTone::Unchanged];
        yield 'any change' => [$punctuality, 50.01, 50.0, ConclusionThreshold::Any, ConclusionTone::Better];
        yield 'no change with any change' => [$punctuality, 50.0, 50.0, ConclusionThreshold::Any, ConclusionTone::Unchanged];
        yield 'from 0' => [$cancelled, 5.0, 0.0, ConclusionThreshold::Five, ConclusionTone::Worse];
        yield '0 and 0' => [$cancelled, 0.0, 0.0, ConclusionThreshold::Any, ConclusionTone::Unchanged];
    }

    /**
     * The headline has the colour of a callout.
     */
    public function testCalloutOfTheHeadline(): void
    {
        self::assertSame('success', ConclusionTone::Better->callout());
        self::assertSame('warn', ConclusionTone::Worse->callout());
        self::assertSame('info', ConclusionTone::Mixed->callout());
        self::assertSame('info', ConclusionTone::Unchanged->callout());
        self::assertSame('info', ConclusionTone::NoComparison->callout());
    }

    /**
     * @param Counts  $now
     * @param ?Counts $before
     */
    private static function calculate(string $measures, string $threshold, array $now, ?array $before): ConclusionDto
    {
        $filter = PeriodFilter::fromQuery(null, null, null, null, new \DateTimeImmutable('2026-01-20'), $measures, $threshold);
        $months = MonthDto::fromQuery('2026-01', new \DateTimeImmutable('2026-01-20'))->trailing(1);
        $trips = self::trips($now);
        $solo = new SoloCountDto($now['solo'][1], $now['solo'][0]);
        $figures = self::figures($now);
        $overview = new OverviewPageDto(
            $months,
            [$trips],
            [$solo],
            $trips,
            null === $before ? null : self::trips($before),
            $solo,
            null === $before ? null : new SoloCountDto($before['solo'][1], $before['solo'][0]),
        );
        $punctuality = new PunctualityPageDto(
            $months,
            [$figures],
            new PunctualityReportDto($figures, [], [], new WorstLocationsDto([], 0, 10)),
            null === $before ? null : self::figures($before),
            90,
        );

        return new ConclusionCalculator()->calculate($filter, $overview, $punctuality);
    }

    /**
     * @param Counts $counts
     */
    private static function trips(array $counts): TripCountDto
    {
        [$cancelled, $trips] = $counts['cancelled'];

        return new TripCountDto($trips, $trips - $cancelled, $cancelled, 0, 1);
    }

    /**
     * @param Counts $counts
     */
    private static function figures(array $counts): PunctualityFiguresDto
    {
        $none = new PunctualityCountDto(0, 0);

        return new PunctualityFiguresDto($none, $none, $none, new PunctualityCountDto(...$counts['punctuality']), new DelayBandsDto(0, 0, 0, 0));
    }
}
