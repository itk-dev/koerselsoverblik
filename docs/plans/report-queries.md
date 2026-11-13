# Make the overview and the punctuality page fast with more driving data

**Issue:** [#134](https://github.com/itk-dev/koerselsoverblik/issues/134)

See the [rules for plans](README.md#rules).

## Implementation

### Counted routes

- Add `RouteRepository::countedRouteIds(?int $supplierId): list<int>`. It gives the ids of the
  routes of a sub-agreement that is not ignored, and of the supplier if it is not null.
- Add a `CountedRoutesDto` with two lists: `all` for the supplier tables, and `selected` for the
  totals and the worst routes. `PeriodSelector` or the page builders read it one time for each
  request, and give it to the calculators.
- Replace `IgnoredSubAgreementFilter` and `SupplierFilter` with one filter that adds
  `row.route IN (:countedRoutes)`. An empty list gives no rows. Do not send an empty `IN ()`.
- The repository methods get a `list<int>` of route ids in place of `?int $supplierId`.
- Add `#[ORM\Index(columns: ['route_id', 'service_date'])]` to `StopRow`, and make the migration.
  `route_run` already has the unique key `(route_id, service_date, run)`. Check with `EXPLAIN`
  that a query for 3 routes uses a range on the new index.

### The 12 months

- Add a query for each figure, grouped by `SUBSTRING(row.serviceDate, 1, 7)`. The key is the same
  as `MonthDto::query()`. The range is from the first date of the oldest month to the last date of
  the newest month.
  - `StopRowRepository::countMeasuredStopsByMonth()`: grouped by month and stop type.
  - `RouteRunRepository::countRunFiguresByMonth()`
  - `StopRowRepository::countTripsByMonth()`: `COUNT(DISTINCT ...)` is for each month, so the
    citizens of a month are the same as before.
  - `RouteRunRepository::countSoloRunsByMonth()`
- A month with no rows has no group. It gets the empty figures.
- `PunctualityCalculator` gets a method for the totals of the months, and a method for the total of
  one range. `calculate()` with the figures of each route is only for the selected period.
- `PunctualityPageDto` only reads `total` of the months and of the comparison. Give it
  `PunctualityFiguresDto` for them, not `PunctualityReportDto`.
- When the selected range or the comparison range is equal to the range of a month, take the
  figures of that month. This applies to both builders.

### Tests

- Integration tests for each new query: the same figures as the query for one month, for months
  with data and with no data. An ignored sub-agreement and a supplier filter give the same rows as
  before.
- The existing tests of `PunctualityCalculator`, `PunctualityPageBuilder`,
  `OverviewPageBuilder` and `SupplierTableCalculator` must pass with no change of the expected
  figures.

### Issue #85

- This work closes #85. The pull request deletes [punctuality-months-query.md](punctuality-months-query.md)
  and moves the rows of both issues to "Done" in the [README](README.md).
- #87 adds one query for the locations of the selected period. Use the filter of the counted
  routes in that query.

### Measure

Measure the two pages before and after with the local data, in the Symfony profiler: the time and
the number of queries. If the pages are still slow, the next steps are a covering index for the
measured stops, or a cache that each import and each clear of driving data clears.

## Open points

- None.
