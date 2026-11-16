<?php

declare(strict_types=1);

namespace App\Command;

use App\Import\ImportRangeDto;
use App\Import\MonthCitizensBuilder;
use App\Import\RouteDayTotalsBuilder;
use App\Repository\RouteDayTotalsRepository;
use App\Repository\StopRowRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Builds the totals of each route and day, and the citizens of each month, of all dates again.
 * Run it after the first deploy of the totals, and after a change of
 * APP_PUNCTUALITY_TOLERANCE_MINUTES. Each import builds the totals of its own range. See
 * "Deployment" in the README.
 */
#[AsCommand(name: 'app:driving-data:rebuild-totals', description: 'Build the totals of each route and day, and the citizens of each month, of all dates again')]
final readonly class DrivingDataRebuildTotalsCommand
{
    public function __construct(
        private StopRowRepository $stopRows,
        private RouteDayTotalsRepository $totals,
        private RouteDayTotalsBuilder $builder,
        private MonthCitizensBuilder $monthCitizens,
        private EntityManagerInterface $entityManager,
        #[Autowire(param: 'app.driving_data_max_days')]
        private int $maxDays,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        // One transaction, so that the pages never read a part of the totals.
        $built = $this->entityManager->wrapInTransaction(function (): int {
            $this->monthCitizens->rebuildAll();
            $this->totals->deleteAll();
            $span = $this->stopRows->dateSpan();
            if (null === $span) {
                return 0;
            }

            // The builder takes an import range, so it takes the dates in steps of the maximum.
            [$first, $last] = $span;
            $built = 0;
            while ($first <= $last) {
                $end = min($first->modify(\sprintf('+%d days', $this->maxDays - 1)), $last);
                $built += $this->builder->rebuild(ImportRangeDto::create($first, $end, $this->maxDays));
                $first = $end->modify('+1 day');
            }

            return $built;
        });

        $io->success(\sprintf('Built %d totals of a route and a day, and the citizens of each month.', $built));

        return Command::SUCCESS;
    }
}
