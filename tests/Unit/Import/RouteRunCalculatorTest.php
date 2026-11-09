<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Import\Mapping\StopStatus;
use App\Import\Mapping\StopType;
use App\Import\RouteRunCalculator;
use App\Import\RouteRunDto;
use App\Import\RunStopDto;
use PHPUnit\Framework\TestCase;

/**
 * Checks the first pickup and the last drop-off of one route run. The punctuality page measures
 * them. See "Build route runs" in "Pipeline" in docs/architecture.md.
 */
final class RouteRunCalculatorTest extends TestCase
{
    /**
     * Run 1 of R-01 in the base scenario.
     */
    public function testRunGivesTheFirstPickupAndTheLastDropOff(): void
    {
        $run = self::calculate([
            self::stop(StopType::Pickup, StopStatus::Completed, '07:00', '07:02'),
            self::stop(StopType::Pickup, StopStatus::Completed, '07:10', '07:12'),
            self::stop(StopType::DropOff, StopStatus::Completed, '07:25', '07:27'),
            self::stop(StopType::DropOff, StopStatus::Completed, '07:35', '07:39'),
        ]);

        self::assertTime('2026-01-05 07:00', $run->firstPickupPlanned);
        self::assertTime('2026-01-05 07:02', $run->firstPickupActual);
        self::assertSame(120, $run->firstPickupDelaySeconds);
        self::assertTime('2026-01-05 07:35', $run->lastDropPlanned);
        self::assertTime('2026-01-05 07:39', $run->lastDropActual);
        self::assertSame(240, $run->lastDropDelaySeconds);
        self::assertFalse($run->cancelled);
    }

    /**
     * R-02 in the base scenario ends after midnight. The cancelled stops and the pickup with no
     * registration do not change the first pickup and the last drop-off.
     */
    public function testRunAfterMidnight(): void
    {
        $run = self::calculate([
            self::stop(StopType::Pickup, StopStatus::Completed, '23:40', '23:41'),
            self::stop(StopType::Pickup, StopStatus::Pending, '23:55'),
            self::stop(StopType::Pickup, StopStatus::Cancelled, '24:05'),
            self::stop(StopType::DropOff, StopStatus::Completed, '24:20', '24:22'),
            self::stop(StopType::DropOff, StopStatus::Cancelled, '24:25'),
            self::stop(StopType::DropOff, StopStatus::Completed, '24:30', '24:33'),
        ]);

        self::assertTime('2026-01-05 23:40', $run->firstPickupPlanned);
        self::assertSame(60, $run->firstPickupDelaySeconds);
        self::assertTime('2026-01-06 00:30', $run->lastDropPlanned);
        self::assertTime('2026-01-06 00:33', $run->lastDropActual);
        self::assertSame(180, $run->lastDropDelaySeconds);
    }

    public function testCancelledFirstPickupMovesTheFirstPickupToTheNextPickup(): void
    {
        $run = self::calculate([
            self::stop(StopType::Pickup, StopStatus::Cancelled, '07:00'),
            self::stop(StopType::Pickup, StopStatus::Completed, '07:10', '07:16'),
            self::stop(StopType::DropOff, StopStatus::Completed, '07:35', '07:35'),
        ]);

        self::assertTime('2026-01-05 07:10', $run->firstPickupPlanned);
        self::assertSame(360, $run->firstPickupDelaySeconds);
    }

    public function testCancelledLastDropOffMovesTheLastDropOffToThePreviousDropOff(): void
    {
        $run = self::calculate([
            self::stop(StopType::Pickup, StopStatus::Completed, '07:00', '07:00'),
            self::stop(StopType::DropOff, StopStatus::Completed, '07:25', '07:26'),
            self::stop(StopType::DropOff, StopStatus::Cancelled, '07:35'),
        ]);

        self::assertTime('2026-01-05 07:25', $run->lastDropPlanned);
        self::assertSame(60, $run->lastDropDelaySeconds);
    }

    /**
     * A stop with no registration from the driver is not measured. The run keeps its planned time.
     */
    public function testPendingLastDropOffHasNoDelay(): void
    {
        $run = self::calculate([
            self::stop(StopType::Pickup, StopStatus::Completed, '07:00', '07:01'),
            self::stop(StopType::DropOff, StopStatus::Pending, '07:35'),
        ]);

        self::assertTime('2026-01-05 07:35', $run->lastDropPlanned);
        self::assertNull($run->lastDropActual);
        self::assertNull($run->lastDropDelaySeconds);
    }

    /**
     * Only a completed stop is measured, also when another stop has an actual time.
     */
    public function testStopThatIsNotCompletedIsNotMeasured(): void
    {
        $run = self::calculate([
            self::stop(StopType::Pickup, StopStatus::Pending, '07:00', '07:01'),
            self::stop(StopType::DropOff, StopStatus::Completed, '07:35'),
        ]);

        self::assertTime('2026-01-05 07:00', $run->firstPickupPlanned);
        self::assertNull($run->firstPickupActual);
        self::assertNull($run->firstPickupDelaySeconds);
        self::assertTime('2026-01-05 07:35', $run->lastDropPlanned);
        self::assertNull($run->lastDropActual);
        self::assertNull($run->lastDropDelaySeconds);
    }

    public function testRunWithAllStopsCancelledIsCancelled(): void
    {
        $run = self::calculate([
            self::stop(StopType::Pickup, StopStatus::Cancelled, '07:00'),
            self::stop(StopType::DropOff, StopStatus::Cancelled, '07:35'),
        ]);

        self::assertEquals(new RouteRunDto(null, null, null, null, null, null, true), $run);
    }

    /**
     * One stop that is not cancelled is sufficient. The run then has no drop-off.
     */
    public function testRunWithOneStopThatIsNotCancelledIsNotCancelled(): void
    {
        $run = self::calculate([
            self::stop(StopType::Pickup, StopStatus::Cancelled, '07:00'),
            self::stop(StopType::Pickup, StopStatus::Pending, '07:10'),
            self::stop(StopType::DropOff, StopStatus::Cancelled, '07:35'),
        ]);

        self::assertFalse($run->cancelled);
        self::assertTime('2026-01-05 07:10', $run->firstPickupPlanned);
        self::assertNull($run->lastDropPlanned);
        self::assertNull($run->lastDropActual);
        self::assertNull($run->lastDropDelaySeconds);
    }

    public function testStopsInAnyOrderGiveTheSameRun(): void
    {
        $run = self::calculate([
            self::stop(StopType::DropOff, StopStatus::Completed, '07:35', '07:39'),
            self::stop(StopType::Pickup, StopStatus::Completed, '07:10', '07:12'),
            self::stop(StopType::DropOff, StopStatus::Completed, '07:25', '07:27'),
            self::stop(StopType::Pickup, StopStatus::Completed, '07:00', '07:02'),
        ]);

        self::assertTime('2026-01-05 07:00', $run->firstPickupPlanned);
        self::assertTime('2026-01-05 07:35', $run->lastDropPlanned);
    }

    /**
     * The builder gives the stops of one planned time in the order of their ids. Then the first
     * pickup is the first one, and the last drop-off is the last one.
     */
    public function testStopsWithTheSamePlannedTime(): void
    {
        $run = self::calculate([
            self::stop(StopType::Pickup, StopStatus::Completed, '07:00', '07:01'),
            self::stop(StopType::Pickup, StopStatus::Completed, '07:00', '07:02'),
            self::stop(StopType::DropOff, StopStatus::Completed, '07:35', '07:36'),
            self::stop(StopType::DropOff, StopStatus::Completed, '07:35', '07:37'),
        ]);

        self::assertSame(60, $run->firstPickupDelaySeconds);
        self::assertSame(120, $run->lastDropDelaySeconds);
    }

    /**
     * @param list<RunStopDto> $stops
     */
    private static function calculate(array $stops): RouteRunDto
    {
        return new RouteRunCalculator()->calculate($stops);
    }

    private static function stop(StopType $type, StopStatus $status, string $planned, ?string $actual = null): RunStopDto
    {
        $plannedTime = self::at($planned);
        $actualTime = null === $actual ? null : self::at($actual);

        return new RunStopDto($type, $status, $plannedTime, $actualTime, null === $actualTime ? null : $actualTime->getTimestamp() - $plannedTime->getTimestamp());
    }

    /**
     * Gives a time on 2026-01-05. A time of 24:00 or more is on the next day.
     */
    private static function at(string $time): \DateTimeImmutable
    {
        [$hours, $minutes] = array_map(intval(...), explode(':', $time));

        return new \DateTimeImmutable('2026-01-05')->modify(\sprintf('+%d hours +%d minutes', $hours, $minutes));
    }

    private static function assertTime(string $expected, ?\DateTimeImmutable $value): void
    {
        self::assertNotNull($value);
        self::assertSame($expected, $value->format('Y-m-d H:i'));
    }
}
