<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A placeholder for the overview. M1-05 adds the role guard, and M1-25 adds the figures.
 */
final class OverviewController extends AbstractController
{
    #[Route('/', name: 'overview', methods: ['GET'])]
    public function __invoke(): Response
    {
        return new Response('Passenger Transport Performance');
    }
}
