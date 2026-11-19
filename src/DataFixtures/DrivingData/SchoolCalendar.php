<?php

declare(strict_types=1);

namespace App\DataFixtures\DrivingData;

/**
 * The school holidays of Aarhus Kommune, so that the development dataset has the volume of the
 * school transport: full on a school day, low in a holiday, and only booked trips at the weekend.
 *
 * Sources: https://aarhus.dk/borger/pasning-skole-og-uddannelse/skole-sfo-og-klub/naar-dit-barn-gaar-i-skole/ferier-og-fridage
 * (2026/27 and 2027/28) and https://publicholidays.dk/da/school-holidays/aarhus/ (2025/26). Add
 * a school year when the municipality publishes it. A date that is not in a listed school year
 * is a school day, except at the weekend and on Constitution Day.
 */
final class SchoolCalendar
{
    /**
     * The summer holidays, from the first to the last day, both included.
     */
    private const array SUMMER = [
        ['2025-06-28', '2025-08-10'],
        ['2026-06-27', '2026-08-10'],
        ['2027-06-26', '2027-08-08'],
    ];

    /**
     * The other holidays and free days, from the first to the last day, both included.
     */
    private const array OTHER = [
        // 2025/26
        ['2025-10-11', '2025-10-19'],
        ['2025-12-23', '2026-01-04'],
        ['2026-02-07', '2026-02-15'],
        ['2026-03-28', '2026-04-06'],
        ['2026-05-14', '2026-05-15'],
        ['2026-05-23', '2026-05-25'],
        // 2026/27
        ['2026-10-10', '2026-10-18'],
        ['2026-12-19', '2027-01-03'],
        ['2027-02-13', '2027-02-21'],
        ['2027-03-20', '2027-03-29'],
        ['2027-05-06', '2027-05-07'],
        ['2027-05-15', '2027-05-17'],
        // 2027/28
        ['2027-10-16', '2027-10-24'],
        ['2027-12-18', '2028-01-02'],
        ['2028-02-12', '2028-02-20'],
        ['2028-04-08', '2028-04-17'],
        ['2028-05-01', '2028-05-01'],
        ['2028-05-25', '2028-05-26'],
        ['2028-06-03', '2028-06-05'],
    ];

    public function isWeekend(\DateTimeImmutable $day): bool
    {
        return (int) $day->format('N') >= 6;
    }

    /**
     * Tells if a weekday is a free day for the schools. Constitution Day (5 June) is not in the
     * lists, but the school transport has a low volume on that day.
     */
    public function isHoliday(\DateTimeImmutable $day): bool
    {
        return '06-05' === $day->format('m-d') || $this->isSummerHoliday($day) || self::inRanges($day, self::OTHER);
    }

    public function isSummerHoliday(\DateTimeImmutable $day): bool
    {
        return self::inRanges($day, self::SUMMER);
    }

    /**
     * @param list<array{string, string}> $ranges
     */
    private static function inRanges(\DateTimeImmutable $day, array $ranges): bool
    {
        $date = $day->format('Y-m-d');
        foreach ($ranges as [$first, $last]) {
            if ($date >= $first && $date <= $last) {
                return true;
            }
        }

        return false;
    }
}
