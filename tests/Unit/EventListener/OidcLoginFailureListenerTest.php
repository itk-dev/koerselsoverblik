<?php

namespace App\Tests\Unit\EventListener;

use App\EventListener\OidcLoginFailureListener;
use ItkDev\OpenIdConnectBundle\Exception\AuthenticationFailedException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * The cases that the functional tests do not reach. OidcLoginFlowTest checks the pages.
 */
final class OidcLoginFailureListenerTest extends TestCase
{
    public function testOtherExceptionsAreLeftToTheErrorHandler(): void
    {
        $event = $this->event(new \RuntimeException('Something else'), HttpKernelInterface::MAIN_REQUEST);

        $this->listener()->onKernelException($event);

        self::assertNull($event->getResponse());
    }

    public function testASubRequestIsLeftToTheErrorHandler(): void
    {
        $event = $this->event(new AuthenticationFailedException('Login failed'), HttpKernelInterface::SUB_REQUEST);

        $this->listener()->onKernelException($event);

        self::assertNull($event->getResponse());
    }

    private function listener(): OidcLoginFailureListener
    {
        return new OidcLoginFailureListener(
            $this->createStub(Environment::class),
            $this->createStub(UrlGeneratorInterface::class),
        );
    }

    private function event(\Throwable $exception, int $requestType): ExceptionEvent
    {
        return new ExceptionEvent($this->createStub(HttpKernelInterface::class), new Request(), $requestType, $exception);
    }
}
