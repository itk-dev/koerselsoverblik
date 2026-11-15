<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Route;
use App\Entity\RouteDayTotals;
use App\Entity\SubAgreement;
use App\Report\DayTotalsDto;
use PHPUnit\Framework\TestCase;

final class RouteDayTotalsTest extends TestCase
{
    public function testRowHasItsRouteDateToleranceAndTotals(): void
    {
        $route = new Route('AB-CD-01', new SubAgreement()->setReference('AB-CD'));
        $serviceDate = new \DateTimeImmutable('2026-01-05');
        // Each counter has its own value, so that a counter in the wrong column fails the test.
        $totals = new DayTotalsDto(...range(1, 17));

        $row = new RouteDayTotals($route, $serviceDate, 5, $totals);

        self::assertNull($row->getId());
        self::assertSame($route, $row->getRoute());
        self::assertSame($serviceDate, $row->getServiceDate());
        self::assertSame(5, $row->getToleranceMinutes());
        self::assertEquals($totals, $row->getTotals());
    }
}
