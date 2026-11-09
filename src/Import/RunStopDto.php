<?php

namespace App\Import;

use App\Import\Mapping\StopStatus;
use App\Import\Mapping\StopType;

/**
 * The values of one stored stop row that a route run needs.
 */
final readonly class RunStopDto
{
    /**
     * @param ?int $delaySeconds the actual time minus the planned time, or null if the stop has no actual time
     */
    public function __construct(
        public StopType $type,
        public StopStatus $status,
        public \DateTimeImmutable $plannedTime,
        public ?\DateTimeImmutable $actualTime,
        public ?int $delaySeconds,
    ) {
    }

    /**
     * A measured stop is completed, and it has an actual time. A cancelled stop and a stop with no
     * registration from the driver are not measured.
     */
    public function isMeasured(): bool
    {
        return StopStatus::Completed === $this->status && null !== $this->actualTime;
    }

    /**
     * Gives the actual time, or null if the stop is not measured.
     */
    public function measuredActualTime(): ?\DateTimeImmutable
    {
        return $this->isMeasured() ? $this->actualTime : null;
    }

    /**
     * Gives the delay, or null if the stop is not measured.
     */
    public function measuredDelaySeconds(): ?int
    {
        return $this->isMeasured() ? $this->delaySeconds : null;
    }
}
