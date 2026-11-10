<?php

declare(strict_types=1);

namespace App\Command;

use App\Import\Upload\UploadStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Deletes the upload files that wait for a confirmation for more than one hour. A cron job on the
 * server runs it. See "Deployment" in the README.
 */
#[AsCommand(name: 'app:driving-data:purge-uploads', description: 'Delete the upload files that are older than one hour')]
final readonly class DrivingDataPurgeUploadsCommand
{
    public function __construct(
        private UploadStore $uploads,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->success(\sprintf('Deleted %d upload files that are older than one hour.', $this->uploads->purge()));

        return Command::SUCCESS;
    }
}
