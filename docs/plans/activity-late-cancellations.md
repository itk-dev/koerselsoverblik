# Show the late cancellations on the driving activity page

**Issue:** [#167](https://github.com/itk-dev/koerselsoverblik/issues/167)

See the [rules for plans](README.md#rules).

## Implementation

- The development dataset already fills `RettidigAflysningsvarsel` (`DevDatasetGenerator`): the
  value of the trip for a cancelled trip, and "no" for the other trips. This agrees with the rule.
- A scenario row can give kept values (`ExtractRow::$kept`). Make the code a typed field in the
  scenarios.
- The trip counters use `Status`, and the code of the report uses `IsCancelled`. Check that the
  two columns agree in the reference extract. If a row has `Status` "Cancelled" and
  `IsCancelled` false, its code is "no", and it counts as too late.
- Add the counters as `notCancelledRuns` was added (#163): the entity, `DayTotalsDto::FIELDS`,
  the constructor, `getTotals()`, the builder query in `StopRowRepository::countDayTotals()` and
  a migration. Run `app:driving-data:rebuild-totals` after the deploy.
- `DayTotalsDto` can give the share of the late cancellations, as `cancelledPercent()`.

## Open points

- None for this issue. The notice in minutes and the notice bands wait for the open questions
  of "Cancellation notice" in [Open decisions](../architecture.md#open-decisions).
