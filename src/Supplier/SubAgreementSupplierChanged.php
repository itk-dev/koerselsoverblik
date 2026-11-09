<?php

declare(strict_types=1);

namespace App\Supplier;

use App\Entity\SubAgreement;

/**
 * An assignment of the sub-agreement was added, changed or deleted. The daily figures must be built
 * again for the dates of the old and the new period.
 */
final readonly class SubAgreementSupplierChanged
{
    /**
     * @param ?ValidityPeriod $oldPeriod the period before the change, or null for a new assignment
     * @param ?ValidityPeriod $newPeriod the period after the change, or null for a deleted assignment
     */
    public function __construct(
        public SubAgreement $subAgreement,
        public ?ValidityPeriod $oldPeriod,
        public ?ValidityPeriod $newPeriod,
    ) {
    }
}
