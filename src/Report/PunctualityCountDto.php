<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The measured stops or runs of one punctuality measure, and those of them that are on time.
 */
final readonly class PunctualityCountDto
{
    public function __construct(
        public int $onTime,
        public int $measured,
    ) {
    }

    /**
     * Gives on time ÷ measured × 100, or null if nothing is measured. No value is not 0 %.
     */
    public function percent(): ?float
    {
        return 0 === $this->measured ? null : $this->onTime / $this->measured * 100;
    }

    public function add(self $other): self
    {
        return new self($this->onTime + $other->onTime, $this->measured + $other->measured);
    }
}
