<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import\Mapping;

use App\Import\Mapping\InvalidMappingException;
use App\Import\Mapping\MappingLoader;
use App\Import\Mapping\MappingProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks that the provider finds a mapping by its name, and rejects names that are not safe.
 */
final class MappingProviderTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../../../fixtures/mappings';

    public function testGetLoadsTheMappingWithTheName(): void
    {
        $mapping = $this->provider()->get('valid_without_row_id');

        self::assertSame('valid_without_row_id', $mapping->name);
    }

    public function testDefaultLoadsTheConfiguredMapping(): void
    {
        $mapping = $this->provider()->default();

        self::assertSame('valid', $mapping->name);
    }

    public function testUnknownNameIsRejected(): void
    {
        $this->expectException(InvalidMappingException::class);
        $this->expectExceptionMessageMatches('/missing\.yaml.*does not exist/s');

        $this->provider()->get('missing');
    }

    /**
     * A name must not select a file outside the mapping directory.
     */
    #[DataProvider('invalidNames')]
    public function testInvalidNameIsRejected(string $name): void
    {
        $this->expectException(InvalidMappingException::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The import mapping name "%s" is not valid.', $name));

        $this->provider()->get($name);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'path before the name' => ['../mappings/valid'];
        yield 'path after the name' => ['valid/../valid'];
        yield 'capital letter' => ['Valid'];
        yield 'dot' => ['valid.yaml'];
        yield 'empty' => [''];
    }

    private function provider(): MappingProvider
    {
        return new MappingProvider(new MappingLoader(), self::FIXTURES, 'valid');
    }
}
