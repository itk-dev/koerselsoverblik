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
     * Gives the ids of the routes that the figures count: the routes of a sub-agreement that is
     * not ignored, and of the supplier if a supplier is given.
     *
     * @param ?int $supplierId the id of a supplier, or null for all suppliers
     *
     * @return list<int>
     */
    public function countedIds(?int $supplierId = null): array
    {
        $builder = $this->createQueryBuilder('route')
            ->select('route.id')
            ->join('route.subAgreement', 'subAgreement')
            ->where('subAgreement.ignored = false');
        if (null !== $supplierId) {
            $builder
                ->andWhere('IDENTITY(subAgreement.supplier) = :supplier')
                ->setParameter('supplier', $supplierId);
        }
        /** @var list<int|string> $ids */
        $ids = $builder->getQuery()->getSingleColumnResult();

        // MariaDB can give an id as text.
        return array_map(static fn (int|string $id): int => (int) $id, $ids);
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
