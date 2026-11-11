<?php

declare(strict_types=1);

namespace App\Tests\Unit\Report;

use App\Report\DelayBandsDto;
use App\Report\MeasureDto;
use App\Report\MonthDto;
use App\Report\PunctualityCountDto;
use App\Report\PunctualityFiguresDto;
use App\Report\PunctualityPageDto;
use App\Report\PunctualityReportDto;
use PHPUnit\Framework\TestCase;

/**
 * Checks the figures of the punctuality page: the four measures of the month with the change from
 * the previous month and the history, and the late last drop-offs.
 */
final class PunctualityPageDtoTest extends TestCase
{
    public function testMeasuresOfTheMonth(): void
    {
        $page = self::page([
            self::figures([9, 10], [7, 10], [4, 5], [3, 4], [3, 1, 0, 0]),
            self::figures([8, 10], [0, 0], [5, 5], [1, 4], [1, 2, 0, 1]),
            self::figures([19, 20], [9, 10], [9, 10], [8, 10], [8, 1, 1, 0]),
        ]);

        self::assertSame(
            ['pickup_per_citizen', 'drop_off_per_citizen', 'pickup_per_run', 'drop_off_per_run'],
            array_map(static fn (MeasureDto $measure): string => $measure->key, $page->measures()),
        );
        [$pickup, $dropOff, $pickupPerRun, $dropOffPerRun] = $page->measures();

        self::assertEquals(new PunctualityCountDto(19, 20), $pickup->count);
        self::assertSame(95.0, $pickup->percent());
        self::assertEqualsWithDelta(15.0, $pickup->change, 0.001);
        self::assertSame([90.0, 80.0, 95.0], $pickup->history);
        self::assertSame('series-info', $pickup->cssClass);
        self::assertFalse($pickup->dashed);
        // The previous month has no measured drop-offs, so the change has no value.
        self::assertNull($dropOff->change);
        self::assertSame([70.0, null, 90.0], $dropOff->history);
        self::assertSame('series-blue', $dropOff->cssClass);
        self::assertFalse($dropOff->dashed);
        self::assertEqualsWithDelta(-10.0, $pickupPerRun->change, 0.001);
        self::assertSame('series-info', $pickupPerRun->cssClass);
        self::assertTrue($pickupPerRun->dashed);
        self::assertSame('series-blue', $dropOffPerRun->cssClass);
        self::assertTrue($dropOffPerRun->dashed);
    }

    /**
     * A measure reaches the target at or above it. A measure with no value does not.
     */
    public function testMeasuresThatReachTheTarget(): void
    {
        $page = self::page([
            self::figures([9, 10], [0, 0], [89, 100], [3, 4], [3, 1, 0, 0]),
        ]);

        self::assertSame(1, $page->measuresAtTarget());
        self::assertSame(90, $page->target);
    }

    public function testLateLastDropOffs(): void
    {
        $page = self::page([
            self::figures([0, 0], [0, 0], [0, 0], [3, 5], [3, 1, 0, 1]),
            self::figures([0, 0], [0, 0], [0, 0], [5, 9], [5, 1, 2, 1]),
        ]);

        self::assertSame(4, $page->lateRuns()->current);
        self::assertSame(2, $page->lateRuns()->change);
        self::assertSame([2.0, 4.0], $page->lateRuns()->history);
        self::assertSame(1, $page->over30()->current);
        self::assertSame(0, $page->over30()->change);
        self::assertSame([1.0, 1.0], $page->over30()->history);
        self::assertSame([[1.0, 1.0], [0.0, 2.0], [1.0, 1.0]], $page->lateBandHistory());
        self::assertEquals(new DelayBandsDto(5, 1, 2, 1), $page->current()->total->bands);
    }

    /**
     * The previous month has no measured last drop-offs, so the counts have no change.
     */
    public function testLateLastDropOffsWithNoPreviousMonth(): void
    {
        $page = self::page([
            PunctualityFiguresDto::empty(),
            self::figures([0, 0], [0, 0], [0, 0], [1, 2], [1, 1, 0, 0]),
        ]);

        self::assertNull($page->lateRuns()->change);
        self::assertNull($page->over30()->change);
    }

    /**
     * A month with no measured last drop-offs has no data, not 0 late runs. The history has a gap
     * for it.
     */
    public function testLateLastDropOffsOfAMonthWithNoData(): void
    {
        $page = self::page([
            self::figures([0, 0], [0, 0], [0, 0], [3, 5], [3, 1, 0, 1]),
            PunctualityFiguresDto::empty(),
        ]);

        self::assertNull($page->lateRuns()->current);
        self::assertNull($page->lateRuns()->change);
        self::assertSame([2.0, null], $page->lateRuns()->history);
        self::assertNull($page->over30()->current);
        self::assertSame([1.0, null], $page->over30()->history);
    }

    public function testPageOfOneMonthHasNoPreviousMonth(): void
    {
        $page = self::page([self::figures([1, 1], [1, 1], [1, 1], [1, 1], [1, 0, 0, 0])]);

        self::assertNull($page->measures()[0]->change);
        self::assertNull($page->lateRuns()->change);
    }

    /**
     * A period that is not a month, for example a week, has its own figures. The history stays
     * the months.
     */
    public function testSelectedPeriodIsNotTheLastMonth(): void
    {
        $months = [
            self::figures([9, 10], [7, 10], [4, 5], [3, 4], [3, 1, 0, 0]),
            self::figures([8, 10], [8, 10], [5, 5], [1, 4], [1, 2, 0, 1]),
        ];
        $page = new PunctualityPageDto(
            MonthDto::fromQuery('2026-01', new \DateTimeImmutable('2026-11-09'))->trailing(2),
            array_map(self::report(...), $months),
            self::report(self::figures([1, 2], [2, 2], [1, 1], [1, 2], [1, 1, 0, 0])),
            self::report(self::figures([2, 2], [1, 2], [0, 1], [2, 2], [2, 0, 0, 0])),
            90,
        );

        [$pickup, $dropOff, $pickupPerRun] = $page->measures();
        self::assertSame(50.0, $pickup->percent());
        self::assertEqualsWithDelta(-50.0, $pickup->change, 0.001);
        self::assertSame([90.0, 80.0], $pickup->history);
        self::assertEqualsWithDelta(50.0, $dropOff->change, 0.001);
        self::assertEqualsWithDelta(100.0, $pickupPerRun->change, 0.001);
        self::assertSame(1, $page->lateRuns()->current);
        self::assertSame(1, $page->lateRuns()->change);
        self::assertSame([1.0, 3.0], $page->lateRuns()->history);
        self::assertEquals(new DelayBandsDto(1, 1, 0, 0), $page->current()->total->bands);
    }

    /**
     * The selected period is the last month, and the comparison is the month before it.
     *
     * @param non-empty-list<PunctualityFiguresDto> $figures the figures of each month, the oldest first
     */
    private static function page(array $figures): PunctualityPageDto
    {
        $months = MonthDto::fromQuery('2026-01', new \DateTimeImmutable('2026-11-09'))->trailing(\count($figures));
        $reports = array_map(self::report(...), $figures);

        return new PunctualityPageDto($months, $reports, $reports[\count($reports) - 1], $reports[\count($reports) - 2] ?? null, 90);
    }

    private static function report(PunctualityFiguresDto $total): PunctualityReportDto
    {
        return new PunctualityReportDto($total, [], []);
    }

    /**
     * @param array{int, int}           $pickup
     * @param array{int, int}           $dropOff
     * @param array{int, int}           $pickupPerRun
     * @param array{int, int}           $dropOffPerRun
     * @param array{int, int, int, int} $bands
     */
    private static function figures(array $pickup, array $dropOff, array $pickupPerRun, array $dropOffPerRun, array $bands): PunctualityFiguresDto
    {
        return new PunctualityFiguresDto(
            new PunctualityCountDto(...$pickup),
            new PunctualityCountDto(...$dropOff),
            new PunctualityCountDto(...$pickupPerRun),
            new PunctualityCountDto(...$dropOffPerRun),
            new DelayBandsDto(...$bands),
        );
    }
}
