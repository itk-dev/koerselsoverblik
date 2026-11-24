<?php

declare(strict_types=1);

namespace App\Report;

/**
 * One row of the supplier table of the activity page: the totals of the routes of one supplier.
 */
final readonly class ActivitySupplierRowDto
{
    /**
     * @param ?string      $supplier the name of the supplier, or null for the routes with no supplier ("Ukendt")
     * @param DayTotalsDto $totals   the sum of the totals of the routes of the supplier
     */
    public function __construct(
        public ?string $supplier,
        public DayTotalsDto $totals,
    ) {
    }

    /**
     * Gives the cancelled trips as a share of all trips in percent, or null if there are no trips.
     */
    public function cancelledPercent(): ?float
    {
        return 0 === $this->totals->trips ? null : $this->totals->cancelledTrips / $this->totals->trips * 100;
    }

    /**
     * Gives the completed trips for each route run that is not cancelled, or null if there are no
     * such runs.
     */
    public function completedPerRun(): ?float
    {
        return 0 === $this->totals->notCancelledRuns ? null : $this->totals->completedTrips / $this->totals->notCancelledRuns;
    }
}
