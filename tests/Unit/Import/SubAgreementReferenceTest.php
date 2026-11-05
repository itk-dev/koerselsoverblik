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
        yield 'hyphen' => ['AB-CD-01', 'AB-CD'];
        yield 'underscore' => ['TEST_01', 'TEST'];
        yield 'long number' => ['AB-CD-123', 'AB-CD'];
        yield 'only the last number' => ['AB-CD-01-02', 'AB-CD-01'];
        yield 'no number at the end' => ['TEST', 'TEST'];
        yield 'letter after the number' => ['AB-CD-1A', 'AB-CD-1A'];
        yield 'number without a separator' => ['R01', 'R01'];
        yield 'spaces at the start and the end' => ['  AB-CD-01 ', 'AB-CD'];
        yield 'only a separator and a number' => ['-01', '-01'];
        yield 'line break in the name' => ["AB\nCD-01", "AB\nCD"];
    }
}
