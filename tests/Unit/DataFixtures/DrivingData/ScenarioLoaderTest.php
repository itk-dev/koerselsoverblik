<?php

namespace App\Tests\Unit\DataFixtures\DrivingData;

use App\DataFixtures\DrivingData\Scenario;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Import\Mapping\FieldName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks that the loader changes a scenario into extract rows with application values.
 */
final class ScenarioLoaderTest extends TestCase
{
    private const string SCENARIOS = __DIR__.'/../../../fixtures/driving_data';

    /** @var list<string> */
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            unlink($path);
        }
    }

    public function testStopGivesTheApplicationValues(): void
    {
        $scenario = $this->load(<<<'YAML'
            date: 2026-01-05
            routes:
              R-01:
                runs:
                  2:
                    - { trip: 1, person: P-001, type: pickup, planned: '07:10', actual: '07:12', passengers_after: 1, km: 3.2, min: 8, solo: true }
            expected: { stop_rows: 1 }
            YAML);

        self::assertStringStartsWith('test_scenario_', $scenario->name);
        self::assertCount(1, $scenario->rows);
        $values = $scenario->rows[0]->values;
        self::assertSame('R-01', $values['route']);
        self::assertSame(2, $values['run']);
        self::assertEquals(new \DateTimeImmutable('2026-01-05'), $values['service_date']);
        self::assertSame('P-001', $values['person_id']);
        self::assertSame('pickup', $values['stop_type']);
        self::assertSame('completed', $values['stop_status']);
        self::assertEquals(new \DateInterval('PT7H10M'), $values['planned_time']);
        self::assertEquals(new \DateTimeImmutable('2026-01-05 07:12'), $values['actual_time']);
        self::assertSame(1, $values['passengers_after']);
        self::assertSame(3.2, $values['next_distance_km']);
        self::assertSame(8.0, $values['next_duration_min']);
        self::assertTrue($values['planned_solo']);
        self::assertNull($scenario->rows[0]->rowId);
        self::assertSame([], $scenario->rows[0]->raw);
        self::assertSame([], $scenario->omittedFields);
        self::assertSame(['stop_rows' => 1], $scenario->expected);
    }

    public function testMissingValuesAreEmptyAndHaveDefaults(): void
    {
        $scenario = $this->load(<<<'YAML'
            date: 2026-01-05
            routes:
              R-01:
                runs:
                  1:
                    - { person: P-001, type: pickup, status: cancelled, planned: '07:10' }
            expected: { stop_rows: 1 }
            YAML);

        $values = $scenario->rows[0]->values;
        self::assertSame('cancelled', $values['stop_status']);
        self::assertFalse($values['planned_solo']);
        self::assertNull($values['actual_time']);
        self::assertNull($values['passengers_after']);
        self::assertNull($values['next_distance_km']);
        self::assertNull($values['next_duration_min']);
    }

    public function testTimeAfterMidnightKeepsTheServiceDate(): void
    {
        $scenario = $this->load(<<<'YAML'
            date: 2026-01-05
            routes:
              R-01:
                runs:
                  1:
                    - { person: P-001, type: drop_off, planned: '24:30', actual: '24:40' }
            expected: { stop_rows: 1 }
            YAML);

        $values = $scenario->rows[0]->values;
        self::assertEquals(new \DateTimeImmutable('2026-01-05'), $values['service_date']);
        self::assertEquals(new \DateInterval('PT24H30M'), $values['planned_time']);
        self::assertEquals(new \DateTimeImmutable('2026-01-06 00:40'), $values['actual_time']);
    }

    public function testStopCanSetTheDateTheRowIdAndRawValues(): void
    {
        $scenario = $this->load(<<<'YAML'
            date: 2026-01-05
            omit: [planned_solo]
            routes:
              R-01:
                runs:
                  1:
                    - { person: P-001, type: pickup, planned: '07:10', date: 2026-01-07, row_id: X-1, raw: { run: abc } }
            expected: { stop_rows: 1 }
            YAML);

        $row = $scenario->rows[0];
        self::assertEquals(new \DateTimeImmutable('2026-01-07'), $row->values['service_date']);
        self::assertSame('X-1', $row->rowId);
        self::assertSame(['run' => 'abc'], $row->raw);
        self::assertSame([FieldName::PlannedSolo], $scenario->omittedFields);
    }

    public function testRangeIsTheDateUnlessTheScenarioSetsOne(): void
    {
        $oneDay = $this->load(<<<'YAML'
            date: 2026-01-05
            routes: {}
            expected: { stop_rows: 0 }
            YAML);
        $range = $this->load(<<<'YAML'
            date: 2026-01-05
            range: { first: 2026-01-04, last: 2026-01-07 }
            routes: {}
            expected: { stop_rows: 0 }
            YAML);

        self::assertEquals(new \DateTimeImmutable('2026-01-05'), $oneDay->firstDate);
        self::assertEquals(new \DateTimeImmutable('2026-01-05'), $oneDay->lastDate);
        self::assertEquals(new \DateTimeImmutable('2026-01-04'), $range->firstDate);
        self::assertEquals(new \DateTimeImmutable('2026-01-07'), $range->lastDate);
    }

    public function testRowsFollowTheRoutesRunsAndStops(): void
    {
        $scenario = $this->load(<<<'YAML'
            date: 2026-01-05
            routes:
              R-01:
                runs:
                  1:
                    - { person: P-001, type: pickup, planned: '07:10' }
                    - { person: P-001, type: drop_off, planned: '07:30' }
                  2:
                    - { person: P-002, type: pickup, planned: '09:00' }
              R-02:
                runs:
                  1:
                    - { person: P-003, type: pickup, planned: '08:00' }
            expected: { stop_rows: 4 }
            YAML);

        self::assertSame(
            [['R-01', 1, 'P-001'], ['R-01', 1, 'P-001'], ['R-01', 2, 'P-002'], ['R-02', 1, 'P-003']],
            array_map(static fn ($row): array => [$row->values['route'], $row->values['run'], $row->values['person_id']], $scenario->rows),
        );
    }

    public function testScenarioWithoutExpectedIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/test_scenario_.*\.yaml.*"expected"/');

        $this->load(<<<'YAML'
            date: 2026-01-05
            routes: {}
            YAML);
    }

    public function testTimeThatIsNotHoursAndMinutesIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/test_scenario_.*\.yaml.*"7.10"/');

        $this->load(<<<'YAML'
            date: 2026-01-05
            routes:
              R-01:
                runs:
                  1:
                    - { person: P-001, type: pickup, planned: '7.10' }
            expected: { stop_rows: 1 }
            YAML);
    }

    /**
     * The scenario files that the tests and the local site use.
     */
    #[DataProvider('scenarioFiles')]
    public function testScenarioFileLoads(string $path): void
    {
        $scenario = (new ScenarioLoader())->load($path);

        self::assertNotSame([], $scenario->rows);
        self::assertNotSame([], $scenario->expected);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function scenarioFiles(): iterable
    {
        foreach (glob(self::SCENARIOS.'/*.yaml') ?: [] as $path) {
            yield basename($path, '.yaml') => [$path];
        }
    }

    private function load(string $yaml): Scenario
    {
        $path = sys_get_temp_dir().'/'.uniqid('test_scenario_', true).'.yaml';
        file_put_contents($path, $yaml);
        $this->paths[] = $path;

        return (new ScenarioLoader())->load($path);
    }
}
