<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The conclusion of the overview: the headline, and the measures of the sentence.
 */
final readonly class ConclusionDto
{
    /**
     * @param list<ConclusionReadingDto> $readings the selected measures, in the order of the enum. The list is empty if there is no comparison
     */
    public function __construct(
        public ConclusionTone $tone,
        public array $readings,
    ) {
    }
}
