<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Entity\SourceRow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Checks that the command deletes the copies with no row. Two imports of the same dates that
 * overlap can leave such copies. A cron job on the server runs the command.
 */
final class DrivingDataDeleteOrphanCopiesCommandTest extends KernelTestCase
{
    public function testCopiesWithNoRowAreDeleted(): void
    {
        $this->storeCopies([['a' => 1], ['b' => 2]]);

        $tester = $this->execute();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Deleted 2 copies with no row.', $tester->getDisplay());
        self::assertSame(0, $this->entityManager()->getRepository(SourceRow::class)->count([]));
    }

    /**
     * The output has the count only, never a value of a copy.
     */
    public function testOutputHasNoValues(): void
    {
        $this->storeCopies([['PersonID' => 'secret-value']]);

        $tester = $this->execute();

        self::assertStringNotContainsString('secret-value', $tester->getDisplay());
    }

    /**
     * @param list<array<string, int|string>> $values the values of each copy
     */
    private function storeCopies(array $values): void
    {
        $entityManager = $this->entityManager();
        foreach ($values as $copyValues) {
            $entityManager->persist(new SourceRow($copyValues));
        }
        $entityManager->flush();
        $entityManager->clear();
    }

    private function execute(): CommandTester
    {
        $tester = new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find('app:driving-data:delete-orphan-copies'));
        $tester->execute([]);

        return $tester;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
