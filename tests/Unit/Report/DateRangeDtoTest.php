<?php

declare(strict_types=1);

namespace App\Tests\Unit\Report;

use App\Report\DateRangeDto;
use PHPUnit\Framework\TestCase;

/**
 * Checks the date range of a report: two calendar dates, and the last date is not before the
 * first date.
 */
final class DateRangeDtoTest extends TestCase
{
    public function testDatesAreCalendarDates(): void
    {
        $range = DateRangeDto::create(new \DateTimeImmutable('2026-01-05 13:45'), new \DateTimeImmutable('2026-01-31 23:59'));

        self::assertSame('2026-01-05 00:00:00', $range->firstDate->format('Y-m-d H:i:s'));
        self::assertSame('2026-01-31 00:00:00', $range->lastDate->format('Y-m-d H:i:s'));
        self::assertSame('Europe/Copenhagen', $range->firstDate->getTimezone()->getName());
    }

    public function testOneDayIsAccepted(): void
    {
        $range = DateRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-05 18:00'));

        self::assertEquals($range->firstDate, $range->lastDate);
    }

    public function testEqualsComparesTheDates(): void
    {
        $range = DateRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-31'));

        self::assertTrue($range->equals(DateRangeDto::create(new \DateTimeImmutable('2026-01-05 13:45'), new \DateTimeImmutable('2026-01-31'))));
        self::assertFalse($range->equals(DateRangeDto::create(new \DateTimeImmutable('2026-01-04'), new \DateTimeImmutable('2026-01-31'))));
        self::assertFalse($range->equals(DateRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-30'))));
    }

    public function testLastDateBeforeTheFirstDateIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The last date is before the first date.');

        DateRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-04 23:59'));
    }
}
