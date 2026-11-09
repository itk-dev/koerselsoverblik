<?php

declare(strict_types=1);

namespace App\Tests\Unit\Report;

use App\Report\DelayBandsDto;
use App\Report\PunctualityCountDto;
use App\Report\PunctualityFiguresDto;
use PHPUnit\Framework\TestCase;

/**
 * Checks the counts of the punctuality measures: the percent, no value for no measured rows, and
 * the sum of two counts.
 */
final class PunctualityCountDtoTest extends TestCase
{
    public function testPercentIsOnTimeOfMeasured(): void
    {
        self::assertEqualsWithDelta(83.333, new PunctualityCountDto(5, 6)->percent(), 0.001);
        self::assertSame(100.0, new PunctualityCountDto(4, 4)->percent());
        self::assertSame(0.0, new PunctualityCountDto(0, 3)->percent());
    }

    /**
     * No measured rows give no value, not 0.
     */
    public function testNoMeasuredRowsGiveNoPercent(): void
    {
        self::assertNull(new PunctualityCountDto(0, 0)->percent());
    }

    public function testAddGivesTheSum(): void
    {
        self::assertEquals(new PunctualityCountDto(7, 10), new PunctualityCountDto(5, 6)->add(new PunctualityCountDto(2, 4)));
    }

    public function testBandsAddGivesTheSum(): void
    {
        self::assertEquals(
            new DelayBandsDto(4, 6, 8, 10),
            new DelayBandsDto(1, 2, 3, 4)->add(new DelayBandsDto(3, 4, 5, 6)),
        );
    }

    public function testFiguresAddGivesTheSumOfEachMeasure(): void
    {
        $figures = new PunctualityFiguresDto(
            new PunctualityCountDto(1, 2),
            new PunctualityCountDto(3, 4),
            new PunctualityCountDto(5, 6),
            new PunctualityCountDto(7, 8),
            new DelayBandsDto(1, 2, 3, 4),
        );

        self::assertEquals(
            new PunctualityFiguresDto(
                new PunctualityCountDto(2, 4),
                new PunctualityCountDto(6, 8),
                new PunctualityCountDto(10, 12),
                new PunctualityCountDto(14, 16),
                new DelayBandsDto(2, 4, 6, 8),
            ),
            $figures->add($figures),
        );
    }

    public function testEmptyFiguresHaveNoValues(): void
    {
        $figures = PunctualityFiguresDto::empty();

        self::assertEquals(new DelayBandsDto(0, 0, 0, 0), $figures->bands);
        foreach ([$figures->pickupPerCitizen, $figures->dropOffPerCitizen, $figures->pickupPerRun, $figures->dropOffPerRun] as $count) {
            self::assertEquals(new PunctualityCountDto(0, 0), $count);
            self::assertNull($count->percent());
        }
    }
}
