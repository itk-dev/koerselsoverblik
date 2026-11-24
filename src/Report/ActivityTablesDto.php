<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The two tables of the activity page.
 */
final readonly class ActivityTablesDto
{
    /**
     * @param list<SubAgreementTripsDto>   $subAgreements the sub-agreements with trips, the most trips first
     * @param list<ActivitySupplierRowDto> $suppliers     all suppliers, in the order of the name, with "Ukendt" last
     */
    public function __construct(
        public array $subAgreements,
        public array $suppliers,
    ) {
    }
}
