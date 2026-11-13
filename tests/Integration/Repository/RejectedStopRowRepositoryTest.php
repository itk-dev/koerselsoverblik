<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\ImportBatch;
use App\Entity\RejectedStopRow;
use App\Import\Extract\RejectedRowDto;
use App\Import\Extract\RejectionReason;
use App\Import\ImportRangeDto;
use App\Repository\RejectedStopRowRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks which rejected rows belong to a range. A row with a date belongs to the range of its
 * date. A row with no date belongs to the range when the whole range of its batch is in the range.
 */
final class RejectedStopRowRepositoryTest extends KernelTestCase
{
    public function testSourceRowIdsInRangeGivesTheCopiesOfTheRowsInTheRange(): void
    {
        $copies = $this->storeRows();

        $ids = $this->repository()->sourceRowIdsInRange(self::range());

        sort($ids);
        self::assertSame([$copies['first'], $copies['last'], $copies['no date, smaller batch']], $ids);
    }

    public function testSourceRowIdsInRangeWithNoRowsIsEmpty(): void
    {
        self::assertSame([], $this->repository()->sourceRowIdsInRange(self::range()));
    }

    public function testDeleteInRangeDeletesOnlyTheRowsInTheRange(): void
    {
        $this->storeRows();

        $this->repository()->deleteInRange(self::range());

        $kept = array_map(
            static fn (RejectedStopRow $row): mixed => $row->getSourceRow()->getValues()['name'],
            $this->repository()->findBy([], ['id' => 'ASC']),
        );
        self::assertSame(['before', 'after', 'no date, larger batch'], $kept);
    }

    /**
     * A range after all rows and after the ranges of all batches holds no row, also no row with no
     * date.
     */
    public function testDeleteInRangeWithNoRowsInTheRangeDeletesNothing(): void
    {
        $this->storeRows();

        $this->repository()->deleteInRange(ImportRangeDto::create(new \DateTimeImmutable('2026-01-10'), new \DateTimeImmutable('2026-01-11'), 31));

        self::assertSame(6, $this->repository()->count([]));
    }

    /**
     * Stores a rejected row inside, on and outside the edges of the range, and a row with no date
     * of a batch in the range and of a batch that is larger than the range.
     *
     * @return array<string, int> the ids of the copies, keyed by the name of the row
     */
    private function storeRows(): array
    {
        $entityManager = $this->entityManager();
        $smaller = self::batch('2026-01-05', '2026-01-06');
        $larger = self::batch('2026-01-04', '2026-01-06');
        $rows = [
            'before' => new RejectedStopRow(self::rowDto('before', '2026-01-04'), $larger),
            'first' => new RejectedStopRow(self::rowDto('first', '2026-01-05'), $smaller),
            'last' => new RejectedStopRow(self::rowDto('last', '2026-01-06'), $smaller),
            'after' => new RejectedStopRow(self::rowDto('after', '2026-01-07'), $larger),
            'no date, smaller batch' => new RejectedStopRow(self::rowDto('no date, smaller batch', null), $smaller),
            'no date, larger batch' => new RejectedStopRow(self::rowDto('no date, larger batch', null), $larger),
        ];
        foreach ([$smaller, $larger, ...array_values($rows)] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $copies = array_map(static fn (RejectedStopRow $row): int => (int) $row->getSourceRow()->getId(), $rows);
        $entityManager->clear();

        return $copies;
    }

    private static function rowDto(string $name, ?string $date): RejectedRowDto
    {
        return new RejectedRowDto(5, RejectionReason::InvalidRowId, null, null === $date ? null : new \DateTimeImmutable($date), ['name' => $name]);
    }

    private static function batch(string $firstDate, string $lastDate): ImportBatch
    {
        return new ImportBatch('default', 'extract.xlsx', null, new \DateTimeImmutable(), ImportRangeDto::create(new \DateTimeImmutable($firstDate), new \DateTimeImmutable($lastDate), 31));
    }

    private static function range(): ImportRangeDto
    {
        return ImportRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-06'), 31);
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }

    private function repository(): RejectedStopRowRepository
    {
        $repository = static::getContainer()->get(RejectedStopRowRepository::class);
        self::assertInstanceOf(RejectedStopRowRepository::class, $repository);

        return $repository;
    }
}
