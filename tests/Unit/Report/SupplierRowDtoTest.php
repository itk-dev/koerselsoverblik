<?php

declare(strict_types=1);

namespace App\Tests\Unit\Report;

use App\Report\PunctualityCountDto;
use App\Report\SoloCountDto;
use App\Report\SupplierRowDto;
use PHPUnit\Framework\TestCase;

/**
 * Checks the share of the cancelled trips in a row of the supplier table.
 */
final class SupplierRowDtoTest extends TestCase
{
    public function testCancelledShareInPercent(): void
    {
        self::assertSame(25.0, self::row(trips: 8, cancelled: 2)->cancelledPercent());
        self::assertSame(0.0, self::row(trips: 8, cancelled: 0)->cancelledPercent());
    }

    /**
     * No trips is no data, not 0 %.
     */
    public function testNoTripsHasNoShare(): void
    {
        self::assertNull(self::row(trips: 0, cancelled: 0)->cancelledPercent());
    }

    private static function row(int $trips, int $cancelled): SupplierRowDto
    {
        return new SupplierRowDto('Vognmand A', $trips, $cancelled, new SoloCountDto(0, 0), new PunctualityCountDto(0, 0));
    }
}
