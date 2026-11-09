<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StopRow;
use App\Import\ImportRangeDto;
use App\Import\Mapping\StopStatus;
use App\Import\Mapping\StopType;
use App\Import\RunStopDto;
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

    /**
     * Gives the stops of each run on one date. The stops are in the order of their ids, so that a
     * run is built in the same way each time.
     *
     * @return array<int, array<int, list<RunStopDto>>> the stops, keyed by the route id and the block number
     */
    public function findRunStopsOn(\DateTimeImmutable $date): array
    {
        /** @var list<array{route: int|string, run: int, stopType: StopType, stopStatus: StopStatus, plannedTime: \DateTimeImmutable, actualTime: ?\DateTimeImmutable, delaySeconds: ?int}> $rows */
        $rows = $this->createQueryBuilder('row')
            ->select('IDENTITY(row.route) AS route', 'row.run', 'row.stopType', 'row.stopStatus', 'row.plannedTime', 'row.actualTime', 'row.delaySeconds')
            ->where('row.serviceDate = :date')
            ->orderBy('row.id')
            ->setParameter('date', $date, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getArrayResult();

        $stops = [];
        foreach ($rows as $row) {
            $stops[(int) $row['route']][$row['run']][] = new RunStopDto($row['stopType'], $row['stopStatus'], $row['plannedTime'], $row['actualTime'], $row['delaySeconds']);
        }

        return $stops;
    }
}
