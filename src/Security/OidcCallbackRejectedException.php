<?php

declare(strict_types=1);

namespace App\Security;

use ItkDev\OpenIdConnectBundle\Exception\AuthenticationFailedException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * A failed OIDC login that is not a fault of the application.
 *
 * Examples are a stale callback after the back button, and a user who declined the login. The
 * exception has a 4xx status, and the error listener logs it as a warning, not as critical.
 */
#[WithLogLevel(LogLevel::WARNING)]
final class OidcCallbackRejectedException extends AuthenticationFailedException implements HttpExceptionInterface
{
    public function __construct(
        private readonly int $statusCode,
        AuthenticationFailedException $previous,
    ) {
        parent::__construct($previous->getMessage(), 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }
}
