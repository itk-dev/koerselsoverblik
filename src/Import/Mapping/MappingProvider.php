<?php

namespace App\Import\Mapping;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Gives the import mappings in the mapping directory by name.
 */
final readonly class MappingProvider
{
    public function __construct(
        private MappingLoader $loader,
        #[Autowire(param: 'app.driving_data_mapping_dir')]
        private string $directory,
        #[Autowire(env: 'APP_DRIVING_DATA_MAPPING')]
        private string $defaultName,
    ) {
    }

    /**
     * @throws InvalidMappingException if the name is not valid, or the mapping is missing or not valid
     */
    public function get(string $name): MappingDto
    {
        // The name can come from the command line. It must not select a file outside the directory.
        if (1 !== preg_match('/^[a-z0-9_-]+$/', $name)) {
            throw new InvalidMappingException(\sprintf('The import mapping name "%s" is not valid. Use only a-z, 0-9, "_" and "-".', $name));
        }

        return $this->loader->load($this->directory.'/'.$name.'.yaml');
    }

    /**
     * Gives the mapping that APP_DRIVING_DATA_MAPPING selects for uploads.
     *
     * @throws InvalidMappingException if the name is not valid, or the mapping is missing or not valid
     */
    public function default(): MappingDto
    {
        return $this->get($this->defaultName);
    }
}
