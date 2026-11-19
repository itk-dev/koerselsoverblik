<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The measured last drop-offs in each delay band. A delay on the edge of two bands is in the
 * lower band.
 */
final readonly class DelayBandsDto
{
    /**
     * @param int $onTime the delays of at most the tolerance
     * @param int $upTo15 the delays of more than the tolerance and at most 15 minutes
     * @param int $upTo30 the delays of more than 15 and at most 30 minutes
     * @param int $over30 the delays of more than 30 minutes
     */
    public function __construct(
        public int $onTime,
        public int $upTo15,
        public int $upTo30,
        public int $over30,
    ) {
    }

    public function add(self $other): self
    {
        return new self(
            $this->onTime + $other->onTime,
            $this->upTo15 + $other->upTo15,
            $this->upTo30 + $other->upTo30,
            $this->over30 + $other->over30,
        );
    }
}
