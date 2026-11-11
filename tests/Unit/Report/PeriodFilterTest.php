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

        // The prototype has a day and a year. The application does not.
        yield 'day' => [['p' => 'day'], $defaults];
        yield 'year' => [['p' => 'year'], $defaults];
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

    public function testQueryLeavesOutTheDefaults(): void
    {
        self::assertSame([], self::filter([])->query());
        self::assertSame(
            ['p' => 'half_year', 'd' => '2025-03-04', 'c' => 'last_year', 's' => '2'],
            self::filter(['p' => 'half_year', 'd' => '2025-03-04', 'c' => 'last_year', 's' => '2'])->query(),
        );
    }

    /**
     * A link changes one filter and keeps the others.
     */
    public function testWithChangesOneFilterAndKeepsTheOthers(): void
    {
        $filter = self::filter(['p' => 'week', 'd' => '2026-02-03', 's' => '2']);

        self::assertSame(['p' => 'quarter', 'd' => '2026-02-03', 's' => '2'], $filter->with(type: PeriodType::Quarter)->query());
        self::assertSame(['p' => 'week', 'd' => '2026-01-27', 's' => '2'], $filter->with(anchor: new \DateTimeImmutable('2026-01-27'))->query());
        self::assertSame(['p' => 'week', 'd' => '2026-02-03', 'c' => 'last_year', 's' => '2'], $filter->with(comparison: Comparison::LastYear)->query());
    }

    /**
     * @param array<string, string> $query
     */
    private static function filter(array $query): PeriodFilter
    {
        return PeriodFilter::fromQuery($query['p'] ?? null, $query['d'] ?? null, $query['c'] ?? null, $query['s'] ?? null, new \DateTimeImmutable(self::TODAY));
    }
}
