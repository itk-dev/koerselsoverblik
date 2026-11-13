<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RouteRun;
use App\Import\ImportRangeDto;
use App\Report\DateRangeDto;
use App\Report\SoloCountDto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RouteRun>
 */
class RouteRunRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RouteRun::class);
    }

    /**
     * Counts the runs of a date range that are not cancelled and have a passenger count, and the
     * real solo runs of them: the runs with at most one citizen in the car at a time.
     */
    public function countSoloRuns(DateRangeDto $range): SoloCountDto
    {
        /** @var array{runs: int|string, soloRuns: int|string|null} $row */
        $row = $this->createQueryBuilder('run')
            ->select('COUNT(run.id) AS runs', 'SUM(CASE WHEN run.peakPassengers <= 1 THEN 1 ELSE 0 END) AS soloRuns')
            ->where('run.serviceDate BETWEEN :first AND :last')
            ->andWhere('run.cancelled = false')
            ->andWhere('run.peakPassengers IS NOT NULL')
            ->setParameter('first', $range->firstDate, Types::DATE_IMMUTABLE)
            ->setParameter('last', $range->lastDate, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getSingleResult();

        // MariaDB gives a sum as text, and no sum when no row matches.
        return new SoloCountDto((int) $row['runs'], (int) $row['soloRuns']);
    }

    /**
     * Deletes the route runs in the range.
     */
    public function deleteInRange(ImportRangeDto $range): void
    {
        $this->createQueryBuilder('run')
            ->delete()
            ->where('run.serviceDate BETWEEN :first AND :last')
            ->setParameter('first', $range->firstDate, Types::DATE_IMMUTABLE)
            ->setParameter('last', $range->lastDate, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->execute();
    }

    /**
     * Counts the measured first pickups and last drop-offs of each route and date in the range,
     * and the last drop-offs in each delay band. A delay on the edge of a band is in the lower
     * band. A run with no delay at the stop is not measured there. The runs of an ignored
     * sub-agreement are not counted.
     *
     * @param int $onTimeSeconds the highest delay that is on time
     * @param int $upTo15Seconds the highest delay of the second band
     * @param int $upTo30Seconds the highest delay of the third band
     *
     * @return list<array{route: int, serviceDate: \DateTimeImmutable, firstPickupOnTime: int, firstPickupMeasured: int, lastDropOnTime: int, lastDropMeasured: int, lastDropUpTo15: int, lastDropUpTo30: int, lastDropOver30: int}>
     */
    public function countRunFigures(DateRangeDto $range, int $onTimeSeconds, int $upTo15Seconds, int $upTo30Seconds): array
    {
        $builder = $this->createQueryBuilder('run')
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
            )
            ->where('run.serviceDate BETWEEN :first AND :last')
            ->groupBy('run.route, run.serviceDate')
            ->setParameter('first', $range->firstDate, Types::DATE_IMMUTABLE)
            ->setParameter('last', $range->lastDate, Types::DATE_IMMUTABLE)
            ->setParameter('onTime', $onTimeSeconds)
            ->setParameter('upTo15', $upTo15Seconds)
            ->setParameter('upTo30', $upTo30Seconds);

        /** @var list<array{route: int|string, serviceDate: \DateTimeImmutable, firstPickupOnTime: int|string, firstPickupMeasured: int|string, lastDropOnTime: int|string, lastDropMeasured: int|string, lastDropUpTo15: int|string, lastDropUpTo30: int|string, lastDropOver30: int|string}> $rows */
        $rows = IgnoredSubAgreementFilter::apply($builder, 'run.route')
            ->getQuery()
            ->getArrayResult();

        // MariaDB gives a sum as text.
        return array_map(static fn (array $row): array => [
            'route' => (int) $row['route'],
            'serviceDate' => $row['serviceDate'],
            'firstPickupOnTime' => (int) $row['firstPickupOnTime'],
            'firstPickupMeasured' => (int) $row['firstPickupMeasured'],
            'lastDropOnTime' => (int) $row['lastDropOnTime'],
            'lastDropMeasured' => (int) $row['lastDropMeasured'],
            'lastDropUpTo15' => (int) $row['lastDropUpTo15'],
            'lastDropUpTo30' => (int) $row['lastDropUpTo30'],
            'lastDropOver30' => (int) $row['lastDropOver30'],
        ], $rows);
    }
}
