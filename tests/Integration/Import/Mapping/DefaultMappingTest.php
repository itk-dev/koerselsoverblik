<?php

declare(strict_types=1);

namespace App\Tests\Integration\Import\Mapping;

use App\Import\Mapping\FieldName;
use App\Import\Mapping\FieldType;
use App\Import\Mapping\MappingProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks that the application starts with the default mapping, and that the mapping reads the
 * layout of the reference extract.
 *
 * The test does not assert the code values. They are only in config/import/driving_data/default.yaml.
 */
final class DefaultMappingTest extends KernelTestCase
{
    public function testApplicationSelectsTheDefaultMapping(): void
    {
        $provider = self::getContainer()->get(MappingProvider::class);
        self::assertInstanceOf(MappingProvider::class, $provider);

        $mapping = $provider->default();

        self::assertSame('default', $mapping->name);
        self::assertSame(0, $mapping->sheet);
        self::assertSame(3, $mapping->headerRow);
        self::assertSame(['RouteStopID'], $mapping->rowId);
        // The copy of a row keeps the other columns, but never the personal data (ADR 004).
        self::assertCount(38, $mapping->keepColumns);
        self::assertContains('PlannedArrival', $mapping->keepColumns);
        self::assertContains('blokstart', $mapping->keepColumns);
        // The mapping names the personal columns that it leaves out on purpose.
        self::assertSame(['PersonName', 'Adresse', 'Post-nr', 'By', 'Labels', 'Comment'], $mapping->excludeColumns);
        self::assertFalse($mapping->field(FieldName::PlannedSolo)->required);
        self::assertSame(FieldType::Date, $mapping->field(FieldName::ServiceDate)->type);
        self::assertSame(FieldType::Time, $mapping->field(FieldName::PlannedTime)->type);
        // The transport office measures punctuality against the planned departure.
        self::assertSame('PlannedDeparture', $mapping->field(FieldName::PlannedTime)->column);
        self::assertSame(FieldType::DateTime, $mapping->field(FieldName::ActualTime)->type);
        self::assertSame(['drop_off', 'pickup'], self::sortedValues($mapping->field(FieldName::StopType)->valueMap));
        self::assertSame(['cancelled', 'completed', 'pending'], self::sortedValues($mapping->field(FieldName::StopStatus)->valueMap));
        self::assertCount(1, $mapping->field(FieldName::PlannedSolo)->trueValues);
        self::assertCount(1, $mapping->field(FieldName::PlannedSolo)->falseValues);
        // The fields of the prototype are optional, so that a bad value does not reject the row.
        foreach ([FieldName::Passengers, FieldName::PricePerMinute, FieldName::CancellationNoticeMin, FieldName::CancellationInTime, FieldName::LocationName] as $name) {
            self::assertFalse($mapping->field($name)->required, $name->value);
        }
        self::assertSame('Placename', $mapping->field(FieldName::LocationName)->column);
        self::assertSame(FieldType::Bool, $mapping->field(FieldName::CancellationInTime)->type);
    }

    /**
     * @param array<array-key, string> $valueMap
     *
     * @return list<string>
     */
    private static function sortedValues(array $valueMap): array
    {
        $values = array_values($valueMap);
        sort($values);

        return $values;
    }
}
