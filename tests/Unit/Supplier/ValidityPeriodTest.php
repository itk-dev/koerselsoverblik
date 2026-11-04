<?php

namespace App\Tests\Unit\Supplier;

use App\Supplier\ValidityPeriod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks when two periods of supplier assignments overlap. Both dates are part of a period.
 */
final class ValidityPeriodTest extends TestCase
{
    #[DataProvider('periods')]
    public function testOverlap(string $first, ?string $firstEnd, string $second, ?string $secondEnd, bool $overlaps): void
    {
        $a = self::period($first, $firstEnd);
        $b = self::period($second, $secondEnd);

        self::assertSame($overlaps, $a->overlaps($b));
        self::assertSame($overlaps, $b->overlaps($a));
    }

    /**
     * @return iterable<string, array{string, ?string, string, ?string, bool}>
     */
    public static function periods(): iterable
    {
        yield 'touching periods' => ['2026-01-01', '2026-01-31', '2026-02-01', '2026-02-28', false];
        yield 'same last and first day' => ['2026-01-01', '2026-01-31', '2026-01-31', '2026-02-28', true];
        yield 'gap between the periods' => ['2026-01-01', '2026-01-15', '2026-02-01', null, false];
        yield 'same start date' => ['2026-01-01', '2026-01-10', '2026-01-01', '2026-03-01', true];
        yield 'one period in the other' => ['2026-01-01', '2026-12-31', '2026-03-01', '2026-03-31', true];
        yield 'open end before a later start' => ['2026-01-01', null, '2026-06-01', '2026-06-30', true];
        yield 'later open end after an earlier period' => ['2026-01-01', '2026-01-31', '2026-02-01', null, false];
        yield 'two open ends' => ['2026-01-01', null, '2027-01-01', null, true];
    }

    public function testContainsTheFirstAndTheLastDate(): void
    {
        $period = self::period('2026-01-05', '2026-01-09');

        self::assertFalse($period->contains(new \DateTimeImmutable('2026-01-04')));
        self::assertTrue($period->contains(new \DateTimeImmutable('2026-01-05')));
        self::assertTrue($period->contains(new \DateTimeImmutable('2026-01-09 23:59')));
        self::assertFalse($period->contains(new \DateTimeImmutable('2026-01-10')));
    }

    public function testOpenEndContainsAllLaterDates(): void
    {
        $period = self::period('2026-01-05', null);

        self::assertFalse($period->contains(new \DateTimeImmutable('2026-01-04 23:59')));
        self::assertTrue($period->contains(new \DateTimeImmutable('2099-12-31')));
    }

    private static function period(string $from, ?string $to): ValidityPeriod
    {
        return new ValidityPeriod(new \DateTimeImmutable($from), null === $to ? null : new \DateTimeImmutable($to));
    }
}
