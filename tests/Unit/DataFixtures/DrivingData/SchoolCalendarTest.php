<?php

declare(strict_types=1);

namespace App\Tests\Unit\DataFixtures\DrivingData;

use App\DataFixtures\DrivingData\SchoolCalendar;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks the days of the Aarhus school calendar that the development dataset uses.
 */
final class SchoolCalendarTest extends TestCase
{
    #[DataProvider('days')]
    public function testDayType(string $date, string $expected): void
    {
        $calendar = new SchoolCalendar();
        $day = new \DateTimeImmutable($date);

        $actual = match (true) {
            $calendar->isWeekend($day) => 'weekend',
            $calendar->isHoliday($day) => 'holiday',
            default => 'school day',
        };

        self::assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function days(): iterable
    {
        yield 'school day' => ['2026-09-15', 'school day'];
        yield 'Saturday' => ['2026-09-19', 'weekend'];
        yield 'Sunday' => ['2026-09-20', 'weekend'];
        yield 'Saturday in a holiday is a weekend' => ['2026-10-10', 'weekend'];
        yield 'first day of the autumn holiday 2025' => ['2025-10-13', 'holiday'];
        yield 'day before the Christmas holiday 2025' => ['2025-12-22', 'school day'];
        yield 'first weekday of the Christmas holiday 2025' => ['2025-12-23', 'holiday'];
        yield 'last weekday of the Christmas holiday 2025' => ['2026-01-02', 'holiday'];
        yield 'first school day of 2026' => ['2026-01-05', 'school day'];
        yield 'winter holiday 2026' => ['2026-02-09', 'holiday'];
        yield 'Easter Monday 2026' => ['2026-04-06', 'holiday'];
        yield 'day after Easter 2026' => ['2026-04-07', 'school day'];
        yield 'day after Ascension Day 2026' => ['2026-05-15', 'holiday'];
        yield 'Whit Monday 2026' => ['2026-05-25', 'holiday'];
        yield 'Constitution Day 2026' => ['2026-06-05', 'holiday'];
        yield 'last day of the summer holiday 2026' => ['2026-08-10', 'holiday'];
        yield 'first school day after the summer 2026' => ['2026-08-11', 'school day'];
        yield 'autumn holiday 2026' => ['2026-10-16', 'holiday'];
        yield 'Easter holiday 2027' => ['2027-03-29', 'holiday'];
        yield 'Whitsun 2028' => ['2028-06-05', 'holiday'];
        yield 'Constitution Day in a year with no list' => ['2030-06-05', 'holiday'];
        yield 'weekday in a year with no list' => ['2030-03-05', 'school day'];
    }

    public function testSummerHolidayIsTheLongHoliday(): void
    {
        $calendar = new SchoolCalendar();

        self::assertTrue($calendar->isSummerHoliday(new \DateTimeImmutable('2026-07-15')));
        self::assertFalse($calendar->isSummerHoliday(new \DateTimeImmutable('2026-10-16')));
        self::assertFalse($calendar->isSummerHoliday(new \DateTimeImmutable('2026-09-15')));
    }
}
