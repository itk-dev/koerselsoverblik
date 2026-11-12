# Show the uploaded dates and clear the driving data of a date

**Issue:** [#81](https://github.com/itk-dev/koerselsoverblik/issues/81)

See the [rules for plans](README.md#rules).

## Implementation

- `DrivingDataClearer` calls `StopRowRangeDeleter::deleteRange()` and
  `RouteRunRepository::deleteInRange()`. [range-deletes.md](range-deletes.md) adds the deleter.
  It deletes the rejected rows, the stop rows and their copies. Do not delete the copies in
  this feature.
- A rejected row with no date: use the rule of the replacer. The clear deletes it when the whole
  range of its batch is in the cleared range.
- "Clearing driving data" in `docs/architecture.md` names `DailyStats`, which does not exist
  yet. It does not name `RejectedStopRow`. Correct the section in this feature.

## Open points

- The issue text tells the clear to keep the `DailyCount` rows. No `DailyCount` entity exists.
  The clear keeps the `ImportBatch` rows only.
- The components diagram in `docs/architecture.md` names a `DailyCount` controller and a
  `DailyCountWeek` form. They do not exist. Remove them in this feature.
