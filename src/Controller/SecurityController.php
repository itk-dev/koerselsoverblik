<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A placeholder for the login. M1-07 replaces it with the OIDC login routes and adds the logout.
 *
 * The login is public, so this controller has no IsGranted. RouteGuardTest lists it as public.
 */
final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'login', methods: ['GET'])]
    public function login(): Response
    {
        return new Response('Login');
    }
}
