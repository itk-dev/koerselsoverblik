<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\Route;
use App\Entity\RouteDayTotals;
use App\Report\DayTotalsDto;
use App\Repository\RouteDayTotalsRepository;
use App\Repository\RouteRunRepository;
use App\Repository\StopRowRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Builds the totals of each route and day of a date range again from the stored stop rows and
 * route runs. Run it after RouteBuilder. The totals are derived data, so they hold nothing that
 * the stop rows and the route runs do not hold. See "Pipeline" in docs/architecture.md.
 */
final readonly class RouteDayTotalsBuilder
{
    private const int SECONDS_IN_A_MINUTE = 60;

    /**
     * The highest delay of the band "more than the tolerance to 15 minutes".
     */
    private const int UP_TO_15_SECONDS = 15 * self::SECONDS_IN_A_MINUTE;

    /**
     * The highest delay of the band "more than 15 to 30 minutes".
     */
    private const int UP_TO_30_SECONDS = 30 * self::SECONDS_IN_A_MINUTE;

    /**
     * The number of rows to write before the entity manager forgets them, so that the memory use
     * does not grow with the range.
     */
    private const int GROUP_SIZE = 500;

    /**
     * @param int $toleranceMinutes the value of APP_PUNCTUALITY_TOLERANCE_MINUTES
     */
    public function __construct(
        private RouteDayTotalsRepository $totals,
        private StopRowRepository $stopRows,
        private RouteRunRepository $routeRuns,
        private EntityManagerInterface $entityManager,
        #[Autowire(param: 'app.punctuality_tolerance_minutes')]
        private int $toleranceMinutes,
    ) {
    }

    /**
     * Deletes the totals of the range, and builds one row for each route and date that has stop
     * rows or route runs in the range.
     *
     * @return int the number of built rows
     */
    public function rebuild(ImportRangeDto $range): int
    {
        $this->totals->deleteInRange($range);

        $onTimeSeconds = $this->toleranceMinutes * self::SECONDS_IN_A_MINUTE;
        /** @var array<string, array{route: int, serviceDate: \DateTimeImmutable, totals: DayTotalsDto}> $days keyed by the route id and the date */
        $days = [];
        $parts = array_merge(
            $this->stopRows->countDayTotals($range, $onTimeSeconds),
            $this->routeRuns->countDayTotals($range, $onTimeSeconds, self::UP_TO_15_SECONDS, self::UP_TO_30_SECONDS),
        );
        foreach ($parts as $part) {
            $key = implode('|', [$part['route'], $part['serviceDate']->format('Y-m-d')]);
            // A route and date has one part from the stops and one from the runs.
            $days[$key] = [
                'route' => $part['route'],
                'serviceDate' => $part['serviceDate'],
                'totals' => isset($days[$key]) ? $days[$key]['totals']->add($part['totals']) : $part['totals'],
            ];
        }

        foreach (array_chunk($days, self::GROUP_SIZE) as $group) {
            $this->write($group);
        }

        return \count($days);
    }

    /**
     * Saves the rows of a group, and then the entity manager forgets them. The routes stay managed.
     *
     * @param list<array{route: int, serviceDate: \DateTimeImmutable, totals: DayTotalsDto}> $days
     */
    private function write(array $days): void
    {
        $rows = [];
        foreach ($days as $day) {
            /** @var Route $route a stop row or a route run refers to the route, so the route exists */
            $route = $this->entityManager->getReference(Route::class, $day['route']);
            $row = new RouteDayTotals($route, $day['serviceDate'], $this->toleranceMinutes, $day['totals']);
            $this->entityManager->persist($row);
            $rows[] = $row;
        }
        $this->entityManager->flush();
        foreach ($rows as $row) {
            $this->entityManager->detach($row);
        }
    }
}
