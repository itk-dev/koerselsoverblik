# Delete only the copies of the deleted rows

**Issue:** not created

See the [rules for plans](README.md#rules).

## Issue text

```markdown
# Delete only the copies of the deleted rows

## Background

`StopRowReplacer` deletes the stop rows and the rejected rows of a range. Then it calls
`SourceRowRepository::deleteOrphans()`. This delete reads all `source_row` rows, also the
copies of the other dates. The table grows with each import, about XX rows after one year.

The delete runs in the import transaction. Thus it locks each scanned `source_row` row until
the commit.

## Tasks

- Before the deletes, select the ids of the copies of the stop rows and the rejected rows that
  the replacer deletes.
- After the deletes, delete only these copies.
- Measure the time of the import of one week before and after the change.

**Out of scope:** a change of the rows that an import deletes.

## Acceptance criteria

- An import deletes the same copies as before. The tests of `StopRowReplacer` and
  `SourceRowRepository` pass with no change of the expected values.
- The time of the delete does not grow with the number of stored copies.
- 100 % line coverage in `src/Import` and `src/Repository`.
```

## Implementation

<!-- Write the design and the steps here when the work starts. -->

## Open points

- [Move the deletes of the stop row replacer to the repositories](stop-row-replacer-deletes.md)
  changes the same method. Do one of the two first.
