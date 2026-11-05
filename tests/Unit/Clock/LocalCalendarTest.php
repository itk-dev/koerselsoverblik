<?php

namespace App\Tests\Unit\Clock;

use App\Clock\LocalCalendar;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class LocalCalendarTest extends TestCase
{
    /**
     * At 22:30 UTC on 1 June it is 00:30 on 2 June in Copenhagen. The calendar date is at 00:00 UTC.
     */
    public function testTodayIsTheDateInTheApplicationTimeZone(): void
    {
        $calendar = new LocalCalendar(new MockClock('2026-06-01 22:30:00', 'UTC'), 'Europe/Copenhagen');

        $today = $calendar->today();

        self::assertSame('2026-06-02 00:00:00', $today->format('Y-m-d H:i:s'));
        self::assertSame('UTC', $today->getTimezone()->getName());
    }
}
