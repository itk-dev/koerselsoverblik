<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StopRow;
use App\Import\ImportRangeDto;
use App\Import\Mapping\StopStatus;
use App\Import\Mapping\StopType;
use App\Import\RunStopDto;
use App\Report\DateRangeDto;
use App\Report\TripCountDto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
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
        $builder = $this->createQueryBuilder('row')->select('COUNT(row.id)');

        return (int) $this->inRange($builder, $range)->getQuery()->getSingleScalarResult();
    }

    /**
     * Gives the ids of the copies of the stop rows in the range. Read them before the delete,
     * because a deleted row no longer points to its copy.
     *
     * @return list<int>
     */
    public function sourceRowIdsInRange(ImportRangeDto $range): array
    {
        $builder = $this->createQueryBuilder('row')->select('IDENTITY(row.sourceRow)');
        /** @var list<int|string> $ids */
        $ids = $this->inRange($builder, $range)->getQuery()->getSingleColumnResult();

        // MariaDB can give an id as text.
        return array_map(static fn (int|string $id): int => (int) $id, $ids);
    }

    /**
     * Deletes the stop rows in the range, from all mappings. A DQL delete does not delete the
     * copies.
     *
     * @return int the number of deleted rows
     */
    public function deleteInRange(ImportRangeDto $range): int
    {
        /** @var int $deleted a DQL delete gives the number of deleted rows */
        $deleted = $this->inRange($this->createQueryBuilder('row')->delete(), $range)->getQuery()->execute();

        return $deleted;
    }

    /**
     * Limits the query to the stop rows in the range. The count, the ids and the delete use the
     * same condition, so they cannot differ.
     */
    private function inRange(QueryBuilder $builder, ImportRangeDto $range): QueryBuilder
    {
        return $builder
            ->where('row.serviceDate BETWEEN :first AND :last')
            ->setParameter('first', $range->firstDate, Types::DATE_IMMUTABLE)
            ->setParameter('last', $range->lastDate, Types::DATE_IMMUTABLE);
    }

    /**
     * Gives the stops of each run on one date. The stops are in the order of their ids, so that a
     * run is built in the same way each time.
     *
     * @return array<int, array<int, list<RunStopDto>>> the stops, keyed by the route id and the block number
     */
    public function findRunStopsOn(\DateTimeImmutable $date): array
    {
        /** @var list<array{route: int|string, run: int, stopType: StopType, stopStatus: StopStatus, plannedTime: \DateTimeImmutable, actualTime: ?\DateTimeImmutable, delaySeconds: ?int, passengersAfter: ?int}> $rows */
        $rows = $this->createQueryBuilder('row')
            ->select('IDENTITY(row.route) AS route', 'row.run', 'row.stopType', 'row.stopStatus', 'row.plannedTime', 'row.actualTime', 'row.delaySeconds', 'row.passengersAfter')
            ->where('row.serviceDate = :date')
            ->orderBy('row.id')
            ->setParameter('date', $date, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getArrayResult();

        $stops = [];
        foreach ($rows as $row) {
            $stops[(int) $row['route']][$row['run']][] = new RunStopDto($row['stopType'], $row['stopStatus'], $row['plannedTime'], $row['actualTime'], $row['delaySeconds'], $row['passengersAfter']);
        }

        return $stops;
    }

    /**
     * Counts the measured stops of each route, date and stop type in the range, and those of them
     * that are on time. A measured stop is completed, and it has a delay (an actual time). The
     * stops of an ignored sub-agreement are not counted.
     *
     * @param int $onTimeSeconds the highest delay that is on time
     *
     * @return list<array{route: int, serviceDate: \DateTimeImmutable, stopType: StopType, onTime: int, measured: int}>
     */
    public function countMeasuredStops(DateRangeDto $range, int $onTimeSeconds): array
    {
        $builder = $this->createQueryBuilder('row')
            ->select(
                'IDENTITY(row.route) AS route',
                'row.serviceDate AS serviceDate',
                'row.stopType AS stopType',
                'SUM(CASE WHEN row.delaySeconds <= :onTime THEN 1 ELSE 0 END) AS onTime',
                'COUNT(row.id) AS measured',
            )
            ->where('row.serviceDate BETWEEN :first AND :last')
            ->andWhere('row.stopStatus = :completed')
            ->andWhere('row.delaySeconds IS NOT NULL')
            ->groupBy('row.route, row.serviceDate, row.stopType')
            ->setParameter('first', $range->firstDate, Types::DATE_IMMUTABLE)
            ->setParameter('last', $range->lastDate, Types::DATE_IMMUTABLE)
            ->setParameter('completed', StopStatus::Completed)
            ->setParameter('onTime', $onTimeSeconds);

        /** @var list<array{route: int|string, serviceDate: \DateTimeImmutable, stopType: StopType, onTime: int|string, measured: int|string}> $rows */
        $rows = IgnoredSubAgreementFilter::apply($builder, 'row.route')
            ->getQuery()
            ->getArrayResult();

        // MariaDB gives a sum as text.
        return array_map(static fn (array $row): array => [
            'route' => (int) $row['route'],
            'serviceDate' => $row['serviceDate'],
            'stopType' => $row['stopType'],
            'onTime' => (int) $row['onTime'],
            'measured' => (int) $row['measured'],
        ], $rows);
    }

    /**
     * Counts the trips and the citizens of a date range. A trip is a pickup, with any status. A
     * citizen was driven if a pickup of the citizen is not cancelled. The pickups of an ignored
     * sub-agreement are not counted.
     */
    public function countTrips(DateRangeDto $range): TripCountDto
    {
        $builder = $this->createQueryBuilder('row')
            ->select(
                'COUNT(row.id) AS trips',
                'SUM(CASE WHEN row.stopStatus = :completed THEN 1 ELSE 0 END) AS completed',
                'SUM(CASE WHEN row.stopStatus = :cancelled THEN 1 ELSE 0 END) AS cancelled',
                'SUM(CASE WHEN row.stopStatus = :pending THEN 1 ELSE 0 END) AS pending',
                // COUNT ignores NULL, so a cancelled pickup gives no person id.
                'COUNT(DISTINCT CASE WHEN row.stopStatus = :cancelled THEN NULL ELSE row.personId END) AS citizens',
            )
            ->where('row.serviceDate BETWEEN :first AND :last')
            ->andWhere('row.stopType = :pickup')
            ->setParameter('first', $range->firstDate, Types::DATE_IMMUTABLE)
            ->setParameter('last', $range->lastDate, Types::DATE_IMMUTABLE)
            ->setParameter('pickup', StopType::Pickup)
            ->setParameter('completed', StopStatus::Completed)
            ->setParameter('cancelled', StopStatus::Cancelled)
            ->setParameter('pending', StopStatus::Pending);

        /** @var array{trips: int|string, completed: int|string|null, cancelled: int|string|null, pending: int|string|null, citizens: int|string} $row */
        $row = IgnoredSubAgreementFilter::apply($builder, 'row.route')
            ->getQuery()
            ->getSingleResult();

        // MariaDB gives a sum as text, and no sum when no row matches.
        return new TripCountDto(
            (int) $row['trips'],
            (int) $row['completed'],
            (int) $row['cancelled'],
            (int) $row['pending'],
            (int) $row['citizens'],
        );
    }
}
