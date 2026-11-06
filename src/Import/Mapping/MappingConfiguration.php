<?php

namespace App\Import\Mapping;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * The format of an import mapping file.
 *
 * @phpstan-type FieldConfig array{
 *     column: string,
 *     type: FieldType,
 *     format: ?string,
 *     map: array<array-key, string>,
 *     true_values: list<string>,
 *     false_values: list<string>,
 *     required: bool,
 * }
 * @phpstan-type MappingConfig array{
 *     sheet: int,
 *     header_row: int,
 *     row_id: list<string>,
 *     keep_columns: list<string>,
 *     fields: array<string, FieldConfig>,
 * }
 */
final class MappingConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('mapping');
        $root = $treeBuilder->getRootNode();

        $root->children()
            ->integerNode('sheet')->min(0)->defaultValue(0)->end()
            ->integerNode('header_row')->isRequired()->min(1)->end()
            ->arrayNode('row_id')->requiresAtLeastOneElement()->stringPrototype()->end()->end()
            // The other columns that the copy of a row keeps. See "Keep a copy of each imported row"
            // in docs/architecture.md.
            ->arrayNode('keep_columns')->stringPrototype()->cannotBeEmpty()->end()->end()
            ->append($this->fieldsNode());

        $root->validate()
            ->ifTrue(self::timeWithoutServiceDate(...))
            ->thenInvalid('A "time" field needs a "service_date" field with the type "date".');

        $root->validate()
            ->always(self::checkKeptColumns(...));

        return $treeBuilder;
    }

    private function fieldsNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('fields');
        $node->isRequired();

        $children = $node->children();
        foreach (FieldName::cases() as $name) {
            $children->append($this->fieldNode($name));
        }

        return $node;
    }

    private function fieldNode(FieldName $name): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition($name->value);
        $node->isRequired();

        $node->children()
            ->stringNode('column')->isRequired()->cannotBeEmpty()->end()
            ->enumNode('type')->isRequired()->enumFqcn(FieldType::class)->end()
            ->stringNode('format')->defaultNull()->end()
            // A code can contain a dash. Do not change it to an underscore.
            ->arrayNode('map')->normalizeKeys(false)->stringPrototype()->end()->end()
            ->arrayNode('true_values')->stringPrototype()->end()->end()
            ->arrayNode('false_values')->stringPrototype()->end()->end()
            ->booleanNode('required')->defaultFalse()->end();

        $types = $name->allowedTypes();
        $node->validate()
            ->ifTrue(static fn (array $field): bool => !\in_array($field['type'], $types, true))
            ->thenInvalid(\sprintf(
                'The type of "%s" must be "%s".',
                $name->value,
                implode('" or "', array_map(static fn (FieldType $type): string => $type->value, $types)),
            ));

        $node->validate()
            ->ifTrue(self::mapOnOtherType(...))
            ->thenInvalid('Only a "string" field can have a "map".');

        $node->validate()
            ->ifTrue(self::formatOnOtherType(...))
            ->thenInvalid('Only a "date" or "datetime" field can have a "format".');

        $node->validate()
            ->ifTrue(self::boolValuesOnOtherType(...))
            ->thenInvalid('Only a "bool" field can have "true_values" and "false_values".');

        $node->validate()
            ->ifTrue(self::boolWithoutValues(...))
            ->thenInvalid('A "bool" field must have "true_values" and "false_values".');

        $node->validate()
            ->ifTrue(self::valueInBothLists(...))
            ->thenInvalid('A value cannot be in "true_values" and in "false_values".');

        $enum = $name->valueEnum();
        if (null !== $enum) {
            // A wrong value in the map would make the reports skip the rows without an error.
            $allowed = array_map(static fn (StopType|StopStatus $case): string => $case->value, $enum::cases());

            $node->validate()
                ->ifTrue(self::withoutMap(...))
                ->thenInvalid(\sprintf('A "%s" field must have a "map".', $name->value));

            $node->validate()
                ->ifTrue(static fn (array $field): bool => self::mapValueNotAllowed($field, $allowed))
                ->thenInvalid(\sprintf('The "map" of "%s" can only have the values "%s".', $name->value, implode('", "', $allowed)));
        }

        if ($name->mustBeRequired()) {
            $node->validate()
                ->ifTrue(static fn (array $field): bool => !$field['required'])
                ->thenInvalid(\sprintf('The "%s" field must be required.', $name->value));
        }

        return $node;
    }

    /**
     * @param MappingConfig $mapping
     */
    private static function timeWithoutServiceDate(array $mapping): bool
    {
        return FieldType::Date !== $mapping['fields'][FieldName::ServiceDate->value]['type']
            && array_any($mapping['fields'], static fn (array $field): bool => FieldType::Time === $field['type']);
    }

    /**
     * The copy of a row holds the field columns and the row id columns. A kept column is one of
     * the other columns, so that each column is in the copy one time.
     *
     * @param MappingConfig $mapping
     *
     * @return MappingConfig
     *
     * @throws \InvalidArgumentException if a kept column is in the list more than one time, or is a field column or a row id column
     */
    private static function checkKeptColumns(array $mapping): array
    {
        foreach (array_count_values($mapping['keep_columns']) as $column => $count) {
            if ($count > 1) {
                throw new \InvalidArgumentException(\sprintf('The column "%s" is in "keep_columns" more than one time.', $column));
            }
        }

        $mappedColumns = [...array_column($mapping['fields'], 'column'), ...$mapping['row_id']];
        foreach ($mapping['keep_columns'] as $column) {
            if (\in_array($column, $mappedColumns, true)) {
                throw new \InvalidArgumentException(\sprintf('The column "%s" in "keep_columns" is a field column or a row id column.', $column));
            }
        }

        return $mapping;
    }

    /**
     * @param FieldConfig $field
     */
    private static function mapOnOtherType(array $field): bool
    {
        return [] !== $field['map'] && FieldType::String !== $field['type'];
    }

    /**
     * @param FieldConfig $field
     */
    private static function formatOnOtherType(array $field): bool
    {
        return null !== $field['format']
            && FieldType::Date !== $field['type']
            && FieldType::DateTime !== $field['type'];
    }

    /**
     * @param FieldConfig $field
     */
    private static function boolValuesOnOtherType(array $field): bool
    {
        return FieldType::Bool !== $field['type']
            && ([] !== $field['true_values'] || [] !== $field['false_values']);
    }

    /**
     * @param FieldConfig $field
     */
    private static function boolWithoutValues(array $field): bool
    {
        return FieldType::Bool === $field['type']
            && ([] === $field['true_values'] || [] === $field['false_values']);
    }

    /**
     * @param FieldConfig $field
     */
    private static function valueInBothLists(array $field): bool
    {
        return [] !== array_intersect($field['true_values'], $field['false_values']);
    }

    /**
     * @param FieldConfig $field
     */
    private static function withoutMap(array $field): bool
    {
        return [] === $field['map'];
    }

    /**
     * The field is not typed as FieldConfig, because a closure calls this method.
     *
     * @param array<array-key, mixed> $field
     * @param list<string>            $allowed
     */
    private static function mapValueNotAllowed(array $field, array $allowed): bool
    {
        /** @var array<string, string> $map */
        $map = $field['map'];

        return [] !== array_diff($map, $allowed);
    }
}
