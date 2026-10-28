<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The OIDC callback (OIDC_REDIRECT_URI). OidcAuthenticator handles a callback with a state, so
 * this action runs only for a request without one.
 *
 * The callback is public, so this controller has no IsGranted. RouteGuardTest lists it as public.
 * The firewall handles the logout at /logout.
 */
final class SecurityController extends AbstractController
{
    #[Route('/openid-connect/generic', name: 'openid_connect_generic', methods: ['GET'])]
    public function callback(): RedirectResponse
    {
        return $this->redirectToRoute('overview');
    }
}
