# Store the citizens of each month

**Issue:** [#138](https://github.com/itk-dev/koerselsoverblik/issues/138)

See the [rules for plans](README.md#rules).

The branch starts from the branch of #136 (PR #137), because it uses `RouteDayTotals` and
`StopRowRepository::countCitizensByMonth()`. Merge `develop` into it when PR #137 is merged.

## Implementation

### The table

- `MonthCitizens` has the first date of the month, a nullable supplier and the citizens.
- A `NULL` supplier is the count for all suppliers. MariaDB allows more than one `NULL` in a
  unique index, so the unique index on `(month, supplier_id)` does not protect that row. The
  builder deletes the rows of a month before it writes them. Add a test that a month has one row
  for all suppliers.
- The foreign key to the supplier needs `ON DELETE CASCADE`, or no foreign key. Otherwise the
  delete of a supplier fails.
- A supplier with no citizens in a month has no row. The read gives 0 for a missing row.

### The build

- `MonthCitizensBuilder::rebuild(DateRangeDto $months, ?array $supplierIds = null)` builds the
  counts of the full months of the range. Reuse `StopRowRepository::countCitizensByMonth()`
  (#136): one call for all suppliers (`null`), and one call for each supplier. The rules of the
  citizens then stay in one place.
- An import builds the full months of its range: from the first day of the month of the first
  date to the last day of the month of the last date. Call it in `DrivingDataImporter::import()`
  after `RouteDayTotalsBuilder`.
- `SubAgreementController` (new and edit) builds all months again: for all suppliers, and for
  the old and the new supplier of the sub-agreement. `SupplierController` delete needs no build,
  because a supplier with sub-agreements cannot be deleted.
- `app:driving-data:rebuild-totals` also builds all months, for all suppliers and each supplier.
- Add it to the plan of the clear of dates (#81, [clear-dates.md](clear-dates.md)): build the
  months of the cleared dates again.

### The read

- `MonthCitizensRepository::byMonth(DateRangeDto $months, ?int $supplierId): array<string, int>`,
  keyed by `MonthDto::query()`.
- `OverviewPageBuilder` reads the citizens of the 12 months from it, in place of
  `countCitizensByMonth()`. The selected period and the comparison keep `countCitizens()`.

### Small gain

The overview adds the totals of the 12 months twice: in `PunctualityPageBuilder` and in
`OverviewPageBuilder` (approximately 0.15 s each). Give the overview the sums from one call, if
this is simple.

### Tests

- The builder gives the same counts as `countCitizensByMonth()`, for all suppliers and for each
  supplier, with an ignored sub-agreement and a route with no supplier.
- An import builds only the months of its range.
- A change of the ignored flag and a change of the supplier of a sub-agreement give new counts.
- The existing tests of the overview pass with no change of the expected figures.
- Compare the figures of the overview before and after on the local data, as in #136.

## Open points

- A change of a sub-agreement reads all months of `stop_row` for up to three scopes. With one
  year of data, that takes a few seconds in the save request, and it grows with the history.
  Measure it. If it is too slow, build the counts after the response, or in a queue.
- The retention job of ADR 004 deletes old stop rows. The counts of those months then cannot be
  built again. Decide whether the counts stay, or are deleted with the stop rows.
