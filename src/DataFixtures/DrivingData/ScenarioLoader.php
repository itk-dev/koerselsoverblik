<?php

namespace App\DataFixtures\DrivingData;

use App\Import\Mapping\FieldName;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads a test scenario from a YAML file in tests/fixtures/driving_data/.
 *
 * A scenario has a date, routes with runs and stops, and an "expected" section. Each stop
 * becomes an ExtractRow with application values.
 */
final class ScenarioLoader
{
    /**
     * @throws \InvalidArgumentException if the scenario is not valid
     */
    public function load(string $path): Scenario
    {
        $data = self::map($path, Yaml::parseFile($path, Yaml::PARSE_DATETIME), 'the scenario');
        if (!\array_key_exists('expected', $data)) {
            self::invalid($path, 'The scenario has no "expected" section.');
        }

        $date = self::date($path, $data['date'] ?? null, 'date');
        $range = self::map($path, $data['range'] ?? ['first' => $date, 'last' => $date], 'range');

        $rows = [];
        foreach (self::map($path, $data['routes'] ?? [], 'routes') as $route => $routeData) {
            $runs = self::map($path, self::map($path, $routeData, 'route')['runs'] ?? [], 'runs');
            foreach ($runs as $run => $stops) {
                foreach (self::map($path, $stops, 'stops') as $stop) {
                    $rows[] = self::row($path, (string) $route, (int) $run, $date, self::map($path, $stop, 'stop'));
                }
            }
        }

        $expected = [];
        foreach (self::map($path, $data['expected'], 'expected') as $name => $value) {
            $expected[(string) $name] = $value;
        }

        return new Scenario(
            name: basename($path, '.yaml'),
            firstDate: self::date($path, $range['first'] ?? null, 'range first'),
            lastDate: self::date($path, $range['last'] ?? null, 'range last'),
            rows: $rows,
            omittedFields: array_values(array_map(
                static fn (mixed $name): FieldName => FieldName::tryFrom(self::string($path, $name, 'omit'))
                    ?? self::invalid($path, '"omit" has a name that is not a field.'),
                self::map($path, $data['omit'] ?? [], 'omit'),
            )),
            expected: $expected,
        );
    }

    /**
     * @param array<array-key, mixed> $stop
     */
    private static function row(string $path, string $route, int $run, \DateTimeImmutable $date, array $stop): ExtractRow
    {
        $serviceDate = \array_key_exists('date', $stop) ? self::date($path, $stop['date'], 'date') : $date;
        $actual = $stop['actual'] ?? null;

        return new ExtractRow(
            values: [
                'route' => $route,
                'person_id' => self::string($path, $stop['person'] ?? null, 'person'),
                'service_date' => $serviceDate,
                'run' => $run,
                'stop_type' => self::string($path, $stop['type'] ?? null, 'type'),
                'stop_status' => self::string($path, $stop['status'] ?? 'completed', 'status'),
                'passengers_after' => self::optionalInt($path, $stop['passengers_after'] ?? null, 'passengers_after'),
                'next_distance_km' => self::optionalFloat($path, $stop['km'] ?? null, 'km'),
                'next_duration_min' => self::optionalFloat($path, $stop['min'] ?? null, 'min'),
                'planned_time' => self::time($path, $stop['planned'] ?? null),
                // A time of 24:00 or more is on the next calendar day.
                'actual_time' => null === $actual ? null : $serviceDate->add(self::time($path, $actual)),
                'planned_solo' => self::bool($path, $stop['solo'] ?? false, 'solo'),
            ],
            rowId: \array_key_exists('row_id', $stop) ? self::string($path, $stop['row_id'], 'row_id') : null,
            raw: self::raw($path, $stop['raw'] ?? []),
        );
    }

    /**
     * @return array<string, float|int|string|null>
     */
    private static function raw(string $path, mixed $value): array
    {
        $raw = [];
        foreach (self::map($path, $value, 'raw') as $field => $cell) {
            $raw[(string) $field] = null === $cell || \is_string($cell) || \is_int($cell) || \is_float($cell)
                ? $cell
                : self::invalid($path, '"raw" must have text or numbers.');
        }

        return $raw;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function map(string $path, mixed $value, string $name): array
    {
        return \is_array($value) ? $value : self::invalid($path, \sprintf('"%s" must be a map or a list.', $name));
    }

    private static function string(string $path, mixed $value, string $name): string
    {
        return \is_string($value) ? $value : self::invalid($path, \sprintf('"%s" must be text.', $name));
    }

    private static function bool(string $path, mixed $value, string $name): bool
    {
        return \is_bool($value) ? $value : self::invalid($path, \sprintf('"%s" must be true or false.', $name));
    }

    private static function optionalInt(string $path, mixed $value, string $name): ?int
    {
        return null === $value || \is_int($value) ? $value : self::invalid($path, \sprintf('"%s" must be a whole number.', $name));
    }

    private static function optionalFloat(string $path, mixed $value, string $name): ?float
    {
        if (null === $value) {
            return null;
        }

        return \is_int($value) || \is_float($value) ? (float) $value : self::invalid($path, \sprintf('"%s" must be a number.', $name));
    }

    /**
     * The YAML parser gives a date at midnight in UTC. Use the same calendar date in the default
     * time zone.
     */
    private static function date(string $path, mixed $value, string $name): \DateTimeImmutable
    {
        return $value instanceof \DateTimeInterface
            ? new \DateTimeImmutable($value->format('Y-m-d'))
            : self::invalid($path, \sprintf('"%s" must be a date.', $name));
    }

    /**
     * Reads a time of day as "HH:MM". The hours can be 24 or more, for a time after midnight.
     */
    private static function time(string $path, mixed $value): \DateInterval
    {
        return \is_string($value) && 1 === preg_match('/^(\d{1,2}):(\d{2})$/', $value, $match)
            ? new \DateInterval(\sprintf('PT%dH%dM', $match[1], $match[2]))
            : self::invalid($path, \sprintf('The time "%s" must be hours and minutes, for example "07:30".', \is_string($value) ? $value : get_debug_type($value)));
    }

    private static function invalid(string $path, string $message): never
    {
        throw new \InvalidArgumentException(\sprintf('The scenario "%s" is not valid: %s', $path, $message));
    }
}
