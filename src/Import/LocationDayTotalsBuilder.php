<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\LocationDayTotals;
use App\Entity\Route;
use App\Repository\LocationDayTotalsRepository;
use App\Repository\StopRowRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Builds the measured stops of each location, route and day of a date range again from the stored
 * stop rows. The rows are derived data, so they hold nothing that the stop rows do not hold. See
 * "Pipeline" in docs/architecture.md.
 */
final readonly class LocationDayTotalsBuilder
{
    private const int SECONDS_IN_A_MINUTE = 60;

    /**
     * The number of rows to write before the entity manager forgets them, so that the memory use
     * does not grow with the range.
     */
    private const int GROUP_SIZE = 500;

    /**
     * @param int $toleranceMinutes the value of APP_PUNCTUALITY_TOLERANCE_MINUTES
     */
    public function __construct(
        private LocationDayTotalsRepository $totals,
        private StopRowRepository $stopRows,
        private EntityManagerInterface $entityManager,
        #[Autowire(param: 'app.punctuality_tolerance_minutes')]
        private int $toleranceMinutes,
    ) {
    }

    /**
     * Deletes the rows of the range, and builds one row for each location, route and date that
     * has measured stops in the range.
     *
     * @return int the number of built rows
     */
    public function rebuild(ImportRangeDto $range): int
    {
        $this->totals->deleteInRange($range);

        $counts = $this->stopRows->countLocationDayTotals($range, $this->toleranceMinutes * self::SECONDS_IN_A_MINUTE);
        /** @var list<LocationDayTotals> $written the rows since the last flush */
        $written = [];
        foreach ($counts as $count) {
            /** @var Route $route a stop row refers to the route, so the route exists */
            $route = $this->entityManager->getReference(Route::class, $count['route']);
            $written[] = new LocationDayTotals($route, $count['serviceDate'], $count['location'], $this->toleranceMinutes, $count['stops']);
            if (\count($written) >= self::GROUP_SIZE) {
                $this->write($written);
                $written = [];
            }
        }
        $this->write($written);

        return \count($counts);
    }

    /**
     * Saves the rows, and then the entity manager forgets them. The routes stay managed.
     *
     * @param list<LocationDayTotals> $rows
     */
    private function write(array $rows): void
    {
        foreach ($rows as $row) {
            $this->entityManager->persist($row);
        }
        $this->entityManager->flush();
        foreach ($rows as $row) {
            $this->entityManager->detach($row);
        }
    }
}
