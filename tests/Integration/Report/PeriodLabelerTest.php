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
 * Checks the Danish labels of the periods, as in the prototype. Today is Friday 18 September 2026.
 */
final class PeriodLabelerTest extends KernelTestCase
{
    private const string TODAY = '2026-09-18';

    /**
     * @param array<string, string> $query
     */
    #[DataProvider('labels')]
    public function testLabelOfThePeriod(array $query, string $label, string $phrase, string $comparison): void
    {
        $selection = self::select($query);
        $labeler = $this->labeler();

        self::assertSame($label, $labeler->label($selection->selected));
        self::assertSame($phrase, $labeler->phrase($selection->selected));
        self::assertSame($comparison, $labeler->comparisonPhrase($selection));
    }

    /**
     * @return iterable<string, array{array<string, string>, string, string, string}>
     */
    public static function labels(): iterable
    {
        yield 'day' => [['p' => 'day', 'd' => '2026-09-15'], 'tir. 15. sep 2026', 'tir. 15. sep 2026', 'man. 14. sep 2026'];
        yield 'Sunday' => [['p' => 'day', 'd' => '2026-09-13'], 'søn. 13. sep 2026', 'søn. 13. sep 2026', 'lør. 12. sep 2026'];
        yield 'week' => [['p' => 'week', 'd' => '2026-09-09'], 'Uge 37 · 7. sep–13. sep 2026', 'uge 37 · 7. sep–13. sep 2026', 'uge 36 · 31. aug–6. sep 2026'];
        yield 'current week' => [['p' => 'week', 'd' => '2026-09-16'], 'Uge 38 · 14. sep–20. sep 2026', '14.–18. september 2026 (til og med i dag)', '7.–11. september 2026'];
        yield 'month' => [['d' => '2026-08-20'], 'august 2026', 'august 2026', 'juli 2026'];
        yield 'current month' => [[], 'september 2026', '1.–18. september 2026 (til og med i dag)', '1.–18. august 2026'];
        yield 'same month last year' => [['c' => 'last_year'], 'september 2026', '1.–18. september 2026 (til og med i dag)', '1.–18. september 2025'];
        yield 'year' => [['p' => 'year', 'd' => '2025-05-05'], '2025', '2025', '2024'];
        yield 'current year' => [['p' => 'year'], '2026', '1. jan–18. sep 2026 (til og med i dag)', '1. jan–18. sep 2025'];
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

        self::assertSame('14. september 2026 (til og med i dag)', $this->labeler()->phrase($selection->selected));
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
    private static function select(array $query, string $today = self::TODAY): PeriodSelectionDto
    {
        $date = new \DateTimeImmutable($today);

        return new PeriodRangeFactory()->create(PeriodFilter::fromQuery($query['p'] ?? null, $query['d'] ?? null, $query['c'] ?? null, null, $date), $date);
    }
}
