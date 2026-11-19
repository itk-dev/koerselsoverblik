<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\SourceRow;
use PHPUnit\Framework\TestCase;

/**
 * Checks that the copy of a row keeps the values of the extract.
 */
final class SourceRowTest extends TestCase
{
    public function testCopyKeepsTheValues(): void
    {
        $values = ['RouteName' => 'R-01', 'PlannedDeparture' => ['datetime' => '2026-01-05 07:10:00'], 'Run' => 2];

        $sourceRow = new SourceRow($values);

        self::assertNull($sourceRow->getId());
        self::assertSame($values, $sourceRow->getValues());
    }
}
