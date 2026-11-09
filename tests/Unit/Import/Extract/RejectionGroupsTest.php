<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import\Extract;

use App\Import\Extract\RejectionCountDto;
use App\Import\Extract\RejectionGroups;
use App\Import\Extract\RejectionReason;
use App\Import\Mapping\FieldName;
use PHPUnit\Framework\TestCase;

/**
 * Checks that rejected rows and warnings are counted in groups of the same field and reason, with
 * no values.
 */
final class RejectionGroupsTest extends TestCase
{
    public function testNoRowsGiveNoGroups(): void
    {
        $groups = new RejectionGroups();

        self::assertSame([], $groups->groups());
        self::assertSame(0, $groups->total());
    }

    /**
     * The groups are in the order of the first row of each group. The row id has no field.
     */
    public function testRowsWithTheSameFieldAndReasonAreOneGroup(): void
    {
        $groups = new RejectionGroups();
        $groups->add(FieldName::Run, RejectionReason::NotWholeNumber, 4);
        $groups->add(null, RejectionReason::InvalidRowId, 5);
        $groups->add(FieldName::Run, RejectionReason::NotWholeNumber, 7);
        $groups->add(FieldName::Run, RejectionReason::Empty, 8);

        self::assertEquals([
            new RejectionCountDto(FieldName::Run, RejectionReason::NotWholeNumber, 2, [4, 7]),
            new RejectionCountDto(null, RejectionReason::InvalidRowId, 1, [5]),
            new RejectionCountDto(FieldName::Run, RejectionReason::Empty, 1, [8]),
        ], $groups->groups());
        self::assertSame(4, $groups->total());
    }

    public function testGroupKeepsTheFirstTenRowNumbers(): void
    {
        $groups = new RejectionGroups();
        foreach (range(1, 12) as $rowNumber) {
            $groups->add(FieldName::Route, RejectionReason::Empty, $rowNumber);
        }

        [$group] = $groups->groups();
        self::assertSame(12, $group->count);
        self::assertSame(range(1, 10), $group->rowNumbers);
    }
}
