<?php

declare(strict_types=1);

namespace App\Controller;

use App\Report\ActivityPageBuilder;
use App\Report\ActivityTableCalculator;
use App\Report\PeriodSelector;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The "Kørselsaktivitet" page: the trips, the cancellations and the route runs of the selected
 * period, compared with the comparison period, with the 12 months up to the period.
 */
#[IsGranted('ROLE_VIEW_OVERVIEW')]
final class ActivityController extends AbstractController
{
    /**
     * The filters are in the query string. An invalid value gives null, and then its default. A
     * query that is not canonical redirects, so that each view has one URL.
     */
    #[Route('/activity', name: 'activity', methods: ['GET'])]
    public function __invoke(
        Request $request,
        ActivityPageBuilder $builder,
        ActivityTableCalculator $tables,
        PeriodSelector $periods,
        #[MapQueryParameter(flags: \FILTER_NULL_ON_FAILURE)]
        ?string $p = null,
        #[MapQueryParameter(flags: \FILTER_NULL_ON_FAILURE)]
        ?string $d = null,
        #[MapQueryParameter(flags: \FILTER_NULL_ON_FAILURE)]
        ?string $c = null,
        #[MapQueryParameter(flags: \FILTER_NULL_ON_FAILURE)]
        ?string $s = null,
    ): Response {
        $selection = $periods->select($p, $d, $c, $s);
        if (!$selection->filter->isCanonical($request->query->all())) {
            return $this->redirectToRoute('activity', $selection->filter->query());
        }

        return $this->render('activity/index.html.twig', [
            'selection' => $selection,
            'page' => $builder->build($selection),
            'tables' => $tables->calculate($selection->selectedRange, $selection->filter->supplier),
        ]);
    }
}
