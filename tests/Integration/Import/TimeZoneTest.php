<?php

namespace App\Tests\Integration\Import;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks that APP_TIMEZONE gives the time zone of the wall-clock times in an extract.
 */
final class TimeZoneTest extends KernelTestCase
{
    public function testTimeZoneIsCopenhagenByDefault(): void
    {
        self::assertSame('Europe/Copenhagen', self::getContainer()->getParameter('app.timezone'));
    }
}
