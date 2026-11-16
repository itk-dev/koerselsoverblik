<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;

/**
 * Limits a query for the figures to the counted routes: the routes of a sub-agreement that is not
 * ignored, and of one supplier if a supplier is given. Each query that counts driving data for the
 * figures must use it.
 *
 * The filter gives the ids of the routes to the query, and does not join the route. With a join,
 * MariaDB starts at the few sub-agreements, and reads the rows of each route for all dates. The
 * query then gets slower when the data grows.
 */
final readonly class CountedRouteFilter
{
    public function __construct(
        private RouteRepository $routes,
    ) {
    }

    /**
     * Adds the condition. Call it after where(), because where() replaces all conditions.
     *
     * @param string $route      the path to the route of a row, for example "row.route"
     * @param ?int   $supplierId the id of a supplier, or null for all suppliers
     */
    public function apply(QueryBuilder $builder, string $route, ?int $supplierId = null): QueryBuilder
    {
        $ids = $this->routes->countedIds($supplierId);
        if ([] === $ids) {
            // An empty list is not valid in SQL, and no route gives no rows.
            return $builder->andWhere('1 = 0');
        }

        return $builder
            ->andWhere(\sprintf('IDENTITY(%s) IN (:countedRoutes)', $route))
            ->setParameter('countedRoutes', $ids, ArrayParameterType::INTEGER);
    }
}
