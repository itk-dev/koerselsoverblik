# Calculate the 12 months of the punctuality page in one query

**Issue:** [#85](https://github.com/itk-dev/koerselsoverblik/issues/85)

See the [rules for plans](README.md#rules).

## Implementation

- #87 adds one query for the locations of the period of the page. Count it in the limit of 9
  queries.
- The queries for the months use the same filter as `countMeasuredStops()` and
  `countRunFigures()`: join the sub-agreement, and skip an ignored sub-agreement.
