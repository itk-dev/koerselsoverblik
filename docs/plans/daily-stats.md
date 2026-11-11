# Aggregate the daily stats

**Issue:** not created. Its row in [Driving data](README.md#driving-data) shows what the work
needs.

See the [rules for plans](README.md#rules).

## Issue text

Write the issue text when the transport office answers the two open decisions. The answers
decide how the trips, the peak passengers, the solo runs and the empty kilometres count.

## Implementation

The goal: one `DailyStats` row for each date and supplier in the range. The overview reads its
figures from these rows.

- Add the peak passengers, the driven and the empty kilometres, and the driven and the empty
  minutes to `RouteRun`. The route run feature (#57) left them out until the answer.
- Add the `DailyStats` entity and a migration, with a unique index on
  `(service_date, supplier_id)`.
  - MariaDB allows more than one `NULL` in a unique index, so the index does not protect the
    "Unknown" row. The aggregator must write one row only. Add a test for this.
- Add `DailyStatsCalculator`, a plain class. It gets the route runs and the stops of one date
  and one supplier, and gives the counters:
  - trips: the pickups, also the cancelled pickups and the pickups with no registration (the
    proposal in the open decision)
  - completed and cancelled trips, from the status of the pickup
  - route runs, last drop-offs, on time, solo runs, and the driven and empty kilometres and
    minutes
- Add `DailyStatsAggregator::rebuild()`. It finds the supplier of each route from the
  sub-agreement of the route, and replaces the rows of the range.
- The aggregator skips the routes of an ignored sub-agreement. Its queries call
  `IgnoredSubAgreementFilter::apply()`.
- When the supplier or the ignored flag of a sub-agreement changes, rebuild all dates of the
  routes of the sub-agreement. `SubAgreementController::new()` and `edit()` save both.
- Add the step to `DrivingDataImporter::import()`, in the same transaction as the other steps.
- Add `DailyStats` to `DrivingDataClearer` (#81).
- The `base` scenario must give its `expected` totals.
- The citizens figure is not in `DailyStats`. The overview counts the distinct person ids from
  `StopRow`.

## Open points

- When the daily stats exist, #85 can read the totals of the months from them.
