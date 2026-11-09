<?php

declare(strict_types=1);

namespace App\Import;

use App\Import\Mapping\StopStatus;
use App\Import\Mapping\StopType;

/**
 * Finds the first pickup and the last drop-off of one route run. See "Build route runs" in
 * "Pipeline" in docs/architecture.md.
 */
final readonly class RouteRunCalculator
{
    /**
     * The first pickup is the pickup with the earliest planned time that is not cancelled. The
     * last drop-off is the drop-off with the latest planned time that is not cancelled. On equal
     * planned times, the first pickup and the last drop-off in the list win.
     *
     * @param list<RunStopDto> $stops the stops of one route, date and run, in any order
     */
    public function calculate(array $stops): RouteRunDto
    {
        $firstPickup = null;
        $lastDrop = null;
        $cancelled = true;

        foreach ($stops as $stop) {
            if (StopStatus::Cancelled === $stop->status) {
                continue;
            }
            $cancelled = false;

            if (StopType::Pickup === $stop->type) {
                if (null === $firstPickup || $stop->plannedTime < $firstPickup->plannedTime) {
                    $firstPickup = $stop;
                }
            } elseif (null === $lastDrop || $stop->plannedTime >= $lastDrop->plannedTime) {
                $lastDrop = $stop;
            }
        }

        return new RouteRunDto(
            firstPickupPlanned: $firstPickup?->plannedTime,
            firstPickupActual: $firstPickup?->measuredActualTime(),
            firstPickupDelaySeconds: $firstPickup?->measuredDelaySeconds(),
            lastDropPlanned: $lastDrop?->plannedTime,
            lastDropActual: $lastDrop?->measuredActualTime(),
            lastDropDelaySeconds: $lastDrop?->measuredDelaySeconds(),
            cancelled: $cancelled,
        );
    }
}
