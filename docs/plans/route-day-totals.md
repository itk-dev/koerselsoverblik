# Calculate the figures from the totals of each route and day

**Issue:** [#136](https://github.com/itk-dev/koerselsoverblik/issues/136)

See the [rules for plans](README.md#rules).

## Implementation

### The table and the build

- `RouteDayTotals` has a unique index on `(route_id, service_date)`, and plain integer columns.
  The table stores all routes, also the routes of an ignored sub-agreement.
- `RouteDayTotalsBuilder::rebuild(ImportRangeDto)` deletes the rows of the range and writes new
  rows. It reads the counts with grouped queries on `stop_row` and `route_run`, grouped by route
  and date, with no `CountedRouteFilter`. Reuse the query builders of #134
  (`measuredStopsQuery()`, `tripsQuery()`, `runFiguresQuery()` and `soloRunsQuery()`) without
  the filter, so that the rules stay in one place.
  - Call it in `DrivingDataImporter::import()` after `RouteBuilder::rebuild()`.
  - Add it to the plan of the clear of dates (#81, [clear-dates.md](clear-dates.md)).
  - The command `driving-data:rebuild-totals` builds the rows of all dates in steps of
    `APP_DRIVING_DATA_MAX_DAYS`.
- The counts on time depend on `APP_PUNCTUALITY_TOLERANCE_MINUTES`. Store the tolerance in each
  row. The read queries compare it with the setting, and fail with a clear message if they
  differ. The message tells the admin to run the command.

### The read

- `RouteDayTotalsRepository` has `total()`, `totalsByMonth()` (with `MonthKey`) and
  `totalsOfEachRoute()`. Each method calls `CountedRouteFilter::apply()`.
- `PunctualityCalculator`, `OverviewPageBuilder` and `SupplierTableCalculator` read from it.
- `StopRowRepository::countTrips()` and `countTripsByMonth()` keep only the citizens.
- Remove the old count queries when no code uses them.

### The citizens index

Measure `(service_date, stop_type, stop_status, route_id, person_id)` on the local data with a
timing script, as in #134. Keep the index only if the 12-month citizens query gets clearly
faster. Write the result in the pull request.

### Tests

- For each column, an integration test that the builder gives the same counts as the queries of
  today, with an ignored sub-agreement and with routes of two suppliers.
- The rebuild of one range does not change other dates.
- The check of the tolerance.
- The existing tests of the calculators and the builders pass with no change of the expected
  figures.
- Compare the figures of the two pages before and after on the local data, as in #134.

## Open points

- Is a stored tolerance with a check sufficient, or must a change of the setting rebuild the
  rows automatically on deploy?
- A cache is still possible later, if the overview is still too slow because of the citizens.
