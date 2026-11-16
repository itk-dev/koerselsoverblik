<?php

declare(strict_types=1);

namespace App\Tests\Unit\Report;

use App\Report\MonthDto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks the month filter of a page: a valid month from the query, or the current month.
 */
final class MonthDtoTest extends TestCase
{
    private const string TODAY = '2026-11-09';

    public function testMonthFromTheQuery(): void
    {
        $month = self::fromQuery('2026-02');

        self::assertSame('2026-02', $month->query());
        self::assertSame(2, $month->number());
        self::assertSame(2026, $month->year());
        self::assertSame('2026-02-01 00:00:00', $month->range()->firstDate->format('Y-m-d H:i:s'));
        self::assertSame('2026-02-28', $month->range()->lastDate->format('Y-m-d'));
    }

    /**
     * The month of a date starts on the first day at 00:00, also from the last day at a time.
     */
    public function testMonthOfADate(): void
    {
        $month = MonthDto::of(new \DateTimeImmutable('2026-03-31 15:45'));

        self::assertSame('2026-03-01 00:00:00', $month->range()->firstDate->format('Y-m-d H:i:s'));
        self::assertSame('2026-03-31', $month->range()->lastDate->format('Y-m-d'));
    }

    public function testLastDateOfAMonthInALeapYear(): void
    {
        self::assertSame('2024-02-29', self::fromQuery('2024-02')->range()->lastDate->format('Y-m-d'));
    }

    public function testCurrentMonthIsTheMonthOfToday(): void
    {
        self::assertSame('2026-11', self::fromQuery(null)->query());
        self::assertSame('2026-11', self::fromQuery('2026-11')->query());
    }

    /**
     * An invalid month gives the current month, so that a link always works. A month after the
     * current month has no data yet.
     */
    #[DataProvider('invalidMonths')]
    public function testInvalidMonthGivesTheCurrentMonth(string $value): void
    {
        self::assertSame('2026-11', self::fromQuery($value)->query());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidMonths(): iterable
    {
        yield 'empty' => [''];
        yield 'text' => ['november'];
        yield 'a month that does not exist' => ['2026-13'];
        yield 'one digit' => ['2026-2'];
        yield 'a date' => ['2026-02-01'];
        yield 'the next month' => ['2026-12'];
    }

    public function testPreviousAndNextMonth(): void
    {
        self::assertSame('2025-12', self::fromQuery('2026-01')->previous()->query());
        self::assertSame('2026-02', self::fromQuery('2026-01')->next()->query());
        self::assertSame('2026-01', self::fromQuery('2025-12')->next()->query());
    }

    public function testIsBefore(): void
    {
        self::assertTrue(self::fromQuery('2026-10')->isBefore(self::fromQuery('2026-11')));
        self::assertFalse(self::fromQuery('2026-11')->isBefore(self::fromQuery('2026-11')));
        self::assertFalse(self::fromQuery('2026-11')->isBefore(self::fromQuery('2026-10')));
    }

    /**
     * The twelve months up to and including the month, the oldest first.
     */
    public function testTrailingMonths(): void
    {
        $months = self::fromQuery('2026-01')->trailing(12);

        self::assertSame(
            ['2025-02', '2025-03', '2025-04', '2025-05', '2025-06', '2025-07', '2025-08', '2025-09', '2025-10', '2025-11', '2025-12', '2026-01'],
            array_map(static fn (MonthDto $month): string => $month->query(), $months),
        );
    }

    private static function fromQuery(?string $value): MonthDto
    {
        return MonthDto::fromQuery($value, new \DateTimeImmutable(self::TODAY));
    }
}
