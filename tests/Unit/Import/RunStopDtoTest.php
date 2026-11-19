<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Import\Mapping\StopStatus;
use App\Import\Mapping\StopType;
use App\Import\RunStopDto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks the rule for a measured stop: the status is completed, and the stop has an actual time.
 */
final class RunStopDtoTest extends TestCase
{
    #[DataProvider('stops')]
    public function testMeasuredStop(StopStatus $status, ?string $actualTime, bool $expected): void
    {
        $stop = new RunStopDto(
            StopType::Pickup,
            $status,
            new \DateTimeImmutable('2026-01-05 07:00'),
            null === $actualTime ? null : new \DateTimeImmutable($actualTime),
            null === $actualTime ? null : 60,
        );

        self::assertSame($expected, $stop->isMeasured());
    }

    /**
     * @return iterable<string, array{StopStatus, ?string, bool}>
     */
    public static function stops(): iterable
    {
        yield 'completed, with an actual time' => [StopStatus::Completed, '2026-01-05 07:01', true];
        yield 'completed, with no actual time' => [StopStatus::Completed, null, false];
        yield 'pending, with an actual time' => [StopStatus::Pending, '2026-01-05 07:01', false];
        yield 'cancelled, with an actual time' => [StopStatus::Cancelled, '2026-01-05 07:01', false];
    }
}
