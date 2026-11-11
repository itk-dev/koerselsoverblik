<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The filters of a page: the period type, a date in the period, the comparison and the supplier.
 * They are in the query string, so that a view can be shared as a link.
 */
final readonly class PeriodFilter
{
    private const string DATE = 'Y-m-d';

    /**
     * @param \DateTimeImmutable $anchor   a date in the period, at 00:00
     * @param ?int               $supplier the id of the supplier, or null for all suppliers
     * @param \DateTimeImmutable $today    the default of the anchor, so that the query can leave it out
     */
    private function __construct(
        public PeriodType $type,
        public \DateTimeImmutable $anchor,
        public Comparison $comparison,
        public ?int $supplier,
        private \DateTimeImmutable $today,
    ) {
    }

    /**
     * Reads the query string. An invalid value gives its default, so that a link always works:
     * the month of today, the previous period and all suppliers. A date after today gives today.
     */
    public static function fromQuery(?string $type, ?string $date, ?string $comparison, ?string $supplier, \DateTimeImmutable $today): self
    {
        $today = new \DateTimeImmutable($today->format(self::DATE));
        $anchor = null === $date ? false : \DateTimeImmutable::createFromFormat('!'.self::DATE, $date);
        if (false === $anchor || $anchor->format(self::DATE) !== $date || $anchor > $today) {
            $anchor = $today;
        }
        $id = null === $supplier ? false : filter_var($supplier, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return new self(
            PeriodType::tryFrom($type ?? '') ?? PeriodType::Month,
            $anchor,
            Comparison::tryFrom($comparison ?? '') ?? Comparison::Previous,
            false === $id ? null : $id,
            $today,
        );
    }

    /**
     * Gives a filter with other values, for a link that changes one filter and keeps the others.
     */
    public function with(?PeriodType $type = null, ?\DateTimeImmutable $anchor = null, ?Comparison $comparison = null): self
    {
        return new self($type ?? $this->type, $anchor ?? $this->anchor, $comparison ?? $this->comparison, $this->supplier, $this->today);
    }

    /**
     * Gives the query string of the filter for path(). It leaves out a value that is the default.
     *
     * @return array<string, string>
     */
    public function query(): array
    {
        return array_filter([
            'p' => PeriodType::Month === $this->type ? null : $this->type->value,
            'd' => $this->anchor == $this->today ? null : $this->anchor->format(self::DATE),
            'c' => Comparison::Previous === $this->comparison ? null : $this->comparison->value,
            's' => null === $this->supplier ? null : (string) $this->supplier,
        ], static fn (?string $value): bool => null !== $value);
    }
}
