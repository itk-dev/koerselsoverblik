<?php

namespace App\Tests\Unit\Import;

use App\Import\SubAgreementReference;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks the rule that finds the sub-agreement of a route name.
 */
final class SubAgreementReferenceTest extends TestCase
{
    #[DataProvider('routeNames')]
    public function testReferenceOfARouteName(string $routeName, string $reference): void
    {
        self::assertSame($reference, SubAgreementReference::fromRouteName($routeName));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function routeNames(): iterable
    {
        yield 'hyphen' => ['Ø4-VB-01', 'Ø4-VB'];
        yield 'underscore' => ['TEST_01', 'TEST'];
        yield 'long number' => ['Ø4-VB-123', 'Ø4-VB'];
        yield 'only the last number' => ['Ø4-VB-01-02', 'Ø4-VB-01'];
        yield 'no number at the end' => ['TEST', 'TEST'];
        yield 'letter after the number' => ['Ø4-VB-1A', 'Ø4-VB-1A'];
        yield 'number without a separator' => ['R01', 'R01'];
        yield 'spaces at the start and the end' => ['  Ø4-VB-01 ', 'Ø4-VB'];
        yield 'only a separator and a number' => ['-01', '-01'];
        yield 'line break in the name' => ["Ø4\nVB-01", "Ø4\nVB"];
    }
}
