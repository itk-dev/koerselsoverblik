<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\SubAgreement;
use App\Entity\SubAgreementSupplierAssignment;
use App\Entity\Supplier;
use PHPUnit\Framework\TestCase;

final class SubAgreementTest extends TestCase
{
    public function testAssignmentOnADateFollowsThePeriods(): void
    {
        $subAgreement = new SubAgreement();
        $old = $this->assign($subAgreement, '2026-01-01', '2026-03-31');
        $new = $this->assign($subAgreement, '2026-04-01', null);

        self::assertNull($subAgreement->getAssignmentOn(new \DateTimeImmutable('2025-12-31')));
        self::assertSame($old, $subAgreement->getAssignmentOn(new \DateTimeImmutable('2026-03-31')));
        self::assertSame($new, $subAgreement->getAssignmentOn(new \DateTimeImmutable('2026-04-01')));
    }

    /**
     * The assignments come from the database in the order of the first date. If two overlap, the
     * one with the latest first date wins, as in findSupplier().
     */
    public function testOverlappingAssignmentsGiveTheAssignmentWithTheLatestFirstDate(): void
    {
        $subAgreement = new SubAgreement();
        $earlier = $this->assign($subAgreement, '2026-01-01', null);
        $later = $this->assign($subAgreement, '2026-02-01', null);

        self::assertSame($earlier, $subAgreement->getAssignmentOn(new \DateTimeImmutable('2026-01-31')));
        self::assertSame($later, $subAgreement->getAssignmentOn(new \DateTimeImmutable('2026-02-01')));
    }

    private function assign(SubAgreement $subAgreement, string $from, ?string $to): SubAgreementSupplierAssignment
    {
        $assignment = new SubAgreementSupplierAssignment($subAgreement, new Supplier(), new \DateTimeImmutable($from), null === $to ? null : new \DateTimeImmutable($to));
        $subAgreement->getAssignments()->add($assignment);

        return $assignment;
    }
}
