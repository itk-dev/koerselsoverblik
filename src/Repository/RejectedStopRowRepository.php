<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ImportBatch;
use App\Entity\RejectedStopRow;
use App\Import\ImportRangeDto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RejectedStopRow>
 */
class RejectedStopRowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RejectedStopRow::class);
    }

    /**
     * Gives the ids of the copies of the rejected rows in the range. Read them before the delete,
     * because a deleted row no longer points to its copy.
     *
     * @return list<int>
     */
    public function sourceRowIdsInRange(ImportRangeDto $range): array
    {
        $builder = $this->createQueryBuilder('rejected')->select('IDENTITY(rejected.sourceRow)');
        /** @var list<int|string> $ids */
        $ids = $this->inRange($builder, $range)->getQuery()->getSingleColumnResult();

        // MariaDB can give an id as text.
        return array_map(static fn (int|string $id): int => (int) $id, $ids);
    }

    /**
     * Deletes the rejected rows in the range. A DQL delete does not delete the copies.
     */
    public function deleteInRange(ImportRangeDto $range): void
    {
        $this->inRange($this->createQueryBuilder('rejected')->delete(), $range)->getQuery()->execute();
    }

    /**
     * Limits the query to the rejected rows in the range. A rejected row with no date belongs to
     * the range when the whole range of its batch is in the range.
     *
     * Thus a rejected row with no date of a larger batch stays until a range covers the whole range
     * of that batch. Such rows can collect, but the date of a row is not known, so a smaller range
     * must not include it.
     */
    private function inRange(QueryBuilder $builder, ImportRangeDto $range): QueryBuilder
    {
        return $builder
            ->where(
                'rejected.serviceDate BETWEEN :first AND :last'
                .' OR (rejected.serviceDate IS NULL AND rejected.importBatch IN ('
                .'SELECT batch.id FROM '.ImportBatch::class.' batch WHERE batch.firstDate >= :first AND batch.lastDate <= :last))',
            )
            ->setParameter('first', $range->firstDate, Types::DATE_IMMUTABLE)
            ->setParameter('last', $range->lastDate, Types::DATE_IMMUTABLE);
    }
}
