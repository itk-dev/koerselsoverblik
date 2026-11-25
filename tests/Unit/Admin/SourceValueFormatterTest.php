<?php

declare(strict_types=1);

namespace App\Tests\Unit\Admin;

use App\Admin\SourceValueFormatter;
use App\Import\Extract\SourceCells;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * The display text of each type of value in the copy of a row.
 *
 * @phpstan-import-type SourceValue from SourceCells
 */
final class SourceValueFormatterTest extends TestCase
{
    /**
     * @return iterable<string, array{SourceValue, string}>
     */
    public static function texts(): iterable
    {
        yield 'text' => ['Ø4-VB-01', 'Ø4-VB-01'];
        yield 'text that looks like a number' => ['007', '007'];
        yield 'whole number' => [42, '42'];
        yield 'decimal number' => [2.5, '2.5'];
        yield 'duration with more than 24 hours' => [['duration' => 'P0DT25H10M0S'], '25:10'];
        yield 'duration with days' => [['duration' => 'P1DT2H5M0S'], '26:05'];
        yield 'duration with seconds' => [['duration' => 'P0DT0H1M30S'], '0:01:30'];
        yield 'date and time' => [['datetime' => '2026-01-05 07:30:00.000000'], '05-01-2026 07:30'];
        yield 'date and time with seconds' => [['datetime' => '2026-01-05 07:30:15.000000'], '05-01-2026 07:30:15'];
        yield 'date and time after midnight' => [['datetime' => '2026-03-29 02:30:00.000000'], '29-03-2026 02:30'];
        yield 'Excel error' => [['error' => '#N/A'], '#N/A'];
        yield 'duration that is not valid' => [['duration' => 'bad'], 'bad'];
        yield 'date and time that is not valid' => [['datetime' => 'bad'], 'bad'];
    }

    /**
     * @param SourceValue $value
     */
    #[DataProvider('texts')]
    public function testText(string|int|float|bool|array $value, string $text): void
    {
        self::assertSame($text, SourceValueFormatter::format($value));
    }

    public function testBooleanIsATranslatedYesOrNo(): void
    {
        $yes = SourceValueFormatter::format(true);
        $no = SourceValueFormatter::format(false);

        self::assertInstanceOf(TranslatableMessage::class, $yes);
        self::assertSame('common.yes', $yes->getMessage());
        self::assertInstanceOf(TranslatableMessage::class, $no);
        self::assertSame('common.no', $no->getMessage());
    }
}
