<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks that the kernel sets UTC as the PHP default time zone, also when PHP_TIMEZONE in the
 * container is another time zone.
 */
final class KernelTimeZoneTest extends KernelTestCase
{
    private string $defaultTimeZone;

    protected function setUp(): void
    {
        $this->defaultTimeZone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        date_default_timezone_set($this->defaultTimeZone);
    }

    public function testTheKernelSetsUtc(): void
    {
        date_default_timezone_set('Europe/Copenhagen');

        self::bootKernel();

        self::assertSame('UTC', date_default_timezone_get());
    }
}
