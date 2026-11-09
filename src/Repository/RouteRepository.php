<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Route;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Route>
 */
class RouteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Route::class);
    }

    /**
     * Gives the routes with their sub-agreement, its assignments and their suppliers, in one
     * query. Then SubAgreement::getAssignmentOn() finds the supplier on a date with no more
     * queries.
     *
     * @param list<int> $ids
     *
     * @return array<int, Route> the routes, keyed by the id
     */
    public function findWithAssignments(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var list<Route> $routes */
        $routes = $this->createQueryBuilder('route')
            ->select('route', 'subAgreement', 'assignment', 'supplier')
            ->join('route.subAgreement', 'subAgreement')
            ->leftJoin('subAgreement.assignments', 'assignment')
            ->leftJoin('assignment.supplier', 'supplier')
            ->where('route.id IN (:ids)')
            ->orderBy('assignment.validFrom')
            ->setParameter('ids', $ids)
            ->getQuery()
            // A managed sub-agreement can have a collection of assignments that the query does not
            // fill again. The refresh fills it from the database.
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();

        $byId = [];
        foreach ($routes as $route) {
            $byId[(int) $route->getId()] = $route;
        }

        return $byId;
    }
}
