<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The filters of a page: the period type, a date in the period, the comparison and the supplier.
 * The filters also have the settings of the conclusion of the overview, so that the links of the
 * filter bar keep them. They are in the query string, so that a view can be shared as a link.
 */
final readonly class PeriodFilter
{
    private const string DATE = 'Y-m-d';

    private const array DEFAULT_MEASURES = [ConclusionMeasure::Punctuality];

    /**
     * @param \DateTimeImmutable      $anchor    a date in the period, at 00:00
     * @param ?int                    $supplier  the id of the supplier, or null for all suppliers
     * @param \DateTimeImmutable      $today     today at 00:00. It is the default of the anchor and the last date of the periods
     * @param list<ConclusionMeasure> $measures  the measures of the conclusion, at least one, in the order of the enum
     * @param ConclusionThreshold     $threshold the lowest change that the conclusion counts
     */
    private function __construct(
        public PeriodType $type,
        public \DateTimeImmutable $anchor,
        public Comparison $comparison,
        public ?int $supplier,
        public \DateTimeImmutable $today,
        public array $measures,
        public ConclusionThreshold $threshold,
    ) {
    }

    /**
     * Reads the query string. An invalid value gives its default, so that a link always works:
     * the month of today, the previous period, all suppliers, the punctuality and 1 %. A date after
     * today gives today.
     *
     * @param array<mixed>|string|null $measures the measures as a list with commas, or as the list of the checkboxes of the form
     */
    public static function fromQuery(?string $type, ?string $date, ?string $comparison, ?string $supplier, \DateTimeImmutable $today, array|string|null $measures = null, ?string $threshold = null): self
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
            self::measures($measures),
            ConclusionThreshold::tryFrom($threshold ?? '') ?? ConclusionThreshold::One,
        );
    }

    /**
     * Gives a filter with other values, for a link that changes one filter and keeps the others.
     */
    public function with(?PeriodType $type = null, ?\DateTimeImmutable $anchor = null, ?Comparison $comparison = null): self
    {
        return new self($type ?? $this->type, $anchor ?? $this->anchor, $comparison ?? $this->comparison, $this->supplier, $this->today, $this->measures, $this->threshold);
    }

    /**
     * Gives the filter for all suppliers, for a supplier id that does not exist.
     */
    public function withoutSupplier(): self
    {
        return new self($this->type, $this->anchor, $this->comparison, null, $this->today, $this->measures, $this->threshold);
    }

    /**
     * Gives the query string of the filter for path(). It leaves out a value that is the default.
     * The date is the first date of the period, so that each period has one link. The current
     * period has no date.
     *
     * @return array<string, string>
     */
    public function query(): array
    {
        $first = $this->type->start($this->anchor);

        return array_filter([
            'p' => PeriodType::Month === $this->type ? null : $this->type->value,
            'd' => $first == $this->type->start($this->today) ? null : $first->format(self::DATE),
            'c' => Comparison::Previous === $this->comparison ? null : $this->comparison->value,
            's' => null === $this->supplier ? null : (string) $this->supplier,
            'm' => self::DEFAULT_MEASURES === $this->measures ? null : implode(',', array_map(static fn (ConclusionMeasure $measure): string => $measure->value, $this->measures)),
            't' => ConclusionThreshold::One === $this->threshold ? null : $this->threshold->value,
        ], static fn (?string $value): bool => null !== $value);
    }

    /**
     * Tells if a query string is the query of this filter, so that each view has one URL. The
     * order of the keys has no effect. A default value, an invalid value or an unknown parameter
     * makes the query not canonical.
     *
     * @param array<array-key, mixed> $query
     */
    public function isCanonical(array $query): bool
    {
        $canonical = $this->query();
        ksort($canonical);
        ksort($query);

        return $query === $canonical;
    }

    /**
     * Gives the valid measures in the order of the enum. Each measure is in the list one time. An
     * unknown value is left out. With no valid measure, the list has the default measure.
     *
     * @param array<mixed>|string|null $measures
     *
     * @return list<ConclusionMeasure>
     */
    private static function measures(array|string|null $measures): array
    {
        $values = \is_string($measures) ? explode(',', $measures) : $measures ?? [];
        $selected = array_values(array_filter(ConclusionMeasure::cases(), static fn (ConclusionMeasure $measure): bool => \in_array($measure->value, $values, true)));

        return [] === $selected ? self::DEFAULT_MEASURES : $selected;
    }
}
