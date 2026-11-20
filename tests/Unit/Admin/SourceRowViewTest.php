<?php

declare(strict_types=1);

namespace App\Tests\Unit\Admin;

use App\Admin\SourceColumnDto;
use App\Admin\SourceRowView;
use App\Import\Mapping\FieldMappingDto;
use App\Import\Mapping\FieldName;
use App\Import\Mapping\FieldType;
use App\Import\Mapping\MappingDto;
use PHPUnit\Framework\TestCase;

/**
 * The columns of the copy of a row on the detail pages. The person id never shows.
 */
final class SourceRowViewTest extends TestCase
{
    private const array VALUES = [
        'RouteStopID' => 'S-1',
        'Route' => 'AB-CD-01',
        'PersonID' => 'P-001',
        'Seats' => 2,
        'Solo' => true,
    ];

    public function testColumnsInTheOrderOfTheCopy(): void
    {
        $view = SourceRowView::forStopRow(self::VALUES, self::mapping());

        self::assertFalse($view->mappingMissing);
        self::assertFalse($view->rowKeyHidden);
        self::assertSame(['RouteStopID', 'Route', 'PersonID', 'Seats', 'Solo'], array_map(static fn (SourceColumnDto $column): string => $column->column, $view->columns));
        self::assertSame('AB-CD-01', $view->columns[1]->value);
        self::assertNull($view->columns[1]->message);
        self::assertSame('2', $view->columns[3]->value);
        self::assertFalse($view->columns[1]->hidden);
        self::assertFalse($view->columns[1]->cause);
    }

    /**
     * A boolean shows as a translated yes or no.
     */
    public function testBooleanHasAMessage(): void
    {
        $view = SourceRowView::forStopRow(self::VALUES, self::mapping());

        self::assertNull($view->columns[4]->value);
        self::assertSame('common.yes', $view->columns[4]->message);
    }

    public function testPersonIdIsHidden(): void
    {
        $view = SourceRowView::forStopRow(self::VALUES, self::mapping());

        self::assertHidden($view->columns[2]);
    }

    public function testRowIdThatIsThePersonIdIsHidden(): void
    {
        $view = SourceRowView::forStopRow(self::VALUES, self::mapping(['PersonID']));

        self::assertHidden($view->columns[2]);
        self::assertTrue($view->rowKeyHidden);
        self::assertSame('S-1', $view->columns[0]->value);
    }

    /**
     * PHP gives an int key for a numeric column name.
     */
    public function testNumericColumnName(): void
    {
        $view = SourceRowView::forStopRow([2026 => 'x'], self::mapping());

        self::assertSame('2026', $view->columns[0]->column);
    }

    public function testCauseIsTheColumnOfTheField(): void
    {
        $view = SourceRowView::forRejectedRow(self::VALUES, self::mapping(), FieldName::Route);

        self::assertSame([false, true, false, false, false], self::causes($view));
    }

    public function testNoFieldMakesTheRowIdTheCause(): void
    {
        $view = SourceRowView::forRejectedRow(self::VALUES, self::mapping(['RouteStopID', 'Route']), null);

        self::assertSame([true, true, false, false, false], self::causes($view));
    }

    /**
     * The copy leaves out an empty cell. The cause of an empty field still shows, as an empty
     * value.
     */
    public function testEmptyCauseIsAddedAsEmpty(): void
    {
        $view = SourceRowView::forRejectedRow(['RouteStopID' => 'S-1'], self::mapping(), FieldName::Route);

        self::assertCount(2, $view->columns);
        self::assertSame('Route', $view->columns[1]->column);
        self::assertTrue($view->columns[1]->cause);
        self::assertFalse($view->columns[1]->hidden);
        self::assertSame('driving_data.empty', $view->columns[1]->message);
    }

    public function testPersonIdThatIsTheCauseIsHidden(): void
    {
        $view = SourceRowView::forRejectedRow(self::VALUES, self::mapping(), FieldName::PersonId);

        self::assertTrue($view->columns[2]->cause);
        self::assertHidden($view->columns[2]);
    }

    public function testEmptyPersonIdThatIsTheCauseIsHidden(): void
    {
        $view = SourceRowView::forRejectedRow([], self::mapping(), FieldName::PersonId);

        self::assertTrue($view->columns[0]->cause);
        self::assertHidden($view->columns[0]);
    }

    /**
     * With no mapping, the view cannot tell which column has the person id. It hides the row key
     * too.
     */
    public function testMissingMappingShowsNoColumns(): void
    {
        foreach ([SourceRowView::forStopRow(self::VALUES, null), SourceRowView::forRejectedRow(self::VALUES, null, null)] as $view) {
            self::assertTrue($view->mappingMissing);
            self::assertTrue($view->rowKeyHidden);
            self::assertCount(0, $view->columns);
        }
    }

    private static function assertHidden(SourceColumnDto $column): void
    {
        self::assertTrue($column->hidden);
        self::assertNull($column->value);
        self::assertSame('driving_data.hidden', $column->message);
    }

    /**
     * @return list<bool>
     */
    private static function causes(SourceRowView $view): array
    {
        return array_map(static fn (SourceColumnDto $column): bool => $column->cause, $view->columns);
    }

    /**
     * @param list<string> $rowId
     */
    private static function mapping(array $rowId = ['RouteStopID']): MappingDto
    {
        $fields = [];
        foreach (FieldName::cases() as $field) {
            $column = match ($field) {
                FieldName::Route => 'Route',
                FieldName::PersonId => 'PersonID',
                default => 'Column '.$field->value,
            };
            $fields[$field->value] = new FieldMappingDto($column, FieldType::String, false, null, [], [], []);
        }

        return new MappingDto('default', 0, 1, $rowId, $fields);
    }
}
