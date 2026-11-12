# Move the deletes of the stop row replacer to the repositories

**Issue:** not created

See the [rules for plans](README.md#rules).

## Issue text

```markdown
# Move the deletes of the stop row replacer to the repositories

## Background

`StopRowReplacer::deleteRange()` writes two DQL deletes itself: the stop rows of the range,
and the rejected rows of the range. `RouteBuilder` calls `RouteRunRepository::deleteInRange()`
for the same work on the route runs. The queries of an entity belong in its repository.

## Tasks

- Add `RejectedStopRowRepository` with `deleteInRange(ImportRangeDto $range): void`. It
  deletes the rejected rows with a date in the range, and the rejected rows with no date of the
  earlier batches whose whole range is in the range.
- Add `StopRowRepository::deleteInRange(ImportRangeDto $range): int`. It gives the number of
  deleted rows.
- Let `StopRowReplacer` call the two methods. Delete the rejected rows first, then the stop
  rows, as `deleteRange()` does now.
- Add integration tests for the two repository methods.

**Out of scope:** a change of the behaviour.

## Acceptance criteria

- An import deletes the same rows as before. The tests of `StopRowReplacer` pass with no
  change of the expected values.
- 100 % line coverage in `src/Import`.
```
