# Read the stored copies again through a changed mapping

**Issue:** [#61](https://github.com/itk-dev/koerselsoverblik/issues/61)

See the [rules for plans](README.md#rules).

## Implementation

- `SourceCells::cells()` gives the cells of a copy, keyed by the column name. `StopRowParser`
  reads a row of a sheet with the index of each column. The command needs an entry point that
  reads cells keyed by the column name, with the same conversion and the same rejection rules.
- Copy the replacement in one transaction from `DrivingDataImporter`, and call the same builders:
  `RouteBuilder`, `RouteDayTotalsBuilder`, `LocationDayTotalsBuilder` and
  `MonthCitizensBuilder`.
- Read the copies in groups, so that the memory does not grow with the range (see
  [Keep the memory of the import flat](import-memory.md)).

## Open points

- A copy leaves out the empty cells, so it has no header. A field column that is in no copy of
  the range can be empty in all rows, or not in the extract. Proposal: a required field gives a
  rejected row, and an optional field gives no value and a warning.
- Can the range have all the stored dates, or only the maximum range of an upload
  (`APP_DRIVING_DATA_MAX_DAYS`)?
- Must the command also work with a mapping that has another row id?
