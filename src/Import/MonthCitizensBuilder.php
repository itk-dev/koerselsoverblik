<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\MonthCitizens;
use App\Entity\Supplier;
use App\Report\DateRangeDto;
use App\Repository\MonthCitizensRepository;
use App\Repository\StopRowRepository;
use App\Repository\SupplierRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Builds the citizens of each month again from the stored stop rows, for all suppliers and for
 * each supplier. The citizens of two months cannot be added, so each month is counted on the
 * stop rows. See "Pipeline" in docs/architecture.md.
 *
 * The counts depend on the ignored flag and the supplier of each sub-agreement. Thus a change of
 * a sub-agreement builds the counts again, unlike the totals of each route and day.
 */
final readonly class MonthCitizensBuilder
{
    public function __construct(
        private MonthCitizensRepository $months,
        private StopRowRepository $stopRows,
        private SupplierRepository $suppliers,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Builds the full months of the range: from the first day of the month of the first date to
     * the last day of the month of the last date.
     *
     * @param ?list<?int> $scopes the supplier ids to build, with null for all suppliers, or null to build all scopes
     */
    public function rebuild(DateRangeDto $range, ?array $scopes = null): void
    {
        $months = DateRangeDto::create(
            $range->firstDate->modify('first day of this month'),
            $range->lastDate->modify('last day of this month'),
        );
        foreach ($scopes ?? $this->allScopes() as $supplierId) {
            $this->months->deleteMonths($months, $supplierId);
            $this->write($months, $supplierId);
        }
    }

    /**
     * Builds all months of the stored stop rows. It first deletes all rows of the scopes, so that
     * no month with no stop rows keeps a count.
     *
     * @param ?list<?int> $scopes the supplier ids to build, with null for all suppliers, or null to build all scopes
     */
    public function rebuildAll(?array $scopes = null): void
    {
        $span = $this->stopRows->dateSpan();
        foreach ($scopes ?? $this->allScopes() as $supplierId) {
            $this->months->deleteMonths(null, $supplierId);
            if (null !== $span) {
                $this->write(DateRangeDto::create(...$span), $supplierId);
            }
        }
    }

    /**
     * Builds the counts that a change of a sub-agreement changes. The ignored flag changes the
     * count of all suppliers and of the supplier of the sub-agreement. The supplier changes the
     * counts of the old and the new supplier. A sub-agreement with no supplier is in the count of
     * all suppliers only.
     */
    public function rebuildAfterSubAgreementChange(bool $ignoredChanged, ?int $oldSupplierId, ?int $newSupplierId): void
    {
        $suppliers = [];
        if ($ignoredChanged) {
            $suppliers[] = $newSupplierId;
        }
        if ($oldSupplierId !== $newSupplierId) {
            $suppliers[] = $oldSupplierId;
            $suppliers[] = $newSupplierId;
        }
        // A sub-agreement with no supplier has no scope of its own. Null is the scope of all suppliers.
        $scopes = array_values(array_unique(array_filter($suppliers, static fn (?int $id): bool => null !== $id)));
        if ($ignoredChanged) {
            array_unshift($scopes, null);
        }
        if ([] !== $scopes) {
            $this->rebuildAll($scopes);
        }
    }

    private function write(DateRangeDto $months, ?int $supplierId): void
    {
        /** @var ?Supplier $supplier the supplier exists, because its id came from the suppliers */
        $supplier = null === $supplierId ? null : $this->entityManager->getReference(Supplier::class, $supplierId);
        foreach ($this->stopRows->countCitizensByMonth($months, $supplierId) as $month => $citizens) {
            $this->entityManager->persist(new MonthCitizens(new \DateTimeImmutable($month.'-01'), $supplier, $citizens));
        }
        $this->entityManager->flush();
    }

    /**
     * @return list<?int> null for all suppliers, and the id of each supplier
     */
    private function allScopes(): array
    {
        return [null, ...array_map(static fn (Supplier $supplier): int => (int) $supplier->getId(), $this->suppliers->findAll())];
    }
}
