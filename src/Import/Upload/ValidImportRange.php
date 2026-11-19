<?php

declare(strict_types=1);

namespace App\Import\Upload;

use Symfony\Component\Validator\Constraint;

/**
 * The date range of an upload: the last date is not before the first date, and the range has at
 * most APP_DRIVING_DATA_MAX_DAYS days.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class ValidImportRange extends Constraint
{
    public string $endBeforeStartMessage = 'upload.end_before_start';

    public string $tooLongMessage = 'upload.range_too_long';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
