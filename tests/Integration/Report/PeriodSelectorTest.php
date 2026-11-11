<?php

declare(strict_types=1);

namespace App\Tests\Integration\Report;

use App\Entity\Supplier;
use App\Report\PeriodSelector;
use App\Report\PeriodType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Checks that the selector reads the filters, and that a supplier id that does not exist gives
 * all suppliers.
 */
final class PeriodSelectorTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    public function testSelectorReadsTheFiltersWithAnExistingSupplier(): void
    {
        self::mockTime('2026-09-18 10:00:00');
        $supplier = $this->supplier();

        $selection = $this->selector()->select('quarter', '2026-05-05', 'last_year', (string) $supplier->getId());

        self::assertSame(PeriodType::Quarter, $selection->filter->type);
        self::assertSame('2026-04-01', $selection->selectedRange->firstDate->format('Y-m-d'));
        self::assertSame($supplier->getId(), $selection->filter->supplier);
    }

    public function testSupplierThatDoesNotExistGivesAllSuppliers(): void
    {
        self::mockTime('2026-09-18 10:00:00');
        $supplier = $this->supplier();

        $selection = $this->selector()->select(null, null, null, (string) ((int) $supplier->getId() + 1000));

        self::assertNull($selection->filter->supplier);
        self::assertSame('2026-09-18', $selection->selectedRange->lastDate->format('Y-m-d'));
    }

    private function supplier(): Supplier
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $supplier = new Supplier()->setName('Vognmand A');
        $entityManager->persist($supplier);
        $entityManager->flush();

        return $supplier;
    }

    private function selector(): PeriodSelector
    {
        $selector = self::getContainer()->get(PeriodSelector::class);
        self::assertInstanceOf(PeriodSelector::class, $selector);

        return $selector;
    }
}
