<?php

declare(strict_types=1);

namespace App\Tests\Integration\Import\Extract;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks that the APP_DRIVING_DATA_MAX_* variables give the limits for one extract.
 *
 * The reference extract has one day of 395 rows and 62 columns, 126 KB, and 722 KB when it is not
 * compressed. The defaults for the file size and the rows are about four times the values of 31
 * such days. The default for the uncompressed size is about two times, because the inspector
 * reads all the XML files, and this size thus controls the time of the check.
 */
final class ExtractLimitsTest extends KernelTestCase
{
    public function testDefaultLimits(): void
    {
        $container = self::getContainer();

        self::assertSame(20_971_520, $container->getParameter('app.driving_data_max_file_size'));
        self::assertSame(52_428_800, $container->getParameter('app.driving_data_max_uncompressed_size'));
        self::assertSame(50_000, $container->getParameter('app.driving_data_max_rows'));
        self::assertSame(200, $container->getParameter('app.driving_data_max_columns'));
    }
}
