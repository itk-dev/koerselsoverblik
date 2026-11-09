<?php

declare(strict_types=1);

namespace App\DataFixtures\DrivingData;

use App\Import\Mapping\FieldName;

/**
 * A test scenario: the rows of an extract, and the totals that an import of it must give.
 */
final readonly class Scenario
{
    /**
     * @param \DateTimeImmutable      $firstDate     the first date of the upload range
     * @param \DateTimeImmutable      $lastDate      the last date of the upload range
     * @param list<ExtractRow>        $rows
     * @param list<FieldName>         $omittedFields the fields that get no column in the file
     * @param array<array-key, mixed> $expected      the totals, worked out by hand, keyed by name
     */
    public function __construct(
        public string $name,
        public \DateTimeImmutable $firstDate,
        public \DateTimeImmutable $lastDate,
        public array $rows,
        public array $omittedFields,
        public array $expected,
    ) {
    }
}
