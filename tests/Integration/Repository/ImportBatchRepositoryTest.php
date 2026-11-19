<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\ImportBatch;
use App\Entity\User;
use App\Import\ImportRangeDto;
use App\Report\DateRangeDto;
use App\Repository\ImportBatchRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks which batches the upload page uses for the last upload of a date.
 */
final class ImportBatchRepositoryTest extends KernelTestCase
{
    /**
     * A batch overlaps the range when one or more of its dates are in the range. The newest batch
     * is first. Of two batches with the same time, the batch with the higher id is first.
     */
    public function testFindOverlappingGivesTheBatchesThatOverlapTheRangeNewestFirst(): void
    {
        $entityManager = $this->entityManager();
        $user = new User('admin@example.com');
        $entityManager->persist($user);
        foreach ([
            ['before', '2025-12-29', '2025-12-31', '10:00'],
            ['over the first date', '2025-12-30', '2026-01-02', '09:00'],
            ['in the range, first', '2026-01-05', '2026-01-06', '11:00'],
            ['in the range, second', '2026-01-05', '2026-01-06', '11:00'],
            ['in the range, older', '2026-01-05', '2026-01-06', '08:00'],
            ['over the last date', '2026-01-31', '2026-02-02', '10:00'],
            ['after', '2026-02-01', '2026-02-03', '12:00'],
        ] as [$fileName, $firstDate, $lastDate, $time]) {
            $entityManager->persist(new ImportBatch('default', $fileName, $user, new \DateTimeImmutable('2026-02-04 '.$time), ImportRangeDto::create(new \DateTimeImmutable($firstDate), new \DateTimeImmutable($lastDate), 31)));
        }
        $entityManager->flush();
        $entityManager->clear();

        $batches = $this->repository()->findOverlapping(DateRangeDto::create(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-01-31')));

        self::assertSame(
            ['in the range, second', 'in the range, first', 'over the last date', 'over the first date', 'in the range, older'],
            array_map(static fn (ImportBatch $batch): string => $batch->getFileName(), $batches),
        );
        self::assertSame('admin@example.com', $batches[0]->getUploadedBy()?->getUserIdentifier());
    }

    public function testFindOverlappingWithNoBatchesIsEmpty(): void
    {
        self::assertSame([], $this->repository()->findOverlapping(DateRangeDto::create(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-01-31'))));
    }

    /**
     * The batches stay after a clear, so the first date of the batches is the first date that can
     * have driving data.
     */
    public function testFirstDateGivesTheFirstDateOfTheBatches(): void
    {
        $entityManager = $this->entityManager();
        foreach ([['2026-01-05', '2026-01-06'], ['2025-12-30', '2026-01-02'], ['2026-02-01', '2026-02-03']] as [$firstDate, $lastDate]) {
            $entityManager->persist(new ImportBatch('default', 'extract.xlsx', null, new \DateTimeImmutable(), ImportRangeDto::create(new \DateTimeImmutable($firstDate), new \DateTimeImmutable($lastDate), 31)));
        }
        $entityManager->flush();

        self::assertEquals(new \DateTimeImmutable('2025-12-30'), $this->repository()->firstDate());
    }

    public function testFirstDateWithNoBatchesIsNull(): void
    {
        self::assertNull($this->repository()->firstDate());
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }

    private function repository(): ImportBatchRepository
    {
        $repository = static::getContainer()->get(ImportBatchRepository::class);
        self::assertInstanceOf(ImportBatchRepository::class, $repository);

        return $repository;
    }
}
