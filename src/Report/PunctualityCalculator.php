<?php

declare(strict_types=1);

namespace App\Report;

use App\Repository\LocationDayTotalsRepository;
use App\Repository\RouteDayTotalsRepository;
use App\Repository\RouteRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Calculates the punctuality figures of a period: the four measures, the delay bands of the last
 * drop-off, the worst routes and the worst locations, in total and for each supplier. See
 * "Calculating the figures" in docs/architecture.md.
 *
 * The figures are sums of the totals of each route and day, and of each location and day. The
 * import counts them.
 */
final readonly class PunctualityCalculator
{
    private const int WORST_ROUTES = 10;

    private const int WORST_LOCATIONS = 10;

    /**
     * @param int $locationMinStops the value of APP_PUNCTUALITY_LOCATION_MIN_STOPS
     */
    public function __construct(
        private RouteDayTotalsRepository $totals,
        private LocationDayTotalsRepository $locationTotals,
        private RouteRepository $routes,
        #[Autowire(param: 'app.punctuality_location_min_stops')]
        private int $locationMinStops,
    ) {
    }

    /**
     * @param ?int $supplierId the id of a supplier, so that the total, the worst routes and the worst locations are of that supplier only, or null for all suppliers. The figures of each supplier are always of all suppliers
     */
    public function calculate(DateRangeDto $range, ?int $supplierId = null): PunctualityReportDto
    {
        $figures = array_map(static fn (DayTotalsDto $totals): PunctualityFiguresDto => $totals->punctuality(), $this->totals->sumOfEachRoute($range));
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

        return new PunctualityReportDto(
            $total,
            self::suppliers($bySupplier),
            self::worstRoutes($byRoute),
            $this->worstLocations($this->locationTotals->sumOfEachLocation($range, $supplierId)),
        );
    }

    /**
     * Gives the total of each month in the range. One query gives all months, so that a page does
     * not calculate each month by itself. A month with no totals has no item.
     *
     * @param ?int $supplierId the id of a supplier, or null for all suppliers
     *
     * @return array<string, PunctualityFiguresDto> keyed by the month as YYYY-MM, as MonthDto::query()
     */
    public function totalsByMonth(DateRangeDto $range, ?int $supplierId = null): array
    {
        return array_map(static fn (DayTotalsDto $totals): PunctualityFiguresDto => $totals->punctuality(), $this->totals->sumByMonth($range, $supplierId));
    }

    /**
     * Gives the total of a range, with no figures of each supplier and route.
     *
     * @param ?int $supplierId the id of a supplier, or null for all suppliers
     */
    public function total(DateRangeDto $range, ?int $supplierId = null): PunctualityFiguresDto
    {
        return $this->totals->sum($range, $supplierId)->punctuality();
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
     * Gives the routes with the most late runs at the last drop-off. On the same number, the route
     * with the lower punctuality is first, and then the route name decides. Thus a route with one
     * run that is late (0 %) is not above a route with many late runs. A route with no measured
     * last drop-off has no punctuality, so it is not in the list.
     *
     * @param list<RoutePunctualityDto> $byRoute
     *
     * @return list<RoutePunctualityDto>
     */
    private static function worstRoutes(array $byRoute): array
    {
        // usort() gives a list, so the keys of the filter do not stay.
        $routes = array_filter($byRoute, static fn (RoutePunctualityDto $route): bool => $route->lastDropOff->measured > 0);
        usort($routes, static fn (RoutePunctualityDto $a, RoutePunctualityDto $b): int => [$b->lastDropOff->late(), $a->lastDropOff->percent(), $a->route]
            <=> [$a->lastDropOff->late(), $b->lastDropOff->percent(), $b->route]);

        return \array_slice($routes, 0, self::WORST_ROUTES);
    }

    /**
     * Gives the locations with the lowest punctuality of the pickups and the drop-offs together.
     * On the same punctuality, the location with more measured stops is first, and then the name
     * decides. A location with fewer measured stops than the minimum is not ranked, because a few
     * stops do not show a pattern.
     *
     * @param list<LocationPunctualityDto> $locations
     */
    private function worstLocations(array $locations): WorstLocationsDto
    {
        // usort() gives a list, so the keys of the filter do not stay.
        $ranked = array_filter($locations, fn (LocationPunctualityDto $location): bool => $location->stops->measured >= $this->locationMinStops);
        usort($ranked, static fn (LocationPunctualityDto $a, LocationPunctualityDto $b): int => [$a->stops->percent(), $b->stops->measured, $a->location]
            <=> [$b->stops->percent(), $a->stops->measured, $b->location]);

        return new WorstLocationsDto(
            \array_slice($ranked, 0, self::WORST_LOCATIONS),
            \count($locations) - \count($ranked),
            $this->locationMinStops,
        );
    }
}
