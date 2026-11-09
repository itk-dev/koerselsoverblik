<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\Route;
use App\Entity\RouteRun;
use App\Repository\RouteRunRepository;
use App\Repository\StopRowRepository;
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
        private RouteRunRepository $routeRuns,
        private StopRowRepository $stopRows,
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
        $this->routeRuns->deleteInRange($range);

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
     * Builds the runs of one date, so that only one date is in memory.
     *
     * @return list<RouteRun>
     */
    private function runsOn(\DateTimeImmutable $date): array
    {
        $runs = [];
        foreach ($this->stopRows->findRunStopsOn($date) as $routeId => $stopsOfRoute) {
            /** @var Route $route a stop row refers to the route, so the route exists */
            $route = $this->entityManager->getReference(Route::class, $routeId);
            foreach ($stopsOfRoute as $run => $stops) {
                $runs[] = new RouteRun($route, $date, $run, $this->calculator->calculate($stops));
            }
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
