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
     * that are on time. A measured stop is completed, and it has a delay (an actual time).
     *
     * @param int $onTimeSeconds the highest delay that is on time
     *
     * @return list<array{route: int, serviceDate: \DateTimeImmutable, stopType: StopType, onTime: int, measured: int}>
     */
    public function countMeasuredStops(DateRangeDto $range, int $onTimeSeconds): array
    {
        /** @var list<array{route: int|string, serviceDate: \DateTimeImmutable, stopType: StopType, onTime: int|string, measured: int|string}> $rows */
        $rows = $this->createQueryBuilder('row')
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
            ->setParameter('onTime', $onTimeSeconds)
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
     * Counts the trips and the cancelled trips of each route in a date range.
     *
     * @return list<array{route: int, trips: int, cancelled: int}>
     */
    public function countTripsOfEachRoute(DateRangeDto $range): array
    {
        /** @var list<array{route: int|string, trips: int|string, cancelled: int|string}> $rows */
        $rows = $this->createQueryBuilder('row')
            ->select(
                'IDENTITY(row.route) AS route',
                'COUNT(row.id) AS trips',
                'SUM(CASE WHEN row.stopStatus = :cancelled THEN 1 ELSE 0 END) AS cancelled',
            )
            ->where('row.serviceDate BETWEEN :first AND :last')
            ->andWhere('row.stopType = :pickup')
            ->groupBy('row.route')
            ->setParameter('first', $range->firstDate, Types::DATE_IMMUTABLE)
            ->setParameter('last', $range->lastDate, Types::DATE_IMMUTABLE)
            ->setParameter('pickup', StopType::Pickup)
            ->setParameter('cancelled', StopStatus::Cancelled)
            ->getQuery()
            ->getArrayResult();

        // MariaDB gives a sum as text.
        return array_map(static fn (array $row): array => [
            'route' => (int) $row['route'],
            'trips' => (int) $row['trips'],
            'cancelled' => (int) $row['cancelled'],
        ], $rows);
    }

    /**
     * Counts the trips and the citizens of a date range. A trip is a pickup, with any status. A
     * citizen was driven if a pickup of the citizen is not cancelled.
     */
    public function countTrips(DateRangeDto $range, ?int $supplierId = null): TripCountDto
    {
        $query = $this->createQueryBuilder('row')
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
        SupplierFilter::apply($query, 'row', $supplierId);
        /** @var array{trips: int|string, completed: int|string|null, cancelled: int|string|null, pending: int|string|null, citizens: int|string} $row */
        $row = $query->getQuery()->getSingleResult();

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
