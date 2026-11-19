<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\SourceRowRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Deletes the copies of the imported rows that have no stop row and no rejected row. Two imports
 * of the same dates that overlap can leave such copies, and they hold personal data. A cron job on
 * the server runs it. See "Deployment" in the README.
 */
#[AsCommand(name: 'app:driving-data:delete-orphan-copies', description: 'Delete the copies of the imported rows that have no row')]
final readonly class DrivingDataDeleteOrphanCopiesCommand
{
    public function __construct(
        private SourceRowRepository $sourceRows,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->success(\sprintf('Deleted %d copies with no row.', $this->sourceRows->deleteOrphans()));

        return Command::SUCCESS;
    }
}
