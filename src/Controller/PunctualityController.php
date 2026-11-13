<?php

declare(strict_types=1);

namespace App\Controller;

use App\Report\PeriodSelector;
use App\Report\PunctualityPageBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The "Rettidighed" page: the four punctuality measures of the selected period, compared with the
 * comparison period, with the 12 months up to the period.
 */
#[IsGranted('ROLE_VIEW_OVERVIEW')]
final class PunctualityController extends AbstractController
{
    /**
     * The filters are in the query string. An invalid value gives null, and then its default. A
     * query that is not canonical redirects, so that each view has one URL.
     */
    #[Route('/punctuality', name: 'punctuality', methods: ['GET'])]
    public function __invoke(
        Request $request,
        PunctualityPageBuilder $builder,
        PeriodSelector $periods,
        #[Autowire(param: 'app.punctuality_tolerance_minutes')]
        int $toleranceMinutes,
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
            return $this->redirectToRoute('punctuality', $selection->filter->query());
        }

        return $this->render('punctuality/index.html.twig', [
            'selection' => $selection,
            'page' => $builder->build($selection),
            'tolerance_minutes' => $toleranceMinutes,
        ]);
    }
}
