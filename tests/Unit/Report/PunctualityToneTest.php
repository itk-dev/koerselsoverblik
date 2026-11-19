<?php

declare(strict_types=1);

namespace App\Tests\Unit\Report;

use App\Report\PunctualityTone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks the tone of a punctuality measure: ok at or above the target, warn at or above 70 %,
 * danger below.
 */
final class PunctualityToneTest extends TestCase
{
    #[DataProvider('percents')]
    public function testToneOfAPercent(?float $percent, ?PunctualityTone $expected): void
    {
        self::assertSame($expected, PunctualityTone::fromPercent($percent, 90));
    }

    /**
     * @return iterable<string, array{?float, ?PunctualityTone}>
     */
    public static function percents(): iterable
    {
        yield 'above the target' => [95.0, PunctualityTone::Ok];
        yield 'at the target' => [90.0, PunctualityTone::Ok];
        yield 'just below the target' => [89.9, PunctualityTone::Warn];
        yield 'at the warning limit' => [70.0, PunctualityTone::Warn];
        yield 'just below the warning limit' => [69.9, PunctualityTone::Danger];
        yield 'no value' => [null, null];
    }
}
