<?php

namespace App\Tests\Integration\Twig;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;
use Twig\Extension\CoreExtension;

/**
 * Checks that the Twig date filter shows a time in APP_TIMEZONE, and a calendar date with no
 * conversion.
 */
final class DateFilterTest extends KernelTestCase
{
    public function testATimeIsShownInTheApplicationTimeZone(): void
    {
        $time = new \DateTimeImmutable('2026-01-05 06:10:00', new \DateTimeZone('UTC'));

        self::assertSame('07:10', $this->render("{{ time|date('H:i') }}", ['time' => $time]));
    }

    /**
     * The test uses a time zone west of UTC. There, a conversion of 00:00 UTC gives the day before.
     */
    public function testACalendarDateIsShownWithNoConversion(): void
    {
        $date = new \DateTimeImmutable('2026-01-05', new \DateTimeZone('UTC'));
        $this->twig()->getExtension(CoreExtension::class)->setTimezone('America/New_York');

        self::assertSame('05.01.2026', $this->render("{{ date|date('d.m.Y', false) }}", ['date' => $date]));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context): string
    {
        return $this->twig()->createTemplate($template)->render($context);
    }

    private function twig(): Environment
    {
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return $twig;
    }
}
