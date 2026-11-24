<?php

declare(strict_types=1);

namespace App\Report;

/**
 * One row of the table of the sub-agreements of the activity page.
 */
final readonly class SubAgreementTripsDto
{
    /**
     * @param string  $reference the reference of the sub-agreement
     * @param ?string $supplier  the name of the supplier, or null if the sub-agreement has no supplier ("Ukendt")
     * @param int     $trips     the pickups of the routes of the sub-agreement, with any status
     * @param float   $share     the trips as a share of the trips of all rows of the table, in percent
     */
    public function __construct(
        public string $reference,
        public ?string $supplier,
        public int $trips,
        public float $share,
    ) {
    }
}
