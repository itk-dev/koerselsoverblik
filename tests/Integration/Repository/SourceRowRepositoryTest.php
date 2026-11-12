<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\SourceRow;
use App\Repository\SourceRowRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks that the deleter can delete the copies of the deleted rows by their ids, and that the
 * other copies stay.
 */
final class SourceRowRepositoryTest extends KernelTestCase
{
    public function testDeleteByIdsDeletesOnlyTheGivenCopies(): void
    {
        $ids = $this->storeCopies(3);

        $deleted = $this->repository()->deleteByIds([$ids[0], $ids[2]]);

        self::assertSame(2, $deleted);
        $kept = array_map(static fn (SourceRow $sourceRow): ?int => $sourceRow->getId(), $this->repository()->findAll());
        self::assertSame([$ids[1]], $kept);
    }

    public function testDeleteByIdsWithNoIdsDeletesNothing(): void
    {
        $this->storeCopies(1);

        self::assertSame(0, $this->repository()->deleteByIds([]));
        self::assertSame(1, $this->repository()->count([]));
    }

    /**
     * The repository deletes the copies in groups, so that one query does not get too many ids.
     */
    public function testDeleteByIdsDeletesMoreCopiesThanOneGroup(): void
    {
        $ids = $this->storeCopies(1001);

        self::assertSame(1001, $this->repository()->deleteByIds($ids));
        self::assertSame(0, $this->repository()->count([]));
    }

    /**
     * @return list<int> the ids of the stored copies
     */
    private function storeCopies(int $count): array
    {
        $entityManager = $this->entityManager();
        $copies = array_map(static fn (int $index): SourceRow => new SourceRow(['index' => $index]), range(1, $count));
        foreach ($copies as $copy) {
            $entityManager->persist($copy);
        }
        $entityManager->flush();
        $entityManager->clear();

        return array_map(static fn (SourceRow $copy): int => (int) $copy->getId(), $copies);
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }

    private function repository(): SourceRowRepository
    {
        $repository = static::getContainer()->get(SourceRowRepository::class);
        self::assertInstanceOf(SourceRowRepository::class, $repository);

        return $repository;
    }
}
