<?php

namespace App\Import\DependencyInjection;

use App\Import\Mapping\MappingLoader;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Loads each import mapping when the container is built, so that an invalid mapping stops the
 * application before it starts.
 */
final class ValidateMappingsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $directory = $container->getParameterBag()->resolveValue($container->getParameter('app.driving_data_mapping_dir'));
        \assert(\is_string($directory));

        // Build the container again when a mapping changes.
        $container->addResource(new DirectoryResource($directory, '/\.yaml$/'));

        $loader = new MappingLoader();
        foreach (glob($directory.'/*.yaml') ?: [] as $path) {
            $loader->load($path);
        }
    }
}
