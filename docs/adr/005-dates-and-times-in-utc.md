# 005: Dates and times in UTC

| Field | Value |
| --- | --- |
| **Created By** | Troels Ugilt Jensen |
| **Date** | 2026-11-05 |
| **Decision Maker** | ITK Dev developers |
| **Stakeholders** | ITK Dev developers; the users of the overview |
| **Status** | Draft |

## Context

The extract parser gives the times of an extract in UTC
([issue #36](https://github.com/itk-dev/koerselsoverblik/issues/36)). The rest of the application
used the PHP default time zone. In the `itkdev/php8.4-fpm` image, `PHP_TIMEZONE` is
`Europe/Copenhagen`. MariaDB uses UTC, and the columns are `DATETIME`, with no time zone.

Doctrine writes the wall-clock time of a PHP object, and reads it back in the PHP default time
zone. A UTC value from the parser is then read back one or two hours wrong. The stop rows of the
import store such values ([issue #47](https://github.com/itk-dev/koerselsoverblik/issues/47)).

### Drivers

- **Functional:** the delay of a route run must be correct, also across a change to or from
  summer time. A user must see the times in local time.
- **Non-functional:** one rule for all stored values. The tests must not depend on the time zone
  of the container.

### Options Considered

1. **UTC in the application and the database, local time in the browser.**
   - Each stored time has one meaning. A delay is a subtraction of two UTC values.
   - Doctrine types must convert the values to UTC. The templates and the forms must convert
     them to local time.
2. **Local time (`Europe/Copenhagen`) everywhere.**
   - No conversion in the templates.
   - One hour occurs two times on the night of the change to winter time. A stored value in that
     hour is ambiguous, and a delay across the change is one hour wrong.
   - The parser output must change.
3. **A time zone or an offset for each stored value.**
   - MariaDB has no `DATETIME` type with a time zone. Doctrine `datetimetz` stores no time zone on
     MariaDB. Each value needs an extra column.

## Decision

Option 1. The application stores and calculates all dates and times in UTC. Only the browser
shows local time, in `APP_TIMEZONE` (default `Europe/Copenhagen`).

| Value | In the application and the database | In the browser |
| --- | --- | --- |
| `planned_time`, `actual_time` | A full date and time in UTC. A `time` field is the service date plus the time, read in `APP_TIMEZONE`. A `DATETIME` column, no `TIME` column. | The time in `APP_TIMEZONE`. 25:10 shows as 01:10 on the next day. |
| `service_date`, the dates of an upload range, the dates of an assignment | A calendar date at 00:00 UTC. It is never converted. | The date with no conversion. |
| `last_login_at` | A full date and time in UTC. | The time in `APP_TIMEZONE`. |

The rules:

- The Doctrine types `datetime` and `datetime_immutable` are `UTCDateTimeType` and
  `UTCDateTimeImmutableType`. They write a value in UTC, and read it back in UTC. The PHP default
  time zone has no effect on the database. This is the approach of ADR 008 in
  [event-database-imports](https://github.com/itk-dev/event-database-imports).
- `Kernel::boot()` sets the PHP default time zone to UTC, for the web and the console.
  `phpunit.dist.xml` sets `date.timezone` to UTC, for the unit tests.
- The Twig `date` filter uses `APP_TIMEZONE`. A calendar date uses `|date(format, false)`.
- `TimeZoneTypeExtension` gives `DateTimeType` and `TimeType` the `model_timezone` UTC and the
  `view_timezone` from `APP_TIMEZONE`. A `TimeType` field converts only with a `reference_date`,
  because a time with no date has no offset. A `DateType` field has no conversion.
- The code gets the time from `ClockInterface`, not from `NOW()` or `CURRENT_TIMESTAMP` in SQL
  or `new \DateTimeImmutable('now')`.
- `LocalCalendar::today()` gives the current date in `APP_TIMEZONE`, as a calendar date at
  00:00 UTC.

## Consequences

### Positive

- A delay across a change to or from summer time is correct.
- A stored time has one meaning, also in the hour that occurs two times.
- The tests give the same result in all containers.

### Negative / Trade-offs

- The templates must convert the times, and must not convert the calendar dates. A missing
  `false` shows a calendar date one day early in a time zone west of UTC.
- A migration converts the stored `user.last_login_at` values from `Europe/Copenhagen` to UTC.
  The hour from 02:00 to 03:00 occurs two times on the night of the change to winter time:
  - `up()` reads a value in that hour as winter time. A login in the first of the two hours
    becomes one hour too late.
  - `down()` gives two UTC times in that night the same local time. A login stored after the
    migration can then become one hour wrong.
  - `up()` followed by `down()` gives the old values.
- The log times are in UTC.

### Follow-up Actions

- [ ] Give a `TimeType` field a `reference_date` when it must show local time.

## References

- [ADR 008: Enforce UTC in the database and ORM layer](https://github.com/itk-dev/event-database-imports/blob/develop/docs/adr/008-enforce-utc-in-database-and-orm.md)
  in event-database-imports.
- [Working with DateTime instances](https://www.doctrine-project.org/projects/doctrine-orm/en/current/cookbook/working-with-datetime.html)
  in the Doctrine ORM cookbook. The section "Handling different Timezones with the DateTime Type"
  describes the UTC type.
