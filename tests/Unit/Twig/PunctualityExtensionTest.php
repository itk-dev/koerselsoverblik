<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Twig\PunctualityExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks the formats of the punctuality figures, as in the prototype (`js/punctuality.js`).
 */
final class PunctualityExtensionTest extends TestCase
{
    /**
     * A whole percent, but a value below the target that rounds to the target shows one decimal,
     * truncated. Then a value below the target never shows as the target.
     */
    #[DataProvider('percents')]
    public function testPercent(?float $value, string $expected): void
    {
        self::assertSame($expected, self::extension()->percent($value));
    }

    /**
     * @return iterable<string, array{?float, string}>
     */
    public static function percents(): iterable
    {
        yield 'no value' => [null, '–'];
        yield 'all on time' => [100.0, '100 %'];
        yield 'none on time' => [0.0, '0 %'];
        yield 'rounds down' => [83.333, '83 %'];
        yield 'rounds up below the target' => [74.5, '75 %'];
        yield 'below the target, rounds to the target' => [89.6, '89,6 %'];
        yield 'below the target, truncated' => [89.96, '89,9 %'];
        yield 'below the target, rounds below the target' => [89.4, '89 %'];
        yield 'at the target' => [90.0, '90 %'];
        yield 'above the target' => [90.4, '90 %'];
    }

    /**
     * The change from the previous month, in percentage points. The minus sign is U+2212.
     */
    #[DataProvider('changes')]
    public function testPoints(?float $change, string $expected): void
    {
        self::assertSame($expected, self::extension()->points($change));
    }

    /**
     * @return iterable<string, array{?float, string}>
     */
    public static function changes(): iterable
    {
        yield 'no value' => [null, '–'];
        yield 'no change' => [0.0, '±0'];
        yield 'a very small rise' => [0.04, '±0'];
        yield 'a very small fall' => [-0.04, '±0'];
        yield 'a small rise' => [2.0, '+2,0'];
        yield 'a small fall' => [-1.44, '−1,4'];
        yield 'a large rise' => [12.3, '+12'];
        yield 'a large fall' => [-15.6, '−16'];
    }

    /**
     * The distance to the target, from the value that the page shows. No value and a value at or
     * above the target have no distance.
     */
    #[DataProvider('gaps')]
    public function testGap(?float $value, ?string $expected): void
    {
        self::assertSame($expected, self::extension()->gap($value));
    }

    /**
     * @return iterable<string, array{?float, ?string}>
     */
    public static function gaps(): iterable
    {
        yield 'no value' => [null, null];
        yield 'at the target' => [90.0, null];
        yield 'above the target' => [95.0, null];
        yield 'a whole distance' => [83.333, '7'];
        yield 'a decimal distance' => [89.6, '0,4'];
        yield 'from the truncated value' => [89.96, '0,1'];
    }

    #[DataProvider('tones')]
    public function testTone(?float $value, ?string $expected): void
    {
        self::assertSame($expected, self::extension()->tone($value));
    }

    /**
     * @return iterable<string, array{?float, ?string}>
     */
    public static function tones(): iterable
    {
        yield 'at the target' => [90.0, 'ok'];
        yield 'below the target' => [75.0, 'warn'];
        yield 'below the warning limit' => [50.0, 'danger'];
        yield 'no value' => [null, null];
    }

    public function testTargetComesFromTheSetting(): void
    {
        $extension = new PunctualityExtension(80);

        self::assertSame('79,6 %', $extension->percent(79.6));
        self::assertSame('ok', $extension->tone(80.0));
    }

    private static function extension(): PunctualityExtension
    {
        return new PunctualityExtension(90);
    }
}
