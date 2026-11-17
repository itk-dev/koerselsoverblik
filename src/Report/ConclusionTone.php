<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The result of the conclusion of the overview, and of each measure in it. A measure is only
 * better, worse or unchanged.
 */
enum ConclusionTone: string
{
    case Better = 'better';
    case Worse = 'worse';

    /**
     * Some measures are better, and some are worse.
     */
    case Mixed = 'mixed';

    case Unchanged = 'unchanged';

    /**
     * A selected measure has no value in the selected period or in the comparison period.
     */
    case NoComparison = 'no_comparison';

    /**
     * Gives the type of the callout of the headline: "success", "warn" or "info".
     */
    public function callout(): string
    {
        return match ($this) {
            self::Better => 'success',
            self::Worse => 'warn',
            default => 'info',
        };
    }
}
