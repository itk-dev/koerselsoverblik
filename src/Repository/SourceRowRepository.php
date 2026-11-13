<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RejectedStopRow;
use App\Entity\SourceRow;
use App\Entity\StopRow;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SourceRow>
 */
class SourceRowRepository extends ServiceEntityRepository
{
    /**
     * The number of ids in one delete query.
     */
    private const int DELETE_GROUP_SIZE = 1000;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SourceRow::class);
    }

    /**
     * Deletes each copy with no stop row and no rejected row. Two imports of the same dates that
     * overlap can leave such copies, because `StopRowRangeDeleter` reads the ids of the copies from
     * the snapshot of its transaction. The command `app:driving-data:delete-orphan-copies` calls
     * this. The delete reads all copies, so the import does not call it.
     *
     * @return int the number of deleted copies
     */
    public function deleteOrphans(): int
    {
        /** @var int $deleted a DQL delete gives the number of deleted rows */
        $deleted = $this->getEntityManager()->createQuery(
            'DELETE FROM '.SourceRow::class.' source'
            .' WHERE NOT EXISTS (SELECT row.id FROM '.StopRow::class.' row WHERE IDENTITY(row.sourceRow) = source.id)'
            .' AND NOT EXISTS (SELECT rejected.id FROM '.RejectedStopRow::class.' rejected WHERE IDENTITY(rejected.sourceRow) = source.id)',
        )->execute();

        return $deleted;
    }

    /**
     * Deletes the copies with the given ids. A DQL delete of the rows does not cascade, so
     * `StopRowRangeDeleter` calls this after it deletes the rows of a range. The delete uses the
     * primary key, so it does not read the copies of other rows. Each query gets a group of ids,
     * so that one query does not get too many ids.
     *
     * @param list<int> $ids
     *
     * @return int the number of deleted copies
     */
    public function deleteByIds(array $ids): int
    {
        $deleted = 0;
        foreach (array_chunk($ids, self::DELETE_GROUP_SIZE) as $group) {
            /** @var int $count a DQL delete gives the number of deleted rows */
            $count = $this->createQueryBuilder('source')
                ->delete()
                ->where('source.id IN (:ids)')
                ->setParameter('ids', $group, ArrayParameterType::INTEGER)
                ->getQuery()
                ->execute();
            $deleted += $count;
        }

        return $deleted;
    }
}
