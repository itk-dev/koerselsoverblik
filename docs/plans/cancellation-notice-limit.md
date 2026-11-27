# Calculate the late cancellations with a notice limit from the configuration

**Issue:** not created

See the [rules for plans](README.md#rules).

## Issue text

```markdown
## Background

The tile "For sent aflyst" on the activity page counts the cancelled trips that were cancelled
too late. The import reads the code "in time" from the column `RettidigAflysningsvarsel` of the
extract. The report of the planning system calculates the code, with a fixed limit of 60 minutes.

The transport office must be able to change the limit with no change of the report. Also, the
report has known limits:

- It measures the notice from the planned arrival of the pickup. The punctuality measures from
  the planned departure.
- It counts a day as 1,440 minutes, so the notice is 60 minutes wrong over a change to or from
  summer time.

The copy of a row keeps the date and the time of the cancellation (`aflysningstidspunkt`, #177).
Thus the application can calculate the notice itself.

## Decision

The application calculates the notice of a cancellation from the date and the time of the
cancellation, and the service date and the planned departure of the stop:

    notice = (service date + planned departure) − time of the cancellation

A cancelled trip is cancelled in time when the notice of its pickup is more than
`APP_CANCELLATION_NOTICE_MINUTES` (default 60). PHP calculates the notice in the time zone of the
application, so it is correct over a change to or from summer time. The application no longer
reads `RettidigAflysningsvarsel`.

## Tasks

- Add the optional field `cancelled_at` (`datetime`) to the import mapping. The `default` mapping
  reads it from `aflysningstidspunkt`, and removes the column from `keep_columns`.
- Remove the field `cancelled_in_time`. Move `RettidigAflysningsvarsel` to `keep_columns`, so
  that the copy of a row still has the code of the report.
- Calculate the notice of each stop row at the import, in PHP, as for `delaySeconds`. A stop
  with no time of the cancellation has no notice. A cancellation after the planned time has a
  negative notice.
- Add `APP_CANCELLATION_NOTICE_MINUTES` to `.env`. The totals of each route and day count the
  cancelled pickups in time and too late with this limit, and store the limit, as for
  `APP_PUNCTUALITY_TOLERANCE_MINUTES`. A page fails with a message to run
  `app:driving-data:rebuild-totals` when the stored limit is not the value of the setting.
- Give the development dataset and the test scenarios a time of the cancellation in place of
  the code "in time".
- Describe the setting in the README, and the notice in `docs/architecture.md`. The open
  decision "Cancellation notice" then describes the rule of the application, not the rule of
  the report.

## Acceptance criteria

- A trip at 08:00 that is cancelled at 14:00 the day before is cancelled in time, with a
  notice of 1,080 minutes.
- With a limit of 60 minutes, a trip that is cancelled 61 minutes before the planned departure
  is in time, and a trip that is cancelled 60 minutes before is too late.
- A cancellation that is registered after the planned departure is too late.
- The notice over a change to or from summer time is correct.
- A cancelled pickup with no time of the cancellation counts as "cancelled, unknown", as a
  pickup with no code does now.
- After a change of `APP_CANCELLATION_NOTICE_MINUTES`, the pages fail with a message until
  `app:driving-data:rebuild-totals` has run.
- An extract without `RettidigAflysningsvarsel` imports with no error.
- Line coverage is 100 %.
```

## Implementation

- `StopRowDto` calculates the notice in seconds from `cancelledAt` and `plannedTime`, as it
  calculates `delaySeconds` from `actualTime` and `plannedTime`. `plannedTime` already has the
  date, also for a time of 24:00 or more. Store it as `StopRow::$cancellationNoticeSeconds`
  (`?int`), and remove `StopRow::$cancelledInTime`. Seconds keep the same unit as the delay.
- `StopRowRepository::countDayTotals()` gets the limit in seconds, as `$onTimeSeconds`. A
  cancelled pickup is in time when `row.cancellationNoticeSeconds > :noticeSeconds`, too late
  when it is less or equal, and unknown when it is `NULL`.
- `RouteDayTotals` stores the limit next to `toleranceMinutes`. Make `OtherToleranceException`
  and `OtherToleranceListener` cover both settings, or add a second pair. Prefer one exception
  that names the setting.
- One migration: add the notice column to `stop_row` and the limit column to
  `route_day_totals`, and drop `cancelled_in_time`. The application is not live, so the
  migration fills nothing. Run `app:driving-data:read-copies` on the local data after the
  deploy.
- `DevDatasetGenerator` writes `cancelled_at` as a field, not as a kept value. It writes
  `aflysningsvarsel` and `RettidigAflysningsvarsel` with the new rule of the report: the
  notice from the date and the planned time of the pickup, and "yes" for more than 60 minutes.
- `ScenarioLoader` reads a time of the cancellation (`cancelled_at`) in place of `in_time`.
  Change the scenarios in `tests/fixtures/driving_data/`.
- The validate command shows the notice of a row in the preview in place of the code.

## Order

1. Deploy this change.
2. Ask the planning office to remove `RettidigAflysningsvarsel` from `selectcols` of the report.
   The report still needs the column internally, for `rutestartet`, `blokstart`, `blokslut` and
   `passengersaftercancel`. A missing kept column is not an error.
3. Remove `RettidigAflysningsvarsel` from `keep_columns` when the report no longer exports it.

## Open points

- Is a cancellation after the planned time "too late", or a group of its own? The proposal is
  "too late", as in the report. See "Cancellation notice" in
  [Open decisions](../architecture.md#open-decisions).
- Must `aflysningsvarsel` stay in the copy? The report now calculates it correctly, except over
  a change of summer time. The proposal is to keep it, so that the two notices can be compared.
