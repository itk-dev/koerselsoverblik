<?php

namespace App\Supplier;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Compares the period of a new or changed assignment with the other assignments of its
 * sub-agreement.
 */
final class NoOverlappingAssignmentValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NoOverlappingAssignment) {
            throw new UnexpectedTypeException($constraint, NoOverlappingAssignment::class);
        }
        if (!$value instanceof AssignmentDto) {
            throw new UnexpectedValueException($value, AssignmentDto::class);
        }

        // NotNull reports a missing first date.
        $period = $value->period();
        if (null === $period) {
            return;
        }

        foreach ($value->subAgreement->getAssignments() as $other) {
            // A changed assignment is not compared with its old period.
            if ($other !== $value->assignment && $period->overlaps($other->getPeriod())) {
                $this->context->buildViolation($constraint->message)->atPath('validFrom')->addViolation();

                return;
            }
        }
    }
}
