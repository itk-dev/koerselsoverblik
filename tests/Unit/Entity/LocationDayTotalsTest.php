<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\LocationDayTotals;
use App\Entity\Route;
use App\Entity\SubAgreement;
use App\Report\PunctualityCountDto;
use PHPUnit\Framework\TestCase;

final class LocationDayTotalsTest extends TestCase
{
    public function testRowHasItsRouteDateLocationToleranceAndStops(): void
    {
        $route = new Route('AB-CD-01', new SubAgreement()->setReference('AB-CD'));
        $serviceDate = new \DateTimeImmutable('2026-01-05');

        $row = new LocationDayTotals($route, $serviceDate, 'Skole 1', 5, new PunctualityCountDto(2, 3));

        self::assertNull($row->getId());
        self::assertSame($route, $row->getRoute());
        self::assertSame($serviceDate, $row->getServiceDate());
        self::assertSame('Skole 1', $row->getLocationName());
        self::assertSame(5, $row->getToleranceMinutes());
        self::assertEquals(new PunctualityCountDto(2, 3), $row->getStops());
    }
}
