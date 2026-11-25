<?php

declare(strict_types=1);

namespace App\Report;

use App\Repository\RouteDayTotalsRepository;
use App\Repository\RouteRepository;

/**
 * Calculates the tables of the activity page: the trips of each sub-agreement, and the volume of
 * each supplier. One sum of each route gives both tables.
 */
final readonly class ActivityTableCalculator
{
    public function __construct(
        private RouteDayTotalsRepository $totals,
        private RouteRepository $routes,
    ) {
    }

    /**
     * @param ?int $supplierId the id of the supplier of the filter, or null for all suppliers. It limits only the table of the sub-agreements
     */
    public function calculate(DateRangeDto $range, ?int $supplierId): ActivityTablesDto
    {
        // The sum has no routes of an ignored sub-agreement.
        $totals = $this->totals->sumOfEachRoute($range);

        /** @var array<string, DayTotalsDto> $bySupplier keyed by the supplier name, or '' for "Ukendt" */
        $bySupplier = [];
        /** @var array<string, array{supplier: ?string, trips: int}> $bySubAgreement keyed by the reference */
        $bySubAgreement = [];
        foreach ($this->routes->findWithSuppliers(array_keys($totals)) as $routeId => $route) {
            $subAgreement = $route->getSubAgreement();
            $supplier = $subAgreement->getSupplier();
            $name = $supplier?->getName() ?? '';
            $bySupplier[$name] = ($bySupplier[$name] ?? DayTotalsDto::empty())->add($totals[$routeId]);

            if (null !== $supplierId && $supplier?->getId() !== $supplierId) {
                continue;
            }
            $reference = $subAgreement->getReference();
            $bySubAgreement[$reference] = [
                'supplier' => $supplier?->getName(),
                'trips' => ($bySubAgreement[$reference]['trips'] ?? 0) + $totals[$routeId]->trips,
            ];
        }

        return new ActivityTablesDto(self::subAgreementRows($bySubAgreement), self::supplierRows($bySupplier));
    }

    /**
     * A sub-agreement with no trips has no row. The most trips are first. On the same number of
     * trips, the reference decides.
     *
     * @param array<string, array{supplier: ?string, trips: int}> $bySubAgreement
     *
     * @return list<SubAgreementTripsDto>
     */
    private static function subAgreementRows(array $bySubAgreement): array
    {
        $bySubAgreement = array_filter($bySubAgreement, static fn (array $item): bool => $item['trips'] > 0);
        $total = array_sum(array_column($bySubAgreement, 'trips'));

        $rows = [];
        foreach ($bySubAgreement as $reference => $item) {
            // A reference with only digits is an integer key, so it is made a string again.
            $rows[] = new SubAgreementTripsDto((string) $reference, $item['supplier'], $item['trips'], $item['trips'] / $total * 100);
        }
        usort($rows, static fn (SubAgreementTripsDto $a, SubAgreementTripsDto $b): int => [$b->trips, $a->reference] <=> [$a->trips, $b->reference]);

        return $rows;
    }

    /**
     * The order of the overview: by the bytes of the name, with "Ukendt" last.
     *
     * @param array<string, DayTotalsDto> $bySupplier
     *
     * @return list<ActivitySupplierRowDto>
     */
    private static function supplierRows(array $bySupplier): array
    {
        // A name with only digits is an integer key. array_map() calls the closure below in
        // coercive mode, so the closure gets the name as a string.
        $names = array_diff(array_keys($bySupplier), ['']);
        sort($names, \SORT_STRING);
        if (isset($bySupplier[''])) {
            $names[] = '';
        }

        return array_map(static fn (string $name): ActivitySupplierRowDto => new ActivitySupplierRowDto('' === $name ? null : $name, $bySupplier[$name]), $names);
    }
}
