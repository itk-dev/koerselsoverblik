# 005: Dates and times in one time zone

| Field | Value |
| --- | --- |
| **Created By** | Ture Gjørup |
| **Date** | 2026-11-06 |
| **Decision Maker** | ITK Dev developers |
| **Stakeholders** | ITK Dev developers; the operators of the servers; the users of the overview |
| **Status** | Approved |

## Context

The extract parser read the wall-clock times of an extract in `APP_TIMEZONE`, and gave them in
UTC ([issue #36](https://github.com/itk-dev/koerselsoverblik/issues/36)). Doctrine writes the
wall-clock time of a PHP object to a `DATETIME` column, with no time zone. It reads the value
back in the PHP default time zone, which is `Europe/Copenhagen`. Thus a UTC value from the parser
comes back one or two hours wrong
([issue #47](https://github.com/itk-dev/koerselsoverblik/issues/47)).

All parts of the system use `Europe/Copenhagen`, except the parser output:

| Part | Time zone |
| --- | --- |
| The driving data | Local wall-clock times. One source. A time can be 24:00 or more on the service date. |
| PHP, in development and on the servers | `Europe/Copenhagen`. All environments use the `itkdev/php8.4-fpm` image, with `PHP_TIMEZONE=Europe/Copenhagen`. |
| MariaDB on the servers | `SYSTEM`, and the host is `Europe/Copenhagen`. MariaDB has no time zone for each database. |
| MariaDB in development | `Europe/Copenhagen`, with `TZ` on the `mariadb` service. This is the same as [devops_itkdev-docker#164](https://github.com/itk-dev/devops_itkdev-docker/pull/164). |

### Drivers

- **Functional:** the figures are for each day and each time of day, in local time. A delay must
  be correct, also across a change to or from summer time.
- **Non-functional:** one time zone in all parts, and one setting for it. No conversions. No
  dependency on the time zone tables in MariaDB, because the servers do not have them.

### Options Considered

1. **UTC in the application and the database, local time in the browser.** This is ADR 008 in
   [event-database-imports](https://github.com/itk-dev/event-database-imports).
   - A stored time has one meaning, also in the hour that occurs two times.
   - Custom Doctrine types, a Twig setting and a form extension must convert the values. A
     `TimeType` field with no reference date cannot convert.
   - A grouping by day or by hour in SQL needs `CONVERT_TZ()`, and thus the time zone tables.
   - The application uses a different time zone from PHP, MariaDB and the data.
   - [PR #48](https://github.com/itk-dev/koerselsoverblik/pull/48) implements this option.
2. **One time zone for the data, PHP and MariaDB.**
   - The stored value is the value in the extract and in the browser. No conversion.
   - `DATE()` and `HOUR()` in SQL give local days and hours.
   - The hour from 02:00 to 03:00 occurs two times on the night of the change to winter time. A
     stored value in that hour is ambiguous. The extract has the same ambiguity.
3. **A time zone or an offset for each stored value.**
   - MariaDB has no `DATETIME` type with a time zone. Each value needs an extra column.

## Decision

Option 2. The application supports one time zone only. The driving data, PHP and MariaDB must
use the same time zone. The application stores, calculates and shows all dates and times in that
time zone. It has no time zone setting of its own, and it converts no values.

This is a deliberate difference from ADR 008 in event-database-imports. That project has many
sources with different time zones. This project has one source with local wall-clock times.

### Requirements for the operators

The operators must make sure that these three are the same time zone, `Europe/Copenhagen`:

- The time zone of the wall-clock times in the driving data.
- `date.timezone` in `php.ini`. The `itkdev/php8.4-fpm` image sets it from `PHP_TIMEZONE`.
- The time zone of MariaDB: `@@global.time_zone`, or the system time zone of the host when the
  value is `SYSTEM`.

The application does not check this. If the time zones are different, the stored times are one or
two hours wrong, and no error shows.

### Rules for the code

- Doctrine uses the default `datetime` and `date` types. They write and read in the PHP default
  time zone.
- Make a date or a time in the PHP default time zone. Do not give a time zone to a PHP object,
  except to read a value from a source in UTC.
- `StopRowDto` gives the times in the PHP default time zone. `service_date` and the dates of
  `ImportRangeDto` are calendar dates at 00:00 in the same time zone.
- `phpunit.dist.xml` sets `date.timezone` to `Europe/Copenhagen`, so that the tests do not depend
  on `php.ini`.
- Calculate a duration from `getTimestamp()`. Do not calculate it with `TIMESTAMPDIFF()` in SQL,
  because a change to or from summer time then gives one hour too much or too little.
- The code gets the time from `ClockInterface`, not from `NOW()` or `CURRENT_TIMESTAMP` in SQL.
  These give the correct time when the operators meet the requirements, but the code is then
  easier to test.

## Consequences

### Positive

- No conversion in the templates, the forms or SQL.
- The stored values are the values in the extract.
- No migration. The stored `user.last_login_at` values are already in `Europe/Copenhagen`.

### Negative / Trade-offs

- The correct time zone is a requirement for the operators. The application cannot find an
  error in the configuration.
- A value in the hour from 02:00 to 03:00 on the night of the change to winter time is read as
  winter time. The driving data has few or no trips in that hour.
- A PHP object in another time zone is written with its own wall-clock time, with no error.
- If a second source with times in other time zones comes, examine this decision again.

## References

- [ADR 008: Enforce UTC in the database and ORM layer](https://github.com/itk-dev/event-database-imports/blob/develop/docs/adr/008-enforce-utc-in-database-and-orm.md)
  in event-database-imports.
- [devops_itkdev-docker#164](https://github.com/itk-dev/devops_itkdev-docker/pull/164): the
  `mariadb` service uses `Europe/Copenhagen`.
- [MariaDB time zones](https://mariadb.com/kb/en/time-zones/).
