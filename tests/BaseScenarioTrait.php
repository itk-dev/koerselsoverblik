<?php

declare(strict_types=1);

namespace App\Tests;

use App\DataFixtures\DrivingData\ExtractRow;
use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Entity\User;
use App\Import\DrivingDataImporter;
use App\Import\ImportRangeDto;
use App\Import\Mapping\MappingProvider;

/**
 * Imports the base scenario on another date, for a test of a kernel.
 */
trait BaseScenarioTrait
{
    /**
     * Imports the 14 stop rows and 4 route runs of the base scenario on the date. A row id is
     * stored on one date only, so the rows of each date get their own ids. The name of the file is
     * the date, for example 2026-01-05.xlsx.
     *
     * @param string $date the date as Y-m-d
     * @param ?User  $user the user of the upload, or null for the console
     */
    private function importBaseOn(string $date, ?User $user = null): void
    {
        $rows = [];
        foreach (new ScenarioLoader()->load(__DIR__.'/fixtures/driving_data/base.yaml')->rows as $row) {
            $values = $row->values;
            foreach (['service_date', 'actual_time'] as $field) {
                if ($values[$field] instanceof \DateTimeImmutable) {
                    $values[$field] = new \DateTimeImmutable($date.' '.$values[$field]->format('H:i:s'));
                }
            }
            $rows[] = new ExtractRow($values, $date.'-'.\count($rows), $row->raw, $row->kept);
        }
        $path = sys_get_temp_dir().'/'.uniqid('extract_', true).'.xlsx';
        $provider = self::getContainer()->get(MappingProvider::class);
        self::assertInstanceOf(MappingProvider::class, $provider);
        new ExtractWriter()->write($path, $provider->default(), $rows);
        $importer = self::getContainer()->get(DrivingDataImporter::class);
        self::assertInstanceOf(DrivingDataImporter::class, $importer);
        $day = new \DateTimeImmutable($date);
        $importer->import($path, ImportRangeDto::create($day, $day, 31), $provider->default(), $user, $date.'.xlsx');
        unlink($path);
    }
}
