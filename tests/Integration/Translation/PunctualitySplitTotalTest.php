<?php

declare(strict_types=1);

namespace App\Tests\Integration\Translation;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Checks the total of the split bar on the punctuality page. A count of 1000 or more has a
 * thousands separator, as the other numbers on the page.
 */
final class PunctualitySplitTotalTest extends KernelTestCase
{
    public function testTotalHasAThousandsSeparator(): void
    {
        $translator = self::getContainer()->get(TranslatorInterface::class);
        self::assertInstanceOf(TranslatorInterface::class, $translator);

        self::assertSame('11.490 vognløb', $translator->trans('punctuality.split.total', ['count' => 11490], locale: 'da'));
    }
}
