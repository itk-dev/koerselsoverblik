<?php

declare(strict_types=1);

namespace App\Report;

use App\Clock\LocalCalendar;
use App\Entity\Supplier;
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

    /**
     * @param mixed   $measures  the value of "m" in the query, see PeriodFilter::fromQuery()
     * @param ?string $threshold the threshold of the conclusion
     */
    public function select(?string $type, ?string $date, ?string $comparison, ?string $supplier, mixed $measures = null, ?string $threshold = null): PeriodSelectionDto
    {
        $filter = PeriodFilter::fromQuery($type, $date, $comparison, $supplier, $this->calendar->today(), $measures, $threshold);
        // One query gives the suppliers of the filter bar and checks the selected supplier.
        $suppliers = $this->suppliers->findAllByName();
        $ids = array_map(static fn (Supplier $item): ?int => $item->getId(), $suppliers);
        if (null !== $filter->supplier && !\in_array($filter->supplier, $ids, true)) {
            $filter = $filter->withoutSupplier();
        }

        return $this->periods->create($filter)->withSuppliers($suppliers);
    }
}
