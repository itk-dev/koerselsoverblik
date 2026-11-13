<?php

declare(strict_types=1);

namespace App\Report;

use App\Import\Mapping\StopType;
use App\Repository\RouteRepository;
use App\Repository\RouteRunRepository;
use App\Repository\StopRowRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Calculates the punctuality figures of a period: the four measures, the delay bands of the last
 * drop-off and the worst routes, in total and for each supplier. See "Calculating the figures" in
 * docs/architecture.md.
 *
 * The import calculates each delay in PHP (ADR 005). The queries only compare a delay with the
 * limits, and count.
 */
final readonly class PunctualityCalculator
{
    private const int SECONDS_IN_A_MINUTE = 60;

    /**
     * The highest delay of the band "more than the tolerance to 15 minutes".
     */
    private const int UP_TO_15_SECONDS = 15 * self::SECONDS_IN_A_MINUTE;

    /**
     * The highest delay of the band "more than 15 to 30 minutes".
     */
    private const int UP_TO_30_SECONDS = 30 * self::SECONDS_IN_A_MINUTE;

    private const int WORST_ROUTES = 10;

    /**
     * @param int $toleranceMinutes the value of APP_PUNCTUALITY_TOLERANCE_MINUTES
     */
    public function __construct(
        private StopRowRepository $stopRows,
        private RouteRunRepository $routeRuns,
        private RouteRepository $routes,
        #[Autowire(param: 'app.punctuality_tolerance_minutes')]
        private int $toleranceMinutes,
    ) {
    }

    /**
     * @param ?int $supplierId the id of a supplier, so that the total and the worst routes are of that supplier only, or null for all suppliers. The figures of each supplier are always of all suppliers
     */
    public function calculate(DateRangeDto $range, ?int $supplierId = null): PunctualityReportDto
    {
        $figures = $this->figuresOfEachRoute($range);
        $routes = $this->routes->findWithSuppliers(array_keys($figures));

        $total = PunctualityFiguresDto::empty();
        /** @var array<string, PunctualityFiguresDto> $bySupplier keyed by the supplier name, or '' for "Unknown" */
        $bySupplier = [];
        $byRoute = [];
        foreach ($figures as $routeId => $routeFigures) {
            $route = $routes[$routeId];
            // The routes of a sub-agreement with no supplier count as "Unknown".
            $supplier = $route->getSubAgreement()->getSupplier();
            $name = $supplier?->getName();
            $bySupplier[$name ?? ''] = ($bySupplier[$name ?? ''] ?? PunctualityFiguresDto::empty())->add($routeFigures);
            if (null !== $supplierId && $supplier?->getId() !== $supplierId) {
                continue;
            }

            $total = $total->add($routeFigures);
            $byRoute[] = new RoutePunctualityDto($route->getSourceKey(), $name, $routeFigures->dropOffPerRun);
        }

        return new PunctualityReportDto($total, self::suppliers($bySupplier), self::worstRoutes($byRoute));
    }

    /**
     * Reads the counts of the stops and the runs, and adds them for each route. The counts of
     * all dates of the range go to the same route.
     *
     * @return array<int, PunctualityFiguresDto> keyed by the route id
     */
    private function figuresOfEachRoute(DateRangeDto $range): array
    {
        $onTimeSeconds = $this->toleranceMinutes * self::SECONDS_IN_A_MINUTE;
        $none = new PunctualityCountDto(0, 0);
        $noBands = new DelayBandsDto(0, 0, 0, 0);

        $figures = [];
        foreach ($this->stopRows->countMeasuredStops($range, $onTimeSeconds) as $row) {
            $count = new PunctualityCountDto($row['onTime'], $row['measured']);
            self::addTo($figures, $row['route'], StopType::Pickup === $row['stopType']
                ? new PunctualityFiguresDto($count, $none, $none, $none, $noBands)
                : new PunctualityFiguresDto($none, $count, $none, $none, $noBands));
        }
        foreach ($this->routeRuns->countRunFigures($range, $onTimeSeconds, self::UP_TO_15_SECONDS, self::UP_TO_30_SECONDS) as $row) {
            self::addTo($figures, $row['route'], new PunctualityFiguresDto(
                $none,
                $none,
                new PunctualityCountDto($row['firstPickupOnTime'], $row['firstPickupMeasured']),
                new PunctualityCountDto($row['lastDropOnTime'], $row['lastDropMeasured']),
                new DelayBandsDto($row['lastDropOnTime'], $row['lastDropUpTo15'], $row['lastDropUpTo30'], $row['lastDropOver30']),
            ));
        }

        return $figures;
    }

    /**
     * @param array<int, PunctualityFiguresDto> $figures keyed by the route id
     */
    private static function addTo(array &$figures, int $route, PunctualityFiguresDto $add): void
    {
        $figures[$route] = ($figures[$route] ?? PunctualityFiguresDto::empty())->add($add);
    }

    /**
     * Gives the suppliers in the order of the name, with "Unknown" last. The order is the order of
     * the bytes, so a name that starts with "Aa" is first, not last as in a Danish list.
     *
     * @param array<string, PunctualityFiguresDto> $bySupplier keyed by the supplier name, or '' for "Unknown"
     *
     * @return list<SupplierPunctualityDto>
     */
    private static function suppliers(array $bySupplier): array
    {
        $unknown = $bySupplier[''] ?? null;
        unset($bySupplier['']);
        ksort($bySupplier, \SORT_STRING);

        $suppliers = [];
        foreach ($bySupplier as $name => $figures) {
            $suppliers[] = new SupplierPunctualityDto((string) $name, $figures);
        }
        if (null !== $unknown) {
            $suppliers[] = new SupplierPunctualityDto(null, $unknown);
        }

        return $suppliers;
    }

    /**
     * Gives the routes with the lowest punctuality at the last drop-off. On the same punctuality,
     * the route with more measured runs is first, and then the route name decides. A route with
     * no measured last drop-off has no punctuality, so it is not in the list.
     *
     * @param list<RoutePunctualityDto> $byRoute
     *
     * @return list<RoutePunctualityDto>
     */
    private static function worstRoutes(array $byRoute): array
    {
        // usort() gives a list, so the keys of the filter do not stay.
        $routes = array_filter($byRoute, static fn (RoutePunctualityDto $route): bool => $route->lastDropOff->measured > 0);
        usort($routes, static fn (RoutePunctualityDto $a, RoutePunctualityDto $b): int => [$a->lastDropOff->percent(), $b->lastDropOff->measured, $a->route]
            <=> [$b->lastDropOff->percent(), $a->lastDropOff->measured, $b->route]);

        return \array_slice($routes, 0, self::WORST_ROUTES);
    }
}
