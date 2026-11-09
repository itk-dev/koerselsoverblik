<?php

declare(strict_types=1);

namespace App\Supplier;

use Symfony\Component\Validator\Constraint;

/**
 * The period of an assignment must not overlap another assignment of the same sub-agreement.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class NoOverlappingAssignment extends Constraint
{
    public string $message = 'assignment.overlap';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
