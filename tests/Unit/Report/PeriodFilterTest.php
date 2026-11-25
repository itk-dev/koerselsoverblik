<?php

declare(strict_types=1);

namespace App\Tests\Unit\Report;

use App\Report\Comparison;
use App\Report\ConclusionMeasure;
use App\Report\ConclusionThreshold;
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
     * The ids start at 1.
     */
    public function testLowestSupplierIdIsRead(): void
    {
        self::assertSame(1, self::filter(['s' => '1'])->supplier);
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
            ['p' => 'half_year', 'd' => '2025-01-01', 'c' => 'last_year', 's' => '2'],
            self::filter(['p' => 'half_year', 'd' => '2025-03-04', 'c' => 'last_year', 's' => '2'])->query(),
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
        yield 'week' => [['p' => 'week', 'd' => '2026-09-09'], '2026-09-07'];
        yield 'week from a Sunday' => [['p' => 'week', 'd' => '2026-09-13'], '2026-09-07'];
        yield 'month' => [['d' => '2026-08-20'], '2026-08-01'];
        yield 'quarter' => [['p' => 'quarter', 'd' => '2026-06-30'], '2026-04-01'];
        yield 'first quarter' => [['p' => 'quarter', 'd' => '2026-03-31'], '2026-01-01'];
        yield 'half year' => [['p' => 'half_year', 'd' => '2025-12-31'], '2025-07-01'];
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
        yield 'quarter' => [['p' => 'quarter'], '2026-07-01'];
        yield 'half year' => [['p' => 'half_year'], '2026-07-01'];
    }

    /**
     * A link changes one filter and keeps the others.
     */
    public function testWithChangesOneFilterAndKeepsTheOthers(): void
    {
        $filter = self::filter(['p' => 'week', 'd' => '2026-02-03', 's' => '2']);

        self::assertSame(['p' => 'quarter', 'd' => '2026-01-01', 's' => '2'], $filter->with(type: PeriodType::Quarter)->query());
        self::assertSame(['p' => 'week', 'd' => '2026-01-26', 's' => '2'], $filter->with(anchor: new \DateTimeImmutable('2026-01-27'))->query());
        self::assertSame(['p' => 'week', 'd' => '2026-02-02', 'c' => 'last_year', 's' => '2'], $filter->with(comparison: Comparison::LastYear)->query());
    }

    /**
     * A query is canonical when it is the query of its filter. The order of the keys has no effect.
     *
     * @param array<string, mixed> $query
     */
    #[DataProvider('canonicalQueries')]
    public function testIsCanonical(array $query, bool $canonical): void
    {
        $filter = PeriodFilter::fromQuery(
            self::value($query, 'p'),
            self::value($query, 'd'),
            self::value($query, 'c'),
            self::value($query, 's'),
            new \DateTimeImmutable(self::TODAY),
            self::measuresOf($query),
            self::value($query, 't'),
        );

        self::assertSame($canonical, $filter->isCanonical($query));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, bool}>
     */
    public static function canonicalQueries(): iterable
    {
        yield 'no query' => [[], true];
        yield 'all filters' => [['p' => 'week', 'd' => '2026-02-02', 'c' => 'last_year', 's' => '2'], true];
        yield 'other order' => [['s' => '2', 'd' => '2026-02-02', 'p' => 'week'], true];
        // The form always sends the comparison and the supplier.
        yield 'defaults from the form' => [['c' => 'previous', 's' => ''], false];
        yield 'date that is not the first date' => [['p' => 'week', 'd' => '2026-02-03'], false];
        yield 'date of the current period' => [['d' => '2026-09-01'], false];
        yield 'invalid date' => [['d' => 'abc'], false];
        yield 'date as a list' => [['d' => ['2026-02-02']], false];
        yield 'unknown parameter' => [['month' => '2026-06'], false];
        yield 'conclusion settings' => [['m' => 'punctuality,solo', 't' => '0'], true];
        yield 'measures in another order' => [['m' => 'solo,punctuality'], false];
        // The checkboxes of the form send a list. The page redirects it to the list with commas.
        yield 'measures from the form' => [['m' => ['solo']], false];
        yield 'default measure' => [['m' => 'punctuality'], false];
        yield 'default threshold' => [['t' => '1'], false];
    }

    public function testWithoutSupplierKeepsTheOtherFilters(): void
    {
        $filter = self::filter(['p' => 'week', 'd' => '2026-02-03', 'c' => 'last_year', 's' => '2', 'm' => 'solo', 't' => '0']);

        self::assertSame(['p' => 'week', 'd' => '2026-02-02', 'c' => 'last_year', 'm' => 'solo', 't' => '0'], $filter->withoutSupplier()->query());
    }

    /**
     * The conclusion of the overview uses the punctuality and a threshold of 1 %.
     */
    public function testConclusionDefaults(): void
    {
        $filter = self::filter([]);

        self::assertSame([ConclusionMeasure::Punctuality], $filter->measures);
        self::assertSame(ConclusionThreshold::One, $filter->threshold);
    }

    /**
     * The measures are always in the order of the enum, and each measure is in the list one time.
     *
     * @param list<ConclusionMeasure> $expected
     */
    #[DataProvider('measures')]
    public function testConclusionMeasures(mixed $measures, array $expected): void
    {
        self::assertSame($expected, self::filter(['m' => $measures])->measures);
    }

    /**
     * @return iterable<string, array{mixed, list<ConclusionMeasure>}>
     */
    public static function measures(): iterable
    {
        // The controller gives the value of the query with no check of its type.
        yield 'not a text or a list' => [5, [ConclusionMeasure::Punctuality]];
        yield 'one' => ['solo', [ConclusionMeasure::RealSolo]];
        yield 'all' => ['punctuality,solo,cancelled', [ConclusionMeasure::Punctuality, ConclusionMeasure::RealSolo, ConclusionMeasure::Cancelled]];
        yield 'other order' => ['cancelled,punctuality', [ConclusionMeasure::Punctuality, ConclusionMeasure::Cancelled]];
        yield 'twice' => ['solo,solo', [ConclusionMeasure::RealSolo]];
        // The checkboxes of the form send a list.
        yield 'list of the form' => [['cancelled', 'solo'], [ConclusionMeasure::RealSolo, ConclusionMeasure::Cancelled]];
        yield 'unknown measure' => ['solo,complaints', [ConclusionMeasure::RealSolo]];
        yield 'value in a list that is not text' => [['solo', ['cancelled']], [ConclusionMeasure::RealSolo]];
        // At least one measure must be in the conclusion.
        yield 'only unknown measures' => ['complaints', [ConclusionMeasure::Punctuality]];
        yield 'empty' => ['', [ConclusionMeasure::Punctuality]];
        yield 'empty list' => [[], [ConclusionMeasure::Punctuality]];
    }

    #[DataProvider('thresholds')]
    public function testConclusionThreshold(string $threshold, ConclusionThreshold $expected): void
    {
        self::assertSame($expected, self::filter(['t' => $threshold])->threshold);
    }

    /**
     * @return iterable<string, array{string, ConclusionThreshold}>
     */
    public static function thresholds(): iterable
    {
        yield 'any change' => ['0', ConclusionThreshold::Any];
        yield 'half' => ['0.5', ConclusionThreshold::Half];
        yield 'five' => ['5', ConclusionThreshold::Five];
        yield 'not an option' => ['3', ConclusionThreshold::One];
        yield 'not a number' => ['abc', ConclusionThreshold::One];
        yield 'other format' => ['0,5', ConclusionThreshold::One];
    }

    /**
     * The query has the measures as a list with commas, and leaves out the defaults.
     */
    public function testQueryHasTheConclusionSettings(): void
    {
        self::assertSame(['m' => 'punctuality,cancelled', 't' => '0.5'], self::filter(['m' => ['cancelled', 'punctuality'], 't' => '0.5'])->query());
        self::assertSame([], self::filter(['m' => 'punctuality', 't' => '1'])->query());
    }

    /**
     * A link of the filter bar keeps the settings of the conclusion.
     */
    public function testWithKeepsTheConclusionSettings(): void
    {
        $filter = self::filter(['m' => 'solo', 't' => '2']);

        self::assertSame(['p' => 'week', 'm' => 'solo', 't' => '2'], $filter->with(type: PeriodType::Week)->query());
    }

    /**
     * Gives a value of the query as the controller does: a value that is not text gives null.
     *
     * @param array<string, mixed> $query
     */
    private static function value(array $query, string $name): ?string
    {
        return \is_string($query[$name] ?? null) ? $query[$name] : null;
    }

    /**
     * Gives the measures as the controller does: the value of the query, of any type.
     *
     * @param array<string, mixed> $query
     */
    private static function measuresOf(array $query): mixed
    {
        return $query['m'] ?? null;
    }

    /**
     * @param array<string, mixed> $query
     */
    private static function filter(array $query): PeriodFilter
    {
        return PeriodFilter::fromQuery(self::value($query, 'p'), self::value($query, 'd'), self::value($query, 'c'), self::value($query, 's'), new \DateTimeImmutable(self::TODAY), self::measuresOf($query), self::value($query, 't'));
    }
}
