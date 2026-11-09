<?php

namespace App\Import;

/**
 * The first pickup and the last drop-off of one route run. The actual time and the delay are null
 * when the stop is not measured. All values are null when the run has no such stop.
 */
final readonly class RouteRunDto
{
    /**
     * @param bool $cancelled true when all stops of the run are cancelled
     */
    public function __construct(
        public ?\DateTimeImmutable $firstPickupPlanned,
        public ?\DateTimeImmutable $firstPickupActual,
        public ?int $firstPickupDelaySeconds,
        public ?\DateTimeImmutable $lastDropPlanned,
        public ?\DateTimeImmutable $lastDropActual,
        public ?int $lastDropDelaySeconds,
        public bool $cancelled,
    ) {
    }
}
