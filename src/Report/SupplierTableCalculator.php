<?php

declare(strict_types=1);

namespace App\Report;

use App\Repository\RouteRepository;
use App\Repository\RouteRunRepository;
use App\Repository\StopRowRepository;

/**
 * Calculates the supplier table of the overview: the trips, the cancelled trips, the real solo
 * runs and the punctuality of each supplier. It counts each route, and then adds the routes of
 * each supplier, as PunctualityCalculator does.
 */
final readonly class SupplierTableCalculator
{
    public function __construct(
        private StopRowRepository $stopRows,
        private RouteRunRepository $routeRuns,
        private RouteRepository $routes,
    ) {
    }

    /**
     * @param PunctualityReportDto $punctuality the punctuality of the same range, from PunctualityCalculator
     *
     * @return list<SupplierRowDto> in the order of the name, with "Ukendt" last
     */
    public function calculate(DateRangeDto $range, PunctualityReportDto $punctuality): array
    {
        /** @var array<int, array{trips: int, cancelled: int, runs: int, soloRuns: int}> $byRoute keyed by the route id */
        $byRoute = [];
        foreach ($this->stopRows->countTripsOfEachRoute($range) as $row) {
            $byRoute[$row['route']] = ['trips' => $row['trips'], 'cancelled' => $row['cancelled'], 'runs' => 0, 'soloRuns' => 0];
        }
        foreach ($this->routeRuns->countSoloRunsOfEachRoute($range) as $row) {
            $byRoute[$row['route']] = ['runs' => $row['runs'], 'soloRuns' => $row['soloRuns']] + ($byRoute[$row['route']] ?? ['trips' => 0, 'cancelled' => 0]);
        }

        /** @var array<string, array{trips: int, cancelled: int, runs: int, soloRuns: int}> $bySupplier keyed by the supplier name, or '' for "Ukendt" */
        $bySupplier = [];
        foreach ($this->routes->findWithSuppliers(array_keys($byRoute)) as $routeId => $route) {
            // The routes of a sub-agreement with no supplier count as "Ukendt".
            $supplier = $route->getSubAgreement()->getSupplier()?->getName() ?? '';
            $sum = $bySupplier[$supplier] ?? ['trips' => 0, 'cancelled' => 0, 'runs' => 0, 'soloRuns' => 0];
            foreach ($byRoute[$routeId] as $key => $count) {
                $sum[$key] += $count;
            }
            $bySupplier[$supplier] = $sum;
        }

        $dropOffs = [];
        foreach ($punctuality->suppliers as $item) {
            $dropOffs[$item->supplier ?? ''] = $item->figures->dropOffPerRun;
        }

        // The order of the punctuality page: by the bytes of the name, with "Ukendt" last.
        $names = array_map(strval(...), array_keys($bySupplier + $dropOffs));
        $names = array_diff($names, ['']);
        sort($names, \SORT_STRING);
        if (isset($bySupplier['']) || isset($dropOffs[''])) {
            $names[] = '';
        }

        return array_map(static function (string $name) use ($bySupplier, $dropOffs): SupplierRowDto {
            $sum = $bySupplier[$name] ?? ['trips' => 0, 'cancelled' => 0, 'runs' => 0, 'soloRuns' => 0];

            return new SupplierRowDto(
                '' === $name ? null : $name,
                $sum['trips'],
                $sum['cancelled'],
                new SoloCountDto($sum['runs'], $sum['soloRuns']),
                $dropOffs[$name] ?? new PunctualityCountDto(0, 0),
            );
        }, $names);
    }
}
