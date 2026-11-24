# Review follow-up of the driving activity page

**Issue:** [#165](https://github.com/itk-dev/koerselsoverblik/issues/165)

See the [rules for plans](README.md#rules).

## Implementation

- `DayTotalsDto::cancelledPercent()` gives null for no trips. `ActivityPageDto::cancelled()` and
  the supplier table of the template use it.
- Remove `ActivitySupplierRowDto::cancelledPercent()`. Keep `completedPerRun()`.
- The template reads `page.selected`. The tests that use `current()` read `selected`.
- The sentence goes in `activity.lede`. Remove `activity.sub_agreements.foot`.
- `SupplierRowDto::cancelledPercent()` and `ConclusionCalculator` stay as they are. They use
  `TripCountDto` and plain counts, not `DayTotalsDto`.

## Open points

- None.
