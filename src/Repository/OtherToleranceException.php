<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Some totals of each route and day were built with another value of
 * APP_PUNCTUALITY_TOLERANCE_MINUTES, so their counts on time are wrong. The admin must build them
 * again with the command.
 */
final class OtherToleranceException extends \RuntimeException
{
    public function __construct(int $toleranceMinutes)
    {
        parent::__construct(\sprintf(
            'Some totals of each route and day were built with another tolerance than %d minutes. Run "app:driving-data:rebuild-totals".',
            $toleranceMinutes,
        ));
    }
}
