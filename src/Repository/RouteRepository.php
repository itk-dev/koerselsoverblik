<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Route;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
     * Gives the routes with their sub-agreement and its supplier, in one query.
     *
     * @param list<int> $ids
     *
     * @return array<int, Route> the routes, keyed by the id
     */
    public function findWithSuppliers(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var list<Route> $routes */
        $routes = $this->createQueryBuilder('route')
            ->select('route', 'subAgreement', 'supplier')
            ->join('route.subAgreement', 'subAgreement')
            ->leftJoin('subAgreement.supplier', 'supplier')
            ->where('route.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $byId = [];
        foreach ($routes as $route) {
            $byId[(int) $route->getId()] = $route;
        }

        return $byId;
    }
}
