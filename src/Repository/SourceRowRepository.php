<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SourceRow;
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
     * Deletes the copies with the given ids. A DQL delete of the rows does not cascade, so
     * `StopRowRangeDeleter` calls this after it deletes the rows of a range. The delete uses the
     * primary key, so it does not read the copies of other rows. Each query gets a group of ids, so that one query does not get too
     * many ids.
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
