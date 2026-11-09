<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Import\ImportRangeDto;
use App\Import\InvalidImportRangeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks the rules for the date range of an upload. See "Date range" in docs/architecture.md.
 */
final class ImportRangeDtoTest extends TestCase
{
    /**
     * The dates are calendar dates at 00:00 in the PHP default time zone. The time zone of the
     * input does not change them.
     */
    public function testRangeHasTheDatesWithoutTime(): void
    {
        $utc = new \DateTimeZone('UTC');
        $range = ImportRangeDto::create(new \DateTime('2026-01-05 23:30', $utc), new \DateTimeImmutable('2026-01-07 08:00', $utc), 31);

        self::assertSame('2026-01-05 00:00:00 Europe/Copenhagen', $range->firstDate->format('Y-m-d H:i:s e'));
        self::assertSame('2026-01-07 00:00:00 Europe/Copenhagen', $range->lastDate->format('Y-m-d H:i:s e'));
    }

    /**
     * The clocks go forward on 29 March 2026. That day has 23 hours, but it is one day.
     */
    public function testRangeAcrossTheChangeToSummerTimeCountsTheDays(): void
    {
        $range = ImportRangeDto::create(new \DateTimeImmutable('2026-03-28'), new \DateTimeImmutable('2026-03-30'), 31);

        self::assertSame(3, $range->days());
    }

    /**
     * Each date of the range occurs one time, also on the days of the changes to and from summer
     * time.
     */
    public function testDatesAcrossTheChangesOfTime(): void
    {
        $spring = ImportRangeDto::create(new \DateTimeImmutable('2026-03-28'), new \DateTimeImmutable('2026-03-30'), 31);
        $autumn = ImportRangeDto::create(new \DateTimeImmutable('2026-10-24'), new \DateTimeImmutable('2026-10-26'), 31);

        self::assertSame(['2026-03-28', '2026-03-29', '2026-03-30'], $spring->dates());
        self::assertSame(['2026-10-24', '2026-10-25', '2026-10-26'], $autumn->dates());
    }

    public function testRangeOfOneDayHasOneDate(): void
    {
        $range = ImportRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-05'), 31);

        self::assertSame(['2026-01-05'], $range->dates());
    }

    public function testRangeOfOneDayIsAccepted(): void
    {
        $range = ImportRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-05'), 31);

        self::assertSame(1, $range->days());
    }

    public function testRangeOfTheMaximumIsAccepted(): void
    {
        $range = ImportRangeDto::create(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-01-31'), 31);

        self::assertSame(31, $range->days());
    }

    public function testRangeOfOneDayMoreThanTheMaximumIsRejected(): void
    {
        $this->expectException(InvalidImportRangeException::class);
        $this->expectExceptionMessageIsOrContains('The range has more than 31 days.');

        ImportRangeDto::create(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-02-01'), 31);
    }

    public function testLastDateBeforeTheFirstDateIsRejected(): void
    {
        $this->expectException(InvalidImportRangeException::class);
        $this->expectExceptionMessageIsOrContains('The last date is before the first date.');

        ImportRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-04'), 31);
    }

    #[DataProvider('ranges')]
    public function testNumberOfDays(string $first, string $last, int $days): void
    {
        $range = ImportRangeDto::create(new \DateTimeImmutable($first), new \DateTimeImmutable($last), 400);

        self::assertSame($days, $range->days());
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function ranges(): iterable
    {
        yield 'two days' => ['2026-01-05', '2026-01-06', 2];
        yield 'across a month' => ['2026-01-30', '2026-02-02', 4];
        yield 'across a year' => ['2025-12-30', '2026-01-02', 4];
        yield 'across the change to summer time' => ['2026-03-28', '2026-03-30', 3];
        yield 'a leap year' => ['2028-02-28', '2028-03-01', 3];
    }

    #[DataProvider('dates')]
    public function testContains(string $date, bool $contains): void
    {
        $range = ImportRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-07'), 31);

        self::assertSame($contains, $range->contains(new \DateTimeImmutable($date)));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function dates(): iterable
    {
        yield 'the day before' => ['2026-01-04 23:59', false];
        yield 'the first date' => ['2026-01-05 00:00', true];
        yield 'a date in the range' => ['2026-01-06 12:00', true];
        yield 'the last date, late' => ['2026-01-07 23:59', true];
        yield 'the day after' => ['2026-01-08 00:00', false];
    }
}
