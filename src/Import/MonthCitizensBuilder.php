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
     * the last day of the month of the last date. It builds all scopes.
     */
    public function rebuild(DateRangeDto $range): void
    {
        $months = DateRangeDto::create(
            $range->firstDate->modify('first day of this month'),
            $range->lastDate->modify('last day of this month'),
        );
        foreach ($this->allScopes() as $supplierId) {
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
        // Each supplier is in the list one time, so that no scope is built two times.
        $suppliers = match (true) {
            $oldSupplierId !== $newSupplierId => [$oldSupplierId, $newSupplierId],
            $ignoredChanged => [$newSupplierId],
            default => [],
        };
        // A sub-agreement with no supplier has no scope of its own. Null is the scope of all suppliers.
        $scopes = [...($ignoredChanged ? [null] : []), ...array_filter($suppliers, static fn (?int $id): bool => null !== $id)];
        if ([] !== $scopes) {
            $this->rebuildAll($scopes);
        }
    }

    private function write(DateRangeDto $months, ?int $supplierId): void
    {
        /** @var ?Supplier $supplier the supplier exists, because its id came from the suppliers */
        $supplier = null === $supplierId ? null : $this->entityManager->getReference(Supplier::class, $supplierId);
        foreach ($this->stopRows->countCitizensByMonth($months, $supplierId) as $month => $citizens) {
            // PHP reads a month as YYYY-MM as the first day of the month at 00:00.
            $this->entityManager->persist(new MonthCitizens(new \DateTimeImmutable($month), $supplier, $citizens));
        }
        $this->entityManager->flush();
    }

    /**
     * @return list<?int> null for all suppliers, and the id of each supplier
     */
    private function allScopes(): array
    {
        // A stored supplier always has an id.
        return [null, ...array_map(static fn (Supplier $supplier): ?int => $supplier->getId(), $this->suppliers->findAll())];
    }
}
