<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Route;
use App\Entity\SubAgreement;
use PHPUnit\Framework\TestCase;

final class RouteTest extends TestCase
{
    public function testRouteHasItsNameAndSubAgreement(): void
    {
        $subAgreement = new SubAgreement()->setReference('AB-CD');

        $route = new Route('AB-CD-01', $subAgreement);

        self::assertNull($route->getId());
        self::assertSame('AB-CD-01', $route->getSourceKey());
        self::assertSame($subAgreement, $route->getSubAgreement());
    }
}
