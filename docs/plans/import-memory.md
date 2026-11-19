# Keep the memory of the import flat

**Issue:** [#100](https://github.com/itk-dev/koerselsoverblik/issues/100)

See the [rules for plans](README.md#rules).

## Implementation

<!-- Write the design and the steps here when the work starts. -->

## Open points

- **The test of the memory.** A generated week takes about 15 s to import. Decide whether the
  test runs in CI, or only with a separate task.
- **The ids of the copies.** `StopRowRangeDeleter` keeps the ids of the copies of the range in
  the memory until it deletes the copies (#126). One int for each row is small. If the
  measurement shows growth, read and delete the ids in groups.
