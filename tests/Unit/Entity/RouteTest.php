<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Route;
use App\Entity\SubAgreement;
use PHPUnit\Framework\TestCase;

final class RouteTest extends TestCase
{
    public function testRouteHasItsNameAndSubAgreement(): void
    {
        $subAgreement = (new SubAgreement())->setReference('Ø4-VB');

        $route = new Route('Ø4-VB-01', $subAgreement);

        self::assertNull($route->getId());
        self::assertSame('Ø4-VB-01', $route->getSourceKey());
        self::assertSame($subAgreement, $route->getSubAgreement());
    }
}
