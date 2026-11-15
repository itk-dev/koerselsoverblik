<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\OtherToleranceListener;
use App\Repository\OtherToleranceException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twig\Environment;

/**
 * The cases that the functional tests do not reach. OverviewPageTest checks the page.
 */
final class OtherToleranceListenerTest extends TestCase
{
    public function testOtherExceptionsAreLeftToTheErrorHandler(): void
    {
        $event = $this->event(new \RuntimeException('Something else'), HttpKernelInterface::MAIN_REQUEST);

        new OtherToleranceListener($this->createStub(Environment::class))->onKernelException($event);

        self::assertNull($event->getResponse());
    }

    public function testASubRequestIsLeftToTheErrorHandler(): void
    {
        $event = $this->event(new OtherToleranceException(5), HttpKernelInterface::SUB_REQUEST);

        new OtherToleranceListener($this->createStub(Environment::class))->onKernelException($event);

        self::assertNull($event->getResponse());
    }

    private function event(\Throwable $exception, int $requestType): ExceptionEvent
    {
        return new ExceptionEvent($this->createStub(HttpKernelInterface::class), new Request(), $requestType, $exception);
    }
}
