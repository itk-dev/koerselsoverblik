<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RouteRun;
use App\Import\ImportRangeDto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RouteRun>
 */
class RouteRunRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RouteRun::class);
    }

    /**
     * Deletes the route runs in the range.
     */
    public function deleteInRange(ImportRangeDto $range): void
    {
        $this->createQueryBuilder('run')
            ->delete()
            ->where('run.serviceDate BETWEEN :first AND :last')
            ->setParameter('first', $range->firstDate, Types::DATE_IMMUTABLE)
            ->setParameter('last', $range->lastDate, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->execute();
    }
}
