<?php

declare(strict_types=1);

namespace App\Report;

/**
 * A measure that the conclusion of the overview can use. The order of the cases is the order of
 * the measures in the query and in the sentence.
 */
enum ConclusionMeasure: string
{
    /**
     * The drop-off per route run, as in the punctuality tile of the overview.
     */
    case Punctuality = 'punctuality';

    /**
     * The share of the real solo runs.
     */
    case RealSolo = 'solo';

    /**
     * The share of the cancelled trips, of all trips.
     */
    case Cancelled = 'cancelled';

    /**
     * Tells if a higher value is better service.
     */
    public function higherIsBetter(): bool
    {
        return self::Punctuality === $this;
    }
}
