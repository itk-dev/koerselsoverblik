# Calculate the 12 months of the punctuality page in one query

**Issue:** [#85](https://github.com/itk-dev/koerselsoverblik/issues/85)

See the [rules for plans](README.md#rules).

## Implementation

- #87 adds one query for the locations of the period of the page. Count it in the limit of 9
  queries.
- The queries for the months call `IgnoredSubAgreementFilter::apply()`, as
  `countMeasuredStops()` and `countRunFigures()` do.
- For a month that has ended, the selected range is also the last of the 12 months. Use the
  result of that month for the selected period, and do not calculate it two times. This also
  applies to the counts of the overview (`OverviewPageBuilder`).
