<?php

declare(strict_types=1);

namespace App\Tests\Integration\Import;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks that APP_DRIVING_DATA_MAX_DAYS gives the maximum number of days in an upload.
 */
final class MaximumRangeTest extends KernelTestCase
{
    public function testMaximumIs31DaysByDefault(): void
    {
        self::assertSame(31, self::getContainer()->getParameter('app.driving_data_max_days'));
    }
}
