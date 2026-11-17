<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clock;

use App\Clock\IsoDate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks the read of a date as YYYY-MM-DD, which the commands and the clear of a date use.
 */
final class IsoDateTest extends TestCase
{
    public function testValidDateGivesMidnightOfTheDate(): void
    {
        self::assertEquals(new \DateTimeImmutable('2026-01-05 00:00:00'), IsoDate::parse('2026-01-05'));
    }

    #[DataProvider('invalidDates')]
    public function testDateThatIsNotValidGivesNull(string $value): void
    {
        self::assertNull(IsoDate::parse($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDates(): iterable
    {
        yield 'a date that does not exist' => ['2026-02-30'];
        yield 'no leading zero' => ['2026-1-5'];
        yield 'another format' => ['05-01-2026'];
        yield 'a time after the date' => ['2026-01-05 10:00'];
        yield 'text' => ['foo'];
        yield 'empty' => [''];
    }
}
