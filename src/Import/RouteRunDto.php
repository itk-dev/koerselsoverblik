<?php

declare(strict_types=1);

namespace App\Import;

/**
 * The first pickup, the last drop-off and the peak passengers of one route run. The actual time
 * and the delay are null when the stop is not measured. All values are null when the run has no
 * such stop.
 */
final readonly class RouteRunDto
{
    /**
     * @param bool $cancelled      true when all stops of the run are cancelled
     * @param ?int $peakPassengers the most citizens in the car at a time, or null if no stop has a count. A run with a peak of 1 is a real solo run
     */
    public function __construct(
        public ?\DateTimeImmutable $firstPickupPlanned,
        public ?\DateTimeImmutable $firstPickupActual,
        public ?int $firstPickupDelaySeconds,
        public ?\DateTimeImmutable $lastDropPlanned,
        public ?\DateTimeImmutable $lastDropActual,
        public ?int $lastDropDelaySeconds,
        public bool $cancelled,
        public ?int $peakPassengers,
    ) {
    }
}
