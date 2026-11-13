<?php

declare(strict_types=1);

namespace App\Tests\Integration\Translation;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Checks the note of the trips on the overview. The note shows the trips with no registration
 * only when there are some, so that the parts of the note add up to the trips.
 */
final class OverviewTripsNoteTest extends KernelTestCase
{
    public function testNoPendingTripsGiveNoPendingPart(): void
    {
        self::assertSame('5 gennemført · 1 aflyst', self::note(5, 1, 0));
    }

    public function testPendingTripsAreInTheNote(): void
    {
        self::assertSame('1.200 gennemført · 0 aflyst · 2 uden registrering', self::note(1200, 0, 2));
    }

    private static function note(int $completed, int $cancelled, int $pending): string
    {
        $translator = self::getContainer()->get(TranslatorInterface::class);
        self::assertInstanceOf(TranslatorInterface::class, $translator);

        return $translator->trans('overview.trips.note', ['completed' => $completed, 'cancelled' => $cancelled, 'pending' => $pending], locale: 'da');
    }
}
