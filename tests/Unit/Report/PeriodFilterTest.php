<?php

declare(strict_types=1);

namespace App\Tests\Unit\Report;

use App\Report\Comparison;
use App\Report\PeriodFilter;
use App\Report\PeriodType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks that the filter reads the query string, and that an invalid value gives its default, so
 * that a link always works.
 */
final class PeriodFilterTest extends TestCase
{
    private const string TODAY = '2026-09-18';

    public function testDefaultsAreTheCurrentMonthThePreviousPeriodAndAllSuppliers(): void
    {
        $filter = self::filter([]);

        self::assertSame(PeriodType::Month, $filter->type);
        self::assertSame(self::TODAY, $filter->anchor->format('Y-m-d'));
        self::assertSame(Comparison::Previous, $filter->comparison);
        self::assertNull($filter->supplier);
    }

    public function testValidValuesAreRead(): void
    {
        $filter = self::filter(['p' => 'week', 'd' => '2026-02-03', 'c' => 'last_year', 's' => '7']);

        self::assertSame(PeriodType::Week, $filter->type);
        self::assertSame('2026-02-03', $filter->anchor->format('Y-m-d'));
        self::assertSame(Comparison::LastYear, $filter->comparison);
        self::assertSame(7, $filter->supplier);
    }

    /**
     * @param array<string, string> $query
     */
    #[DataProvider('invalidValues')]
    public function testInvalidValueGivesTheDefault(array $query, string $expected): void
    {
        $filter = self::filter($query);

        self::assertSame($expected, implode('|', [$filter->type->value, $filter->anchor->format('Y-m-d'), $filter->comparison->value, (string) $filter->supplier]));
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function invalidValues(): iterable
    {
        $defaults = 'month|'.self::TODAY.'|previous|';

        yield 'unknown period' => [['p' => 'quarter'], $defaults];
        yield 'date that is not a date' => [['d' => '2026-13-40'], $defaults];
        yield 'date in another format' => [['d' => '18-09-2026'], $defaults];
        yield 'date with a time' => [['d' => '2026-09-01T10:00'], $defaults];
        yield 'unknown comparison' => [['c' => 'next_year'], $defaults];
        yield 'supplier that is not a number' => [['s' => 'abc'], $defaults];
        yield 'supplier zero' => [['s' => '0'], $defaults];
        yield 'negative supplier' => [['s' => '-3'], $defaults];
        // A date after today has no data. It gives today.
        yield 'date after today' => [['d' => '2026-09-19'], $defaults];
    }

    /**
     * Today has no time, so that it compares with the dates of the periods.
     */
    public function testTodayIsADate(): void
    {
        $filter = PeriodFilter::fromQuery(null, null, null, null, new \DateTimeImmutable(self::TODAY.' 14:30'));

        self::assertSame(self::TODAY.' 00:00', $filter->today->format('Y-m-d H:i'));
        self::assertSame(self::TODAY.' 00:00', $filter->anchor->format('Y-m-d H:i'));
    }

    public function testQueryLeavesOutTheDefaults(): void
    {
        self::assertSame([], self::filter([])->query());
        self::assertSame(
            ['p' => 'year', 'd' => '2025-01-01', 'c' => 'last_year', 's' => '2'],
            self::filter(['p' => 'year', 'd' => '2025-03-04', 'c' => 'last_year', 's' => '2'])->query(),
        );
    }

    /**
     * The date in the query is the first date of the period, so that a period has one link.
     *
     * @param array<string, string> $query
     */
    #[DataProvider('firstDates')]
    public function testQueryGivesTheFirstDateOfThePeriod(array $query, string $date): void
    {
        self::assertSame($date, self::filter($query)->query()['d'] ?? null);
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function firstDates(): iterable
    {
        yield 'day' => [['p' => 'day', 'd' => '2026-09-15'], '2026-09-15'];
        yield 'week' => [['p' => 'week', 'd' => '2026-09-09'], '2026-09-07'];
        yield 'week from a Sunday' => [['p' => 'week', 'd' => '2026-09-13'], '2026-09-07'];
        yield 'month' => [['d' => '2026-08-20'], '2026-08-01'];
        yield 'year' => [['p' => 'year', 'd' => '2025-12-31'], '2025-01-01'];
    }

    /**
     * The current period has no date in the query, also when the anchor is not today.
     *
     * @param array<string, string> $query
     */
    #[DataProvider('currentPeriods')]
    public function testQueryLeavesOutTheDateOfTheCurrentPeriod(array $query, string $anchor): void
    {
        $filter = self::filter($query)->with(anchor: new \DateTimeImmutable($anchor));

        self::assertArrayNotHasKey('d', $filter->query());
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function currentPeriods(): iterable
    {
        yield 'week' => [['p' => 'week'], '2026-09-14'];
        yield 'month' => [[], '2026-09-01'];
        yield 'year' => [['p' => 'year'], '2026-01-01'];
    }

    /**
     * A link changes one filter and keeps the others.
     */
    public function testWithChangesOneFilterAndKeepsTheOthers(): void
    {
        $filter = self::filter(['p' => 'week', 'd' => '2026-02-03', 's' => '2']);

        self::assertSame(['p' => 'day', 'd' => '2026-02-03', 's' => '2'], $filter->with(type: PeriodType::Day)->query());
        self::assertSame(['p' => 'week', 'd' => '2026-01-26', 's' => '2'], $filter->with(anchor: new \DateTimeImmutable('2026-01-27'))->query());
        self::assertSame(['p' => 'week', 'd' => '2026-02-02', 'c' => 'last_year', 's' => '2'], $filter->with(comparison: Comparison::LastYear)->query());
    }

    /**
     * @param array<string, string> $query
     */
    private static function filter(array $query): PeriodFilter
    {
        return PeriodFilter::fromQuery($query['p'] ?? null, $query['d'] ?? null, $query['c'] ?? null, $query['s'] ?? null, new \DateTimeImmutable(self::TODAY));
    }
}
