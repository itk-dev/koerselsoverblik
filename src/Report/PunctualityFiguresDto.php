<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The four punctuality measures and the delay bands of the last drop-off, for a period and
 * possibly one supplier. See "Calculating the figures" in docs/architecture.md.
 */
final readonly class PunctualityFiguresDto
{
    /**
     * @param PunctualityCountDto $pickupPerCitizen  all measured pickups
     * @param PunctualityCountDto $dropOffPerCitizen all measured drop-offs
     * @param PunctualityCountDto $pickupPerRun      the measured first pickups of the route runs
     * @param PunctualityCountDto $dropOffPerRun     the measured last drop-offs of the route runs
     * @param DelayBandsDto       $bands             the measured last drop-offs in each delay band
     */
    public function __construct(
        public PunctualityCountDto $pickupPerCitizen,
        public PunctualityCountDto $dropOffPerCitizen,
        public PunctualityCountDto $pickupPerRun,
        public PunctualityCountDto $dropOffPerRun,
        public DelayBandsDto $bands,
    ) {
    }

    /**
     * Gives the figures with nothing measured.
     */
    public static function empty(): self
    {
        $none = new PunctualityCountDto(0, 0);

        return new self($none, $none, $none, $none, new DelayBandsDto(0, 0, 0, 0));
    }

    public function add(self $other): self
    {
        return new self(
            $this->pickupPerCitizen->add($other->pickupPerCitizen),
            $this->dropOffPerCitizen->add($other->dropOffPerCitizen),
            $this->pickupPerRun->add($other->pickupPerRun),
            $this->dropOffPerRun->add($other->dropOffPerRun),
            $this->bands->add($other->bands),
        );
    }
}
