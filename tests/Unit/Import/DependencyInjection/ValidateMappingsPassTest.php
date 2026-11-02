<?php

namespace App\Tests\Unit\Import\DependencyInjection;

use App\Import\DependencyInjection\ValidateMappingsPass;
use App\Import\Mapping\InvalidMappingException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Checks that the container build stops when a mapping in the mapping directory is not valid.
 */
final class ValidateMappingsPassTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../../../fixtures/mappings';

    private string $directory = '';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/'.uniqid('mapping_dir_', true);
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testValidMappingsPass(): void
    {
        copy(self::FIXTURES.'/valid.yaml', $this->directory.'/first.yaml');
        copy(self::FIXTURES.'/valid_without_row_id.yaml', $this->directory.'/second.yaml');
        // Only the YAML files are mappings.
        copy(self::FIXTURES.'/invalid_syntax.yaml', $this->directory.'/notes.txt');
        $container = $this->container();

        (new ValidateMappingsPass())->process($container);

        self::assertContainsEquals(new DirectoryResource($this->directory, '/\.yaml$/'), $container->getResources());
    }

    public function testInvalidMappingStopsTheBuild(): void
    {
        copy(self::FIXTURES.'/valid.yaml', $this->directory.'/first.yaml');
        copy(self::FIXTURES.'/invalid_syntax.yaml', $this->directory.'/second.yaml');

        $this->expectException(InvalidMappingException::class);
        $this->expectExceptionMessageMatches('/second\.yaml/');

        (new ValidateMappingsPass())->process($this->container());
    }

    /**
     * The parameter uses %kernel.project_dir%, as in config/services.yaml.
     */
    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', \dirname($this->directory));
        $container->setParameter('app.driving_data_mapping_dir', '%kernel.project_dir%/'.basename($this->directory));

        return $container;
    }
}
