<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ImportBatch;
use App\Report\DateRangeDto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ImportBatch>
 */
class ImportBatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImportBatch::class);
    }

    /**
     * Gives the batches with one or more dates in the range, with their users. The newest batch is
     * first. Of two batches with the same time, the batch with the higher id is first, because it
     * was stored last.
     *
     * @return list<ImportBatch>
     */
    public function findOverlapping(DateRangeDto $range): array
    {
        /** @var list<ImportBatch> $batches */
        $batches = $this->createQueryBuilder('batch')
            ->addSelect('user')
            ->leftJoin('batch.uploadedBy', 'user')
            ->where('batch.firstDate <= :last AND batch.lastDate >= :first')
            ->setParameter('first', $range->firstDate, Types::DATE_IMMUTABLE)
            ->setParameter('last', $range->lastDate, Types::DATE_IMMUTABLE)
            ->orderBy('batch.uploadedAt', 'DESC')
            ->addOrderBy('batch.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $batches;
    }
}
