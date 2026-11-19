<?php

declare(strict_types=1);

namespace App\Tests\Unit\Report;

use App\Report\SoloCountDto;
use PHPUnit\Framework\TestCase;

/**
 * Checks the share of the real solo runs.
 */
final class SoloCountDtoTest extends TestCase
{
    public function testShareInPercent(): void
    {
        self::assertSame(50.0, new SoloCountDto(4, 2)->percent());
        self::assertSame(100.0, new SoloCountDto(3, 3)->percent());
        self::assertSame(0.0, new SoloCountDto(3, 0)->percent());
    }

    /**
     * No runs is no data, not 0 %.
     */
    public function testNoRunsHasNoShare(): void
    {
        self::assertNull(new SoloCountDto(0, 0)->percent());
    }
}
