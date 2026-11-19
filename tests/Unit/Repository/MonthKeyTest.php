<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Repository\MonthKey;
use PHPUnit\Framework\TestCase;

/**
 * Checks the DQL expression of the month of a date. The integration tests of the queries for each
 * month check that MariaDB gives the month as YYYY-MM.
 */
final class MonthKeyTest extends TestCase
{
    public function testExpressionTakesTheYearAndTheMonthOfTheDate(): void
    {
        self::assertSame('SUBSTRING(row.serviceDate, 1, 7)', MonthKey::of('row.serviceDate'));
    }
}
