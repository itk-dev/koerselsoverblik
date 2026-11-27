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
        // The list has only the columns of the current report.
        self::assertCount(20, $mapping->keepColumns);
        self::assertContains('TripID', $mapping->keepColumns);
        self::assertContains('blokstart', $mapping->keepColumns);
        self::assertNotContains('PlannedArrival', $mapping->keepColumns);
        // No feature uses these columns of the prototype yet, so they are not fields. The vehicle
        // id, the vehicle type, the driver type and the operator are for the number of vehicles
        // (#181).
        foreach (['passengers', 'prisprminut', 'aflysningsvarsel', 'aflysningstidspunkt', 'afbestillingskanal', 'aflysningstype', 'aflysningskategori', 'chauffør', 'vogn', 'vogntype', 'chaufførtype', 'operatør'] as $column) {
            self::assertContains($column, $mapping->keepColumns);
        }
        // The driver id is personal data, so the pages never show it. The other columns of #181
        // are not personal data.
        self::assertSame(['chauffør'], $mapping->hiddenColumns);
        // The code of a late cancellation is a field, so it is not also a kept column. It is
        // optional, so that a new code gives a warning and does not reject the row.
        self::assertNotContains('RettidigAflysningsvarsel', $mapping->keepColumns);
        self::assertSame('RettidigAflysningsvarsel', $mapping->field(FieldName::CancelledInTime)->column);
        self::assertSame(FieldType::Bool, $mapping->field(FieldName::CancelledInTime)->type);
        self::assertFalse($mapping->field(FieldName::CancelledInTime)->required);
        self::assertSame(['yes'], $mapping->field(FieldName::CancelledInTime)->trueValues);
        self::assertSame(['no'], $mapping->field(FieldName::CancelledInTime)->falseValues);
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
        // The location name is optional, so that a bad value does not reject the row.
        self::assertFalse($mapping->field(FieldName::LocationName)->required);
        self::assertSame('Placename', $mapping->field(FieldName::LocationName)->column);
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
