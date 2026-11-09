<?php

declare(strict_types=1);

namespace App\Import\Mapping;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads an import mapping file, and validates it with MappingConfiguration.
 *
 * @phpstan-import-type MappingConfig from MappingConfiguration
 */
final class MappingLoader
{
    /**
     * @throws InvalidMappingException if the file is missing or not valid
     */
    public function load(string $path): MappingDto
    {
        try {
            /** @var MappingConfig $config */
            $config = new Processor()->processConfiguration(new MappingConfiguration(), [Yaml::parseFile($path)]);
        } catch (ParseException|InvalidConfigurationException $exception) {
            throw new InvalidMappingException(\sprintf('The import mapping "%s" is not valid: %s', $path, $exception->getMessage()), previous: $exception);
        }

        $fields = [];
        foreach ($config['fields'] as $name => $field) {
            $fields[$name] = new FieldMappingDto(
                column: $field['column'],
                type: $field['type'],
                required: $field['required'],
                format: $field['format'],
                valueMap: $field['map'],
                trueValues: $field['true_values'],
                falseValues: $field['false_values'],
            );
        }

        return new MappingDto(
            name: basename($path, '.yaml'),
            sheet: $config['sheet'],
            headerRow: $config['header_row'],
            rowId: $config['row_id'],
            fields: $fields,
            keepColumns: $config['keep_columns'],
            excludeColumns: $config['exclude_columns'],
        );
    }
}
