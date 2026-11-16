<?php

declare(strict_types=1);

namespace App\Report;

/**
 * One row of the supplier table of the overview: the figures of the routes of one supplier.
 */
final readonly class SupplierRowDto
{
    /**
     * @param ?string             $supplier      the name of the supplier, or null for the routes with no supplier ("Ukendt")
     * @param int                 $trips         the pickups, with any status
     * @param int                 $cancelled     the cancelled pickups
     * @param SoloCountDto        $solo          the runs with a peak and the real solo runs
     * @param PunctualityCountDto $dropOffPerRun the punctuality of the last drop-offs
     */
    public function __construct(
        public ?string $supplier,
        public int $trips,
        public int $cancelled,
        public SoloCountDto $solo,
        public PunctualityCountDto $dropOffPerRun,
    ) {
    }

    /**
     * Gives the cancelled trips as a share of the trips in percent, or null if there are no trips.
     */
    public function cancelledPercent(): ?float
    {
        return 0 === $this->trips ? null : $this->cancelled / $this->trips * 100;
    }
}
