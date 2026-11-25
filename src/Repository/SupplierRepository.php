<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Supplier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Supplier>
 */
class SupplierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Supplier::class);
    }

    /**
     * Gives all suppliers in the order of the name.
     *
     * @return list<Supplier>
     */
    public function findAllByName(): array
    {
        /** @var list<Supplier> $suppliers */
        $suppliers = $this->createQueryBuilder('supplier')
            ->orderBy('supplier.name')
            ->getQuery()
            ->getResult();

        return $suppliers;
    }
}
