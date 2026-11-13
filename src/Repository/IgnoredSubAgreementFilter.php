<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\ORM\QueryBuilder;

/**
 * Leaves the rows of an ignored sub-agreement out of a query for the figures. Each query that
 * counts driving data for the figures must use it.
 */
final class IgnoredSubAgreementFilter
{
    /**
     * Joins the route and its sub-agreement, and adds the condition. Call it after where(),
     * because where() replaces all conditions.
     *
     * @param string $route the path to the route of a row, for example "row.route"
     */
    public static function apply(QueryBuilder $builder, string $route): QueryBuilder
    {
        return $builder
            ->join($route, 'filterRoute')
            ->join('filterRoute.subAgreement', 'filterSubAgreement')
            ->andWhere('filterSubAgreement.ignored = false');
    }
}
