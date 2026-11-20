<?php

declare(strict_types=1);

namespace App\Tests\Unit\Admin;

use App\Admin\RowsQueryDto;
use PHPUnit\Framework\TestCase;

/**
 * The query of the range form on the driving data page.
 */
final class RowsQueryDtoTest extends TestCase
{
    public function testForDateHasTheDateAsTheFirstAndTheLastDate(): void
    {
        $query = RowsQueryDto::forDate(new \DateTimeImmutable('2026-01-05'));

        self::assertSame('2026-01-05', $query->firstDate?->format('Y-m-d'));
        self::assertSame('2026-01-05', $query->lastDate?->format('Y-m-d'));
        self::assertNull($query->route);
    }

    public function testRangeOfAValidQuery(): void
    {
        $query = RowsQueryDto::forDate(new \DateTimeImmutable('2026-01-05'));
        $query->lastDate = new \DateTimeImmutable('2026-01-07');

        $range = $query->range(31);

        self::assertSame('2026-01-05', $range->firstDate->format('Y-m-d'));
        self::assertSame('2026-01-07', $range->lastDate->format('Y-m-d'));
    }

    public function testRangeWithNoDateIsAnError(): void
    {
        $query = new RowsQueryDto();

        $this->expectException(\LogicException::class);
        $query->range(31);
    }
}
