<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Supplier;
use App\Report\Comparison;
use App\Report\PeriodDto;
use App\Report\PeriodLabeler;
use App\Report\PeriodSelectionDto;
use App\Report\PeriodType;
use App\Repository\SupplierRepository;
use Twig\Attribute\AsTwigFunction;

/**
 * Gives the filter bar and the lede of a page the labels of the periods.
 */
final readonly class PeriodExtension
{
    public function __construct(
        private PeriodLabeler $labeler,
        private SupplierRepository $suppliers,
    ) {
    }

    /**
     * Gives the suppliers of the select box of the filter bar, in the order of the name.
     *
     * @return list<Supplier>
     */
    #[AsTwigFunction('filter_suppliers')]
    public function suppliers(): array
    {
        return $this->suppliers->findBy([], ['name' => 'ASC']);
    }

    #[AsTwigFunction('period_label')]
    public function label(PeriodDto $period): string
    {
        return $this->labeler->label($period);
    }

    #[AsTwigFunction('period_phrase')]
    public function phrase(PeriodSelectionDto $selection): string
    {
        return $this->labeler->phrase($selection);
    }

    #[AsTwigFunction('comparison_phrase')]
    public function comparisonPhrase(PeriodSelectionDto $selection): string
    {
        return $this->labeler->comparisonPhrase($selection);
    }

    /**
     * @return list<PeriodType>
     */
    #[AsTwigFunction('period_types')]
    public function types(): array
    {
        return PeriodType::cases();
    }

    /**
     * @return list<Comparison>
     */
    #[AsTwigFunction('comparisons')]
    public function comparisons(): array
    {
        return Comparison::cases();
    }
}
