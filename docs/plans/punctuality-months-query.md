# Calculate the 12 months of the punctuality page in one query

| Issue | Status | Needs |
| --- | --- | --- |
| [#85](https://github.com/itk-dev/koerselsoverblik/issues/85) | Blocked | #82 |

See the [rules for plans](README.md#rules).

## Implementation

- #82 changes the periods. Start this feature after #82, so that the grouped query uses the
  final 12 months for each period type.
- #87 adds one query for the locations of the period of the page. Count it in the limit of 9
  queries.
