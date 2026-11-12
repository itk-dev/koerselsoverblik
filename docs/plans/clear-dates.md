# Show the uploaded dates and clear the driving data of a date

**Issue:** [#81](https://github.com/itk-dev/koerselsoverblik/issues/81)

See the [rules for plans](README.md#rules).

## Implementation

- Use the delete queries of the replacer. `RouteRunRepository::deleteInRange()` exists.
  [stop-row-replacer-deletes.md](stop-row-replacer-deletes.md) moves the two other deletes to
  the repositories. Do that refactor first, or add the two methods in this feature.
- After the deletes of the stop rows and the rejected rows, call
  `SourceRowRepository::deleteOrphans()`, as the replacer does. A DQL delete does not delete the
  copies.
- A rejected row with no date: use the rule of the replacer. The clear deletes it when the whole
  range of its batch is in the cleared range.
- "Clearing driving data" in `docs/architecture.md` names `DailyStats`, which does not exist
  yet. It does not name `RejectedStopRow`. Correct the section in this feature.
