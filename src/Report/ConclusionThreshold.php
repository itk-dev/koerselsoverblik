<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The smallest change of a measure, in percentage points, that the conclusion counts as better or
 * worse. A smaller change counts as unchanged. The value is the value in the query.
 *
 * The threshold is in points and not relative to the value, because all measures are shares in
 * percent. The tiles show the change in points too, so the reader can compare the two.
 */
enum ConclusionThreshold: string
{
    case Any = '0';
    case Half = '0.5';
    case One = '1';
    case Two = '2';
    case Five = '5';

    /**
     * Gives the threshold in percentage points.
     */
    public function points(): float
    {
        return (float) $this->value;
    }
}
