<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RouteRun;
use App\Import\ImportRangeDto;
use App\Report\DateRangeDto;
use App\Report\DayTotalsDto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RouteRun>
 */
class RouteRunRepository extends ServiceEntityRepository
{
    /**
     * The counters of DayTotalsDto that countDayTotals() gives.
     */
    private const array DAY_TOTALS_FIELDS = [
        'firstPickupOnTime',
        'firstPickupMeasured',
        'lastDropOnTime',
        'lastDropMeasured',
        'lastDropUpTo15',
        'lastDropUpTo30',
        'lastDropOver30',
        'runs',
        'soloRuns',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RouteRun::class);
    }

    /**
     * Gives the number of route runs in the range. The clear shows it before the delete.
     */
    public function countInRange(ImportRangeDto $range): int
    {
        $builder = $this->createQueryBuilder('run')->select('COUNT(run.id)');

        return (int) $this->inRange($builder, $range)->getQuery()->getSingleScalarResult();
    }

    /**
     * Gives the number of route runs of each date in the range. A date with no runs is not in the
     * result.
     *
     * @return array<string, int> the counts in the order of the date, keyed by the date as Y-m-d
     */
    public function countByDate(DateRangeDto $range): array
    {
        $builder = $this->createQueryBuilder('run')
            ->select('run.serviceDate AS serviceDate', 'COUNT(run.id) AS count')
            ->groupBy('run.serviceDate')
            ->orderBy('run.serviceDate');

        /** @var list<array{serviceDate: \DateTimeImmutable, count: int|string}> $rows */
        $rows = $this->inRange($builder, $range)->getQuery()->getArrayResult();
        $counts = [];
        foreach ($rows as $row) {
            // MariaDB can give a count as text.
            $counts[$row['serviceDate']->format('Y-m-d')] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Deletes the route runs in the range.
     */
    public function deleteInRange(ImportRangeDto $range): void
    {
        $this->inRange($this->createQueryBuilder('run')->delete(), $range)->getQuery()->execute();
    }

    /**
     * Limits the query to the route runs in the range. The counts and the delete use the same
     * condition, so they cannot differ.
     */
    private function inRange(QueryBuilder $builder, ImportRangeDto|DateRangeDto $range): QueryBuilder
    {
        return $builder
            ->where('run.serviceDate BETWEEN :first AND :last')
            ->setParameter('first', $range->firstDate, Types::DATE_IMMUTABLE)
            ->setParameter('last', $range->lastDate, Types::DATE_IMMUTABLE);
    }

    /**
     * Counts the run counters of each route and date in the range, for all routes. The counters
     * of the stops are 0. RouteDayTotalsBuilder stores them.
     *
     * - A run is measured at its first pickup or its last drop-off if it has a delay there. It is
     *   on time if the delay is at most the tolerance.
     * - A late last drop-off is in one delay band. A delay on the edge of a band is in the lower
     *   band.
     * - A run counts for the solo runs if it is not cancelled and had at least one citizen in the
     *   car. A run with a peak of 0 or no peak is neither solo nor shared. A real solo run had
     *   exactly one citizen in the car at a time.
     *
     * @param int $onTimeSeconds the highest delay that is on time
     * @param int $upTo15Seconds the highest delay of the second band
     * @param int $upTo30Seconds the highest delay of the third band
     *
     * @return list<array{route: int, serviceDate: \DateTimeImmutable, totals: DayTotalsDto}>
     */
    public function countDayTotals(ImportRangeDto $range, int $onTimeSeconds, int $upTo15Seconds, int $upTo30Seconds): array
    {
        /** @var list<array<string, mixed>&array{route: int|string, serviceDate: \DateTimeImmutable}> $rows */
        $rows = $this->createQueryBuilder('run')
            ->select(
                'IDENTITY(run.route) AS route',
                'run.serviceDate AS serviceDate',
                'SUM(CASE WHEN run.firstPickupDelaySeconds <= :onTime THEN 1 ELSE 0 END) AS firstPickupOnTime',
                'COUNT(run.firstPickupDelaySeconds) AS firstPickupMeasured',
                'SUM(CASE WHEN run.lastDropDelaySeconds <= :onTime THEN 1 ELSE 0 END) AS lastDropOnTime',
                'COUNT(run.lastDropDelaySeconds) AS lastDropMeasured',
                'SUM(CASE WHEN run.lastDropDelaySeconds > :onTime AND run.lastDropDelaySeconds <= :upTo15 THEN 1 ELSE 0 END) AS lastDropUpTo15',
                'SUM(CASE WHEN run.lastDropDelaySeconds > :upTo15 AND run.lastDropDelaySeconds <= :upTo30 THEN 1 ELSE 0 END) AS lastDropUpTo30',
                'SUM(CASE WHEN run.lastDropDelaySeconds > :upTo30 THEN 1 ELSE 0 END) AS lastDropOver30',
                'SUM(CASE WHEN run.cancelled = false AND run.peakPassengers >= 1 THEN 1 ELSE 0 END) AS runs',
                'SUM(CASE WHEN run.cancelled = false AND run.peakPassengers = 1 THEN 1 ELSE 0 END) AS soloRuns',
            )
            ->where('run.serviceDate BETWEEN :first AND :last')
            ->groupBy('run.route, run.serviceDate')
            ->setParameter('first', $range->firstDate, Types::DATE_IMMUTABLE)
            ->setParameter('last', $range->lastDate, Types::DATE_IMMUTABLE)
            ->setParameter('onTime', $onTimeSeconds)
            ->setParameter('upTo15', $upTo15Seconds)
            ->setParameter('upTo30', $upTo30Seconds)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => [
            // MariaDB can give an id as text.
            'route' => (int) $row['route'],
            'serviceDate' => $row['serviceDate'],
            'totals' => DayTotalsDto::fromRow($row, self::DAY_TOTALS_FIELDS),
        ], $rows);
    }
}
