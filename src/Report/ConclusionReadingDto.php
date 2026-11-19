<?php

declare(strict_types=1);

namespace App\Report;

/**
 * One measure in the conclusion of the overview: its value in the selected period and in the
 * comparison period, in percent.
 */
final readonly class ConclusionReadingDto
{
    /**
     * @param ConclusionTone $tone better, worse or unchanged
     */
    public function __construct(
        public ConclusionMeasure $measure,
        public float $now,
        public float $before,
        public ConclusionTone $tone,
    ) {
    }
}
