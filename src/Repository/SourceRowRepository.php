<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RejectedStopRow;
use App\Entity\SourceRow;
use App\Entity\StopRow;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SourceRow>
 */
class SourceRowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SourceRow::class);
    }

    /**
     * Deletes each copy with no stop row and no rejected row. A DQL delete of the rows does not
     * cascade, so the replacer calls this after it deletes the rows of a range.
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
}
