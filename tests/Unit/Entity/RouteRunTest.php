<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Route;
use App\Entity\RouteRun;
use App\Entity\SubAgreement;
use App\Import\RouteRunDto;
use PHPUnit\Framework\TestCase;

/**
 * Checks that a stored route run has the values of the calculated run.
 */
final class RouteRunTest extends TestCase
{
    public function testRouteRunHasTheValuesOfTheCalculatedRun(): void
    {
        $route = new Route('R-01', (new SubAgreement())->setReference('R'));
        $serviceDate = new \DateTimeImmutable('2026-01-05');
        $values = new RouteRunDto(
            firstPickupPlanned: new \DateTimeImmutable('2026-01-05 07:00'),
            firstPickupActual: new \DateTimeImmutable('2026-01-05 07:02'),
            firstPickupDelaySeconds: 120,
            lastDropPlanned: new \DateTimeImmutable('2026-01-05 07:35'),
            lastDropActual: new \DateTimeImmutable('2026-01-05 07:39'),
            lastDropDelaySeconds: 240,
            cancelled: false,
        );

        $run = new RouteRun($route, $serviceDate, 2, $values);

        self::assertNull($run->getId());
        self::assertSame($route, $run->getRoute());
        self::assertSame($serviceDate, $run->getServiceDate());
        self::assertSame(2, $run->getRun());
        self::assertSame($values->firstPickupPlanned, $run->getFirstPickupPlanned());
        self::assertSame($values->firstPickupActual, $run->getFirstPickupActual());
        self::assertSame(120, $run->getFirstPickupDelaySeconds());
        self::assertSame($values->lastDropPlanned, $run->getLastDropPlanned());
        self::assertSame($values->lastDropActual, $run->getLastDropActual());
        self::assertSame(240, $run->getLastDropDelaySeconds());
        self::assertFalse($run->isCancelled());
    }

    public function testCancelledRunHasNoStops(): void
    {
        $run = new RouteRun(
            new Route('R-01', (new SubAgreement())->setReference('R')),
            new \DateTimeImmutable('2026-01-05'),
            1,
            new RouteRunDto(null, null, null, null, null, null, true),
        );

        self::assertNull($run->getFirstPickupPlanned());
        self::assertNull($run->getLastDropPlanned());
        self::assertTrue($run->isCancelled());
    }
}
