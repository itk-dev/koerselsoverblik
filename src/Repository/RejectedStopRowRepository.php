<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ImportBatch;
use App\Entity\RejectedStopRow;
use App\Import\ImportRangeDto;
use App\Report\DateRangeDto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query;
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
     * Gives the number of rejected rows in the range. The clear shows it before the delete.
     */
    public function countInRange(ImportRangeDto $range): int
    {
        $builder = $this->createQueryBuilder('rejected')->select('COUNT(rejected.id)');

        return (int) $this->inRange($builder, $range)->getQuery()->getSingleScalarResult();
    }

    /**
     * Gives the query of the rejected rows in the range for the driving data page, which pages it.
     * The rows are the rows that the delete deletes. The rows with no date come last. The query
     * does not read the copies.
     *
     * @return Query<null, RejectedStopRow>
     */
    public function listInRange(ImportRangeDto $range): Query
    {
        return $this->inRange($this->createQueryBuilder('rejected'), $range)
            // The condition of the range has a subquery with the alias "batch".
            ->addSelect('upload', 'CASE WHEN rejected.serviceDate IS NULL THEN 1 ELSE 0 END AS HIDDEN noDate')
            ->join('rejected.importBatch', 'upload')
            ->orderBy('noDate')
            ->addOrderBy('rejected.serviceDate')
            ->addOrderBy('upload.id')
            ->addOrderBy('rejected.rowNumber')
            ->addOrderBy('rejected.id')
            ->getQuery();
    }

    /**
     * Gives the number of rejected rows of each date in the range. A row with no date is not in
     * the result, because it has no date to show.
     *
     * @return array<string, int> the counts in the order of the date, keyed by the date as Y-m-d
     */
    public function countByDate(DateRangeDto $range): array
    {
        /** @var list<array{serviceDate: \DateTimeImmutable, count: int|string}> $rows */
        $rows = $this->createQueryBuilder('rejected')
            ->select('rejected.serviceDate AS serviceDate', 'COUNT(rejected.id) AS count')
            ->where('rejected.serviceDate BETWEEN :first AND :last')
            ->setParameter('first', $range->firstDate, Types::DATE_IMMUTABLE)
            ->setParameter('last', $range->lastDate, Types::DATE_IMMUTABLE)
            ->groupBy('rejected.serviceDate')
            ->orderBy('rejected.serviceDate')
            ->getQuery()
            ->getArrayResult();
        $counts = [];
        foreach ($rows as $row) {
            // MariaDB can give a count as text.
            $counts[$row['serviceDate']->format('Y-m-d')] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Deletes the rejected rows in the range. A DQL delete does not delete the copies.
     *
     * @return int the number of deleted rows
     */
    public function deleteInRange(ImportRangeDto $range): int
    {
        /** @var int $deleted a DQL delete gives the number of deleted rows */
        $deleted = $this->inRange($this->createQueryBuilder('rejected')->delete(), $range)->getQuery()->execute();

        return $deleted;
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
