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
    public const string COMMAND = 'app:driving-data:rebuild-totals';

    public function __construct(public readonly int $toleranceMinutes)
    {
        parent::__construct(\sprintf(
            'Some totals of each route and day were built with another tolerance than %d minutes. Run "%s".',
            $toleranceMinutes,
            self::COMMAND,
        ));
    }
}
