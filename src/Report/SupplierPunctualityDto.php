<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The punctuality figures of one supplier.
 */
final readonly class SupplierPunctualityDto
{
    /**
     * @param ?string $supplier the name of the supplier, or null for the routes with no supplier on the date ("Unknown")
     */
    public function __construct(
        public ?string $supplier,
        public PunctualityFiguresDto $figures,
    ) {
    }
}
