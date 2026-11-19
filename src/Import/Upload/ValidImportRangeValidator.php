<?php

declare(strict_types=1);

namespace App\Import\Upload;

use App\Import\ImportRangeDto;
use App\Import\InvalidImportRangeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Checks the date range of an upload with the rules of ImportRangeDto, and shows the error on the
 * last date.
 */
final class ValidImportRangeValidator extends ConstraintValidator
{
    /**
     * @param int $maxDays the value of APP_DRIVING_DATA_MAX_DAYS
     */
    public function __construct(
        #[Autowire(param: 'app.driving_data_max_days')]
        private readonly int $maxDays,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidImportRange) {
            throw new UnexpectedTypeException($constraint, ValidImportRange::class);
        }
        if (!$value instanceof UploadDto || null === $value->firstDate || null === $value->lastDate) {
            return;
        }

        try {
            ImportRangeDto::create($value->firstDate, $value->lastDate, $this->maxDays);
        } catch (InvalidImportRangeException) {
            $violation = $value->lastDate < $value->firstDate
                ? $this->context->buildViolation($constraint->endBeforeStartMessage)
                : $this->context->buildViolation($constraint->tooLongMessage)->setParameter('{max}', (string) $this->maxDays);
            $violation->atPath('lastDate')->addViolation();
        }
    }
}
