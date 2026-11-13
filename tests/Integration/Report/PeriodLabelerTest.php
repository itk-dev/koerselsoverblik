<?php

declare(strict_types=1);

namespace App\Tests\Integration\Report;

use App\Report\PeriodFilter;
use App\Report\PeriodLabeler;
use App\Report\PeriodRangeFactory;
use App\Report\PeriodSelectionDto;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Checks the Danish labels of the periods, in the style of the prototype. Today is Friday
 * 18 September 2026. The tests with a data start use 18 May 2026.
 */
final class PeriodLabelerTest extends KernelTestCase
{
    private const string TODAY = '2026-09-18';

    private const string DATA_START = '2026-05-18';

    /**
     * @param array<string, string> $query
     */
    #[DataProvider('labels')]
    public function testLabelOfThePeriod(array $query, string $label, string $phrase, string $comparison, string $start = ''): void
    {
        $selection = self::select($query, self::TODAY, $start);
        $labeler = $this->labeler();

        self::assertSame($label, $labeler->label($selection->selected));
        self::assertSame($phrase, $labeler->phrase($selection));
        self::assertSame($comparison, $labeler->comparisonPhrase($selection));
    }

    /**
     * @return iterable<string, array{0: array<string, string>, 1: string, 2: string, 3: string, 4?: string}>
     */
    public static function labels(): iterable
    {
        yield 'week' => [['p' => 'week', 'd' => '2026-09-09'], 'Uge 37 · 7. sep–13. sep 2026', 'uge 37 · 7. sep–13. sep 2026', 'uge 36 · 31. aug–6. sep 2026'];
        yield 'current week' => [['p' => 'week', 'd' => '2026-09-16'], 'Uge 38 · 14. sep–20. sep 2026', '14.–18. september 2026 (til og med i dag)', '7.–11. september 2026'];
        yield 'month' => [['d' => '2026-08-20'], 'august 2026', 'august 2026', 'juli 2026'];
        yield 'current month' => [[], 'september 2026', '1.–18. september 2026 (til og med i dag)', '1.–18. august 2026'];
        yield 'same month last year' => [['c' => 'last_year'], 'september 2026', '1.–18. september 2026 (til og med i dag)', '1.–18. september 2025'];
        yield 'quarter' => [['p' => 'quarter', 'd' => '2026-05-05'], '2. kvartal 2026', '2. kvartal 2026', '1. kvartal 2026'];
        yield 'current quarter' => [['p' => 'quarter'], '3. kvartal 2026', '1. jul–18. sep 2026 (til og med i dag)', '1. apr–19. jun 2026'];
        yield 'last quarter' => [['p' => 'quarter', 'd' => '2025-12-01'], '4. kvartal 2025', '4. kvartal 2025', '3. kvartal 2025'];
        yield 'half year' => [['p' => 'half_year', 'd' => '2026-03-03'], '1. halvår 2026', '1. halvår 2026', '2. halvår 2025'];
        yield 'month from the start of the data' => [['d' => '2026-05-25'], 'maj 2026', '18.–31. maj 2026 (delvis periode)', 'en periode uden data', self::DATA_START];
        yield 'month after the start of the data' => [['d' => '2026-06-10'], 'juni 2026', 'juni 2026', 'maj 2026 (delvis periode)', self::DATA_START];
        yield 'last year before the data' => [['c' => 'last_year'], 'september 2026', '1.–18. september 2026 (til og med i dag)', 'en periode uden data', self::DATA_START];
    }

    public function testWeekAcrossAYearHasTheYearOfTheLastDay(): void
    {
        $selection = self::select(['p' => 'week', 'd' => '2026-12-30'], '2027-06-01');

        self::assertSame('Uge 53 · 28. dec–3. jan 2027', $this->labeler()->label($selection->selected));
    }

    /**
     * On a Monday, the current week has one day.
     */
    public function testPeriodOfOneDayUntilToday(): void
    {
        $selection = self::select(['p' => 'week'], '2026-09-14');

        self::assertSame('14. september 2026 (til og med i dag)', $this->labeler()->phrase($selection));
    }

    /**
     * On 10 June, the comparison is 1–10 May, which is before the start of the data.
     */
    public function testCutComparisonBeforeTheDataHasNoData(): void
    {
        $selection = self::select([], '2026-06-10', self::DATA_START);

        self::assertSame('en periode uden data', $this->labeler()->comparisonPhrase($selection));
    }

    private function labeler(): PeriodLabeler
    {
        $translator = self::getContainer()->get(TranslatorInterface::class);
        self::assertInstanceOf(TranslatorInterface::class, $translator);

        return new PeriodLabeler($translator);
    }

    /**
     * @param array<string, string> $query
     */
    private static function select(array $query, string $today = self::TODAY, string $start = ''): PeriodSelectionDto
    {
        return new PeriodRangeFactory($start)->create(PeriodFilter::fromQuery($query['p'] ?? null, $query['d'] ?? null, $query['c'] ?? null, null, new \DateTimeImmutable($today)));
    }
}
