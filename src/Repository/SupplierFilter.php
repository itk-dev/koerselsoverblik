<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\ORM\QueryBuilder;

/**
 * Limits a query on an entity with a route to the routes of one supplier: the routes of the
 * sub-agreements that the supplier drives.
 */
final class SupplierFilter
{
    /**
     * @param string $alias      the alias of the entity with the route, for example "row" or "run"
     * @param ?int   $supplierId the id of the supplier, or null for all suppliers
     */
    public static function apply(QueryBuilder $query, string $alias, ?int $supplierId): void
    {
        if (null === $supplierId) {
            return;
        }

        $query
            ->join($alias.'.route', 'filterRoute')
            ->join('filterRoute.subAgreement', 'filterSubAgreement')
            ->andWhere('IDENTITY(filterSubAgreement.supplier) = :filterSupplier')
            ->setParameter('filterSupplier', $supplierId);
    }
}
