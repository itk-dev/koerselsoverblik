<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StopRow;
use App\Import\ImportRangeDto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StopRow>
 */
class StopRowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StopRow::class);
    }

    /**
     * Gives the number of stored stop rows in the range, from all mappings. An import deletes them.
     */
    public function countInRange(ImportRangeDto $range): int
    {
        return (int) $this->createQueryBuilder('row')
            ->select('COUNT(row.id)')
            ->where('row.serviceDate BETWEEN :first AND :last')
            ->setParameter('first', $range->firstDate, Types::DATE_IMMUTABLE)
            ->setParameter('last', $range->lastDate, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
