<?php

namespace App\Tests\Unit\Supplier;

use App\Entity\SubAgreement;
use App\Entity\SubAgreementSupplierAssignment;
use App\Entity\Supplier;
use App\Supplier\AssignmentDto;
use App\Supplier\NoOverlappingAssignment;
use App\Supplier\NoOverlappingAssignmentValidator;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * Checks that an assignment cannot overlap another assignment of the same sub-agreement.
 *
 * @extends ConstraintValidatorTestCase<NoOverlappingAssignmentValidator>
 */
final class NoOverlappingAssignmentValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ConstraintValidatorInterface
    {
        return new NoOverlappingAssignmentValidator();
    }

    public function testAssignmentWithoutOtherAssignmentsIsValid(): void
    {
        $this->validator->validate(self::data(new SubAgreement(), '2026-01-01', null), new NoOverlappingAssignment());

        $this->assertNoViolation();
    }

    public function testOverlappingAssignmentIsAViolationOnTheFirstDate(): void
    {
        $subAgreement = new SubAgreement();
        self::assign($subAgreement, '2026-01-01', '2026-03-31');

        $this->validator->validate(self::data($subAgreement, '2026-03-01', null), new NoOverlappingAssignment());

        $this->buildViolation('assignment.overlap')->atPath('property.path.validFrom')->assertRaised();
    }

    public function testTouchingAssignmentIsValid(): void
    {
        $subAgreement = new SubAgreement();
        self::assign($subAgreement, '2026-01-01', '2026-03-31');

        $this->validator->validate(self::data($subAgreement, '2026-04-01', null), new NoOverlappingAssignment());

        $this->assertNoViolation();
    }

    /**
     * A changed assignment is in the collection of its sub-agreement with its old period. It is
     * not compared with that period.
     */
    public function testChangedAssignmentIsNotComparedWithItself(): void
    {
        $subAgreement = new SubAgreement();
        $changed = self::assign($subAgreement, '2026-01-01', null);
        self::assign($subAgreement, '2025-01-01', '2025-12-31');
        $data = AssignmentDto::forChange($changed);
        $data->validFrom = new \DateTimeImmutable('2026-02-01');

        $this->validator->validate($data, new NoOverlappingAssignment());

        $this->assertNoViolation();
    }

    public function testChangedAssignmentIsComparedWithTheOthers(): void
    {
        $subAgreement = new SubAgreement();
        $changed = self::assign($subAgreement, '2026-01-01', null);
        self::assign($subAgreement, '2025-01-01', '2025-12-31');
        $data = AssignmentDto::forChange($changed);
        $data->validFrom = new \DateTimeImmutable('2025-12-31');

        $this->validator->validate($data, new NoOverlappingAssignment());

        $this->buildViolation('assignment.overlap')->atPath('property.path.validFrom')->assertRaised();
    }

    /**
     * NotNull reports a missing first date. The overlap check has no period to compare.
     */
    public function testAssignmentWithoutAFirstDateIsNotChecked(): void
    {
        $subAgreement = new SubAgreement();
        self::assign($subAgreement, '2026-01-01', null);

        $this->validator->validate(AssignmentDto::forNew($subAgreement), new NoOverlappingAssignment());

        $this->assertNoViolation();
    }

    public function testOtherConstraintIsRejected(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(AssignmentDto::forNew(new SubAgreement()), new NotNull());
    }

    public function testOtherValueIsRejected(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(new SubAgreement(), new NoOverlappingAssignment());
    }

    private static function assign(SubAgreement $subAgreement, string $from, ?string $to): SubAgreementSupplierAssignment
    {
        $assignment = new SubAgreementSupplierAssignment($subAgreement, new Supplier(), new \DateTimeImmutable($from), null === $to ? null : new \DateTimeImmutable($to));
        $subAgreement->getAssignments()->add($assignment);

        return $assignment;
    }

    private static function data(SubAgreement $subAgreement, string $from, ?string $to): AssignmentDto
    {
        $data = AssignmentDto::forNew($subAgreement);
        $data->validFrom = new \DateTimeImmutable($from);
        $data->validTo = null === $to ? null : new \DateTimeImmutable($to);

        return $data;
    }
}
