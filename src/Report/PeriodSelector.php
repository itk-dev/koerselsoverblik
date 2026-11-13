<?php

declare(strict_types=1);

namespace App\Report;

use App\Clock\LocalCalendar;
use App\Repository\SupplierRepository;

/**
 * Reads the filters of a page from the query string, and gives the periods of the page. A
 * supplier id that does not exist gives all suppliers, so that a link always works.
 */
final readonly class PeriodSelector
{
    public function __construct(
        private PeriodRangeFactory $periods,
        private SupplierRepository $suppliers,
        private LocalCalendar $calendar,
    ) {
    }

    public function select(?string $type, ?string $date, ?string $comparison, ?string $supplier): PeriodSelectionDto
    {
        $today = $this->calendar->today();
        $filter = PeriodFilter::fromQuery($type, $date, $comparison, $supplier, $today);
        if (null !== $filter->supplier && null === $this->suppliers->find($filter->supplier)) {
            $filter = $filter->withoutSupplier();
        }

        return $this->periods->create($filter);
    }
}
