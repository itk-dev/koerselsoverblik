<?php

namespace App\Import;

use App\Entity\Route;
use App\Entity\RouteRun;
use App\Entity\StopRow;
use App\Import\Mapping\StopStatus;
use App\Import\Mapping\StopType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Builds the route runs of a date range again from the stored stop rows. A route run is derived
 * data, so it holds nothing that the stop rows do not hold. See "Pipeline" in
 * docs/architecture.md.
 */
final readonly class RouteBuilder
{
    /**
     * The number of runs to write before the entity manager forgets them, so that the memory use
     * does not grow with the range.
     */
    private const int GROUP_SIZE = 500;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private RouteRunCalculator $calculator,
    ) {
    }

    /**
     * Deletes the route runs of the range, and builds one run for each route, date and block
     * number of the stop rows in the range.
     *
     * @return int the number of built runs
     */
    public function rebuild(ImportRangeDto $range): int
    {
        $this->entityManager->createQuery('DELETE FROM '.RouteRun::class.' run WHERE run.serviceDate BETWEEN :first AND :last')
            ->setParameter('first', $range->firstDate, Types::DATE_IMMUTABLE)
            ->setParameter('last', $range->lastDate, Types::DATE_IMMUTABLE)
            ->execute();

        $built = 0;
        /** @var list<RouteRun> $written the runs since the last flush */
        $written = [];
        foreach ($range->dates() as $date) {
            foreach ($this->runsOn(new \DateTimeImmutable($date)) as $run) {
                $written[] = $run;
                ++$built;
                if (\count($written) >= self::GROUP_SIZE) {
                    $this->write($written);
                    $written = [];
                }
            }
        }
        $this->write($written);

        return $built;
    }

    /**
     * Reads the stop rows of one date, so that only one date is in memory. The rows are in the
     * order of their ids, so that the calculator gives the same run each time.
     *
     * @return list<RouteRun>
     */
    private function runsOn(\DateTimeImmutable $date): array
    {
        /** @var list<array{route: int|string, run: int, stopType: StopType, stopStatus: StopStatus, plannedTime: \DateTimeImmutable, actualTime: ?\DateTimeImmutable, delaySeconds: ?int}> $rows */
        $rows = $this->entityManager->createQuery(
            'SELECT IDENTITY(row.route) AS route, row.run, row.stopType, row.stopStatus, row.plannedTime, row.actualTime, row.delaySeconds'
            .' FROM '.StopRow::class.' row WHERE row.serviceDate = :date ORDER BY row.id',
        )
            ->setParameter('date', $date, Types::DATE_IMMUTABLE)
            ->getArrayResult();

        /** @var array<string, list<RunStopDto>> $stops the stops of each run, keyed by the route id and the block number */
        $stops = [];
        foreach ($rows as $row) {
            $stops[$row['route'].'/'.$row['run']][] = new RunStopDto($row['stopType'], $row['stopStatus'], $row['plannedTime'], $row['actualTime'], $row['delaySeconds']);
        }

        $runs = [];
        foreach ($stops as $key => $runStops) {
            [$routeId, $run] = array_map(intval(...), explode('/', $key));
            /** @var Route $route a stop row refers to the route, so the route exists */
            $route = $this->entityManager->getReference(Route::class, $routeId);
            $runs[] = new RouteRun($route, $date, $run, $this->calculator->calculate($runStops));
        }

        return $runs;
    }

    /**
     * Saves the runs, and then the entity manager forgets them. The routes and the batch stay
     * managed.
     *
     * @param list<RouteRun> $runs
     */
    private function write(array $runs): void
    {
        foreach ($runs as $run) {
            $this->entityManager->persist($run);
        }
        $this->entityManager->flush();
        foreach ($runs as $run) {
            $this->entityManager->detach($run);
        }
    }
}
