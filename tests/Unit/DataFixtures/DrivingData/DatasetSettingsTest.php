<?php

declare(strict_types=1);

namespace App\Tests\Unit\DataFixtures\DrivingData;

use App\DataFixtures\DrivingData\DatasetSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks the punctuality slider of the development dataset.
 */
final class DatasetSettingsTest extends TestCase
{
    #[DataProvider('punctuality')]
    public function testPunctualityOfADay(float $start, float $trend, string $day, float $expected): void
    {
        $settings = new DatasetSettings(1, new \DateTimeImmutable('2025-11-01'), $start, $trend);

        self::assertSame($expected, $settings->punctuality(new \DateTimeImmutable($day)));
    }

    /**
     * @return iterable<string, array{float, float, string, float}>
     */
    public static function punctuality(): iterable
    {
        yield 'first day of the first month' => [65.0, 2.5, '2025-11-01', 65.0];
        yield 'last day of the first month' => [65.0, 2.5, '2025-11-30', 65.0];
        yield 'second month' => [65.0, 2.5, '2025-12-15', 67.5];
        yield 'month in the next year' => [65.0, 2.5, '2026-11-11', 95.0];
        yield 'negative trend' => [80.0, -3.0, '2026-01-31', 74.0];
        yield 'not more than 100' => [95.0, 2.0, '2026-11-01', 100.0];
        yield 'not less than 0' => [5.0, -2.0, '2026-11-01', 0.0];
        yield 'day before the first month' => [65.0, 2.5, '2025-10-31', 65.0];
    }
}
