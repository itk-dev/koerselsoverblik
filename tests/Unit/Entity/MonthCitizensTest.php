<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\MonthCitizens;
use App\Entity\Supplier;
use PHPUnit\Framework\TestCase;

final class MonthCitizensTest extends TestCase
{
    public function testRowHasItsMonthSupplierAndCitizens(): void
    {
        $firstDate = new \DateTimeImmutable('2026-01-01');
        $supplier = new Supplier()->setName('Vognmand A');

        $row = new MonthCitizens($firstDate, $supplier, 12);

        self::assertNull($row->getId());
        self::assertSame($firstDate, $row->getFirstDate());
        self::assertSame($supplier, $row->getSupplier());
        self::assertSame(12, $row->getCitizens());
    }

    public function testRowOfAllSuppliersHasNoSupplier(): void
    {
        self::assertNull(new MonthCitizens(new \DateTimeImmutable('2026-01-01'), null, 12)->getSupplier());
    }
}
