<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StopRow;
use App\Import\ImportRangeDto;
use App\Import\Mapping\StopStatus;
use App\Import\Mapping\StopType;
use App\Import\RunStopDto;
use App\Report\DateRangeDto;
use App\Report\DayTotalsDto;
use App\Report\PunctualityCountDto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StopRow>
 */
class StopRowRepository extends ServiceEntityRepository
{
    /**
     * The counters of DayTotalsDto that countDayTotals() gives.
     */
    private const array DAY_TOTALS_FIELDS = [
        'pickupOnTime',
        'pickupMeasured',
        'dropOffOnTime',
        'dropOffMeasured',
        'trips',
        'completedTrips',
        'cancelledTrips',
        'pendingTrips',
        'cancelledInTimeTrips',
        'cancelledLateTrips',
    ];

    public function __construct(
        ManagerRegistry $registry,
        private readonly CountedRouteFilter $countedRoutes,
    ) {
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
     * Gives the query of the stop rows in the range for the driving data page, which pages it.
     * The query does not read the copies.
     *
     * @param string|null $route the route name, or null for all routes. MariaDB compares it with no regard to case
     *
     * @return Query<null, StopRow>
     */
    public function listInRange(ImportRangeDto $range, ?string $route): Query
    {
        return $this->withRoute($this->inRange($this->createQueryBuilder('row'), $range), $route)
            // The page marks the rows of an ignored sub-agreement.
            ->addSelect('route', 'subAgreement', 'batch')
            ->join('route.subAgreement', 'subAgreement')
            ->join('row.importBatch', 'batch')
            ->orderBy('row.serviceDate')
            ->addOrderBy('route.sourceKey')
            ->addOrderBy('row.run')
            ->addOrderBy('row.plannedTime')
            ->addOrderBy('row.id')
            ->getQuery();
    }

    /**
     * Gives the number of stop rows in the range that the figures do not count, because the
     * sub-agreement of the route is ignored.
     *
     * @param string|null $route the route name, or null for all routes
     */
    public function countIgnoredInRange(ImportRangeDto $range, ?string $route): int
    {
        $builder = $this->withRoute($this->inRange($this->createQueryBuilder('row')->select('COUNT(row.id)'), $range), $route);

        return (int) $this->countedRoutes->applyIgnored($builder, 'row.route')->getQuery()->getSingleScalarResult();
    }

    /**
     * Joins the route, and limits the query to one route if a route name is given.
     */
    private function withRoute(QueryBuilder $builder, ?string $route): QueryBuilder
    {
        $builder->join('row.route', 'route');
        if (null !== $route) {
            $builder->andWhere('route.sourceKey = :route')->setParameter('route', $route);
        }

        return $builder;
    }

    /**
     * Gives the copies of the stop rows of the date, with their row numbers, in the order of the
     * import, for a new read through a mapping (ADR 006).
     *
     * @return list<array{rowNumber: ?int, values: array<array-key, mixed>}> the row number of the file, or null for a row that was imported before the number was stored, and the values of the copy keyed by the column name
     */
    public function copiesOn(\DateTimeImmutable $date): array
    {
        // Doctrine converts the JSON of the copy to an array, with the type of the field.
        /** @var list<array{rowNumber: int|string|null, values: array<array-key, mixed>}> $rows */
        $rows = $this->createQueryBuilder('row')
            ->select('row.rowNumber AS rowNumber', 'copy.values AS values')
            ->join('row.sourceRow', 'copy')
            ->where('row.serviceDate = :date')
            ->orderBy('row.id')
            ->setParameter('date', $date, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getArrayResult();

        // MariaDB can give a number as text.
        return array_map(static fn (array $row): array => ['rowNumber' => null === $row['rowNumber'] ? null : (int) $row['rowNumber'], 'values' => $row['values']], $rows);
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
     * Gives the number of stop rows of each date in the range, from all mappings. A date with no
     * stop rows is not in the result.
     *
     * @return array<string, int> the counts in the order of the date, keyed by the date as Y-m-d
     */
    public function countByDate(DateRangeDto $range): array
    {
        return self::countsByDate($this->countByDateBuilder($range));
    }

    /**
     * Gives the number of stop rows of each date in the range that the figures do not count,
     * because the sub-agreement of the route is ignored. A date with no such rows is not in the
     * result.
     *
     * @return array<string, int> the counts in the order of the date, keyed by the date as Y-m-d
     */
    public function countIgnoredByDate(DateRangeDto $range): array
    {
        return self::countsByDate($this->countedRoutes->applyIgnored($this->countByDateBuilder($range), 'row.route'));
    }

    private function countByDateBuilder(DateRangeDto $range): QueryBuilder
    {
        return $this->inRange($this->createQueryBuilder('row'), $range)
            ->select('row.serviceDate AS serviceDate', 'COUNT(row.id) AS count')
            ->groupBy('row.serviceDate')
            ->orderBy('row.serviceDate');
    }

    /**
     * @return array<string, int> the counts in the order of the date, keyed by the date as Y-m-d
     */
    private static function countsByDate(QueryBuilder $builder): array
    {
        /** @var list<array{serviceDate: \DateTimeImmutable, count: int|string}> $rows */
        $rows = $builder->getQuery()->getArrayResult();
        $counts = [];
        foreach ($rows as $row) {
            // MariaDB can give a count as text.
            $counts[$row['serviceDate']->format('Y-m-d')] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Limits the query to the stop rows in the range. The count, the ids, the delete and the
     * counters use the same condition, so they cannot differ.
     */
    private function inRange(QueryBuilder $builder, ImportRangeDto|DateRangeDto $range): QueryBuilder
    {
        return $builder
            ->where('row.serviceDate BETWEEN :first AND :last')
            ->setParameter('first', $range->firstDate, Types::DATE_IMMUTABLE)
            ->setParameter('last', $range->lastDate, Types::DATE_IMMUTABLE);
    }

    /**
     * Gives the first and the last date of the stored stop rows, or null if there are no stop
     * rows.
     *
     * @return ?array{\DateTimeImmutable, \DateTimeImmutable}
     */
    public function dateSpan(): ?array
    {
        /** @var array{first: ?string, last: ?string} $row */
        $row = $this->createQueryBuilder('row')
            ->select('MIN(row.serviceDate) AS first', 'MAX(row.serviceDate) AS last')
            ->getQuery()
            ->getSingleResult();

        return null === $row['first'] || null === $row['last']
            ? null
            : [new \DateTimeImmutable($row['first']), new \DateTimeImmutable($row['last'])];
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
     * Counts the stop counters of each route and date in the range, for all routes. The counters
     * of the route runs are 0. RouteDayTotalsBuilder stores them.
     *
     * - A measured stop is completed, and it has a delay (an actual time). It is on time if the
     *   delay is at most the tolerance.
     * - A trip is a pickup, with any status: completed, cancelled or with no registration.
     * - A cancelled trip was cancelled in time or too late, from the code of the extract. The
     *   extract gives the code also for a stop that is not cancelled, so only the cancelled
     *   pickups count. A cancelled pickup with no code counts for neither.
     *
     * @param int $onTimeSeconds the highest delay that is on time
     *
     * @return list<array{route: int, serviceDate: \DateTimeImmutable, totals: DayTotalsDto}>
     */
    public function countDayTotals(ImportRangeDto $range, int $onTimeSeconds): array
    {
        $measured = 'row.stopType = :%s AND row.stopStatus = :completed AND row.delaySeconds IS NOT NULL';
        $onTime = $measured.' AND row.delaySeconds <= :onTime';
        $builder = $this->createQueryBuilder('row')
            ->select(
                'IDENTITY(row.route) AS route',
                'row.serviceDate AS serviceDate',
                \sprintf('SUM(CASE WHEN %s THEN 1 ELSE 0 END) AS pickupOnTime', \sprintf($onTime, 'pickup')),
                \sprintf('SUM(CASE WHEN %s THEN 1 ELSE 0 END) AS pickupMeasured', \sprintf($measured, 'pickup')),
                \sprintf('SUM(CASE WHEN %s THEN 1 ELSE 0 END) AS dropOffOnTime', \sprintf($onTime, 'dropOff')),
                \sprintf('SUM(CASE WHEN %s THEN 1 ELSE 0 END) AS dropOffMeasured', \sprintf($measured, 'dropOff')),
                'SUM(CASE WHEN row.stopType = :pickup THEN 1 ELSE 0 END) AS trips',
                'SUM(CASE WHEN row.stopType = :pickup AND row.stopStatus = :completed THEN 1 ELSE 0 END) AS completedTrips',
                'SUM(CASE WHEN row.stopType = :pickup AND row.stopStatus = :cancelled THEN 1 ELSE 0 END) AS cancelledTrips',
                'SUM(CASE WHEN row.stopType = :pickup AND row.stopStatus = :pending THEN 1 ELSE 0 END) AS pendingTrips',
                'SUM(CASE WHEN row.stopType = :pickup AND row.stopStatus = :cancelled AND row.cancelledInTime = true THEN 1 ELSE 0 END) AS cancelledInTimeTrips',
                'SUM(CASE WHEN row.stopType = :pickup AND row.stopStatus = :cancelled AND row.cancelledInTime = false THEN 1 ELSE 0 END) AS cancelledLateTrips',
            )
            ->groupBy('row.route, row.serviceDate')
            ->setParameter('pickup', StopType::Pickup)
            ->setParameter('dropOff', StopType::DropOff)
            ->setParameter('completed', StopStatus::Completed)
            ->setParameter('cancelled', StopStatus::Cancelled)
            ->setParameter('pending', StopStatus::Pending)
            ->setParameter('onTime', $onTimeSeconds);

        /** @var list<array<string, mixed>&array{route: int|string, serviceDate: \DateTimeImmutable}> $rows */
        $rows = $this->inRange($builder, $range)->getQuery()->getArrayResult();

        return array_map(static fn (array $row): array => [
            // MariaDB can give an id as text.
            'route' => (int) $row['route'],
            'serviceDate' => $row['serviceDate'],
            'totals' => DayTotalsDto::fromRow($row, self::DAY_TOTALS_FIELDS),
        ], $rows);
    }

    /**
     * Counts the measured stops of each location, route and date in the range, for all routes.
     * LocationDayTotalsBuilder stores them.
     *
     * - A measured stop is a completed pickup or drop-off with a delay (an actual time), as in the
     *   measures "pr. borger". It is on time if the delay is at most the tolerance.
     * - A stop with no location name is not counted.
     *
     * @param int $onTimeSeconds the highest delay that is on time
     *
     * @return list<array{route: int, serviceDate: \DateTimeImmutable, location: string, stops: PunctualityCountDto}>
     */
    public function countLocationDayTotals(ImportRangeDto $range, int $onTimeSeconds): array
    {
        $builder = $this->createQueryBuilder('row')
            ->select(
                'IDENTITY(row.route) AS route',
                'row.serviceDate AS serviceDate',
                'row.locationName AS location',
                'SUM(CASE WHEN row.delaySeconds <= :onTime THEN 1 ELSE 0 END) AS onTime',
                'COUNT(row.id) AS measured',
            );
        $this->inRange($builder, $range)
            ->andWhere('row.locationName IS NOT NULL')
            ->andWhere('row.stopStatus = :completed')
            ->andWhere('row.delaySeconds IS NOT NULL')
            ->groupBy('row.route, row.serviceDate, row.locationName')
            ->setParameter('completed', StopStatus::Completed)
            ->setParameter('onTime', $onTimeSeconds);

        /** @var list<array{route: int|string, serviceDate: \DateTimeImmutable, location: string, onTime: int|string, measured: int|string}> $rows */
        $rows = $builder->getQuery()->getArrayResult();

        return array_map(static fn (array $row): array => [
            // MariaDB can give an id and a sum as text.
            'route' => (int) $row['route'],
            'serviceDate' => $row['serviceDate'],
            'location' => $row['location'],
            'stops' => new PunctualityCountDto((int) $row['onTime'], (int) $row['measured']),
        ], $rows);
    }

    /**
     * Counts the citizens who were driven in the range: the distinct person ids of the pickups
     * that are not cancelled. A pickup with no registration counts. The pickups of an ignored
     * sub-agreement are not counted.
     *
     * @param ?int $supplierId the id of a supplier, or null for all suppliers
     */
    public function countCitizens(DateRangeDto $range, ?int $supplierId = null): int
    {
        return (int) $this->citizensQuery($range, $supplierId)->getQuery()->getSingleScalarResult();
    }

    /**
     * Counts the citizens of each month in the range, with the rules of countCitizens(). The
     * citizens of a month are the citizens who were driven in that month. A month with no
     * citizens has no item.
     *
     * @param ?int $supplierId the id of a supplier, or null for all suppliers
     *
     * @return array<string, int> keyed by the month as YYYY-MM, as MonthDto::query()
     */
    public function countCitizensByMonth(DateRangeDto $range, ?int $supplierId = null): array
    {
        /** @var list<array{month: string, citizens: int|string}> $rows */
        $rows = $this->citizensQuery($range, $supplierId)
            ->addSelect(MonthKey::of('row.serviceDate').' AS month')
            ->groupBy('month')
            ->getQuery()
            ->getArrayResult();

        $citizens = [];
        foreach ($rows as $row) {
            $citizens[$row['month']] = (int) $row['citizens'];
        }

        return $citizens;
    }

    private function citizensQuery(DateRangeDto $range, ?int $supplierId): QueryBuilder
    {
        // COUNT ignores NULL, so a pickup with no person id does not count.
        $builder = $this->createQueryBuilder('row')
            ->select('COUNT(DISTINCT row.personId) AS citizens')
            ->where('row.serviceDate BETWEEN :first AND :last')
            ->andWhere('row.stopType = :pickup')
            ->andWhere('row.stopStatus <> :cancelled')
            ->setParameter('first', $range->firstDate, Types::DATE_IMMUTABLE)
            ->setParameter('last', $range->lastDate, Types::DATE_IMMUTABLE)
            ->setParameter('pickup', StopType::Pickup)
            ->setParameter('cancelled', StopStatus::Cancelled);

        return $this->countedRoutes->apply($builder, 'row.route', $supplierId);
    }
}
