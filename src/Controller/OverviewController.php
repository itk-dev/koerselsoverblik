<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * A placeholder for the overview. M1-25 adds the figures.
 */
#[IsGranted('ROLE_VIEW_OVERVIEW')]
final class OverviewController extends AbstractController
{
    #[Route('/', name: 'overview', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('overview/index.html.twig');
    }
}
