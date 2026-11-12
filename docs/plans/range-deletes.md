# Delete the rows of a range in the repositories, and only their copies

**Issue:** [#126](https://github.com/itk-dev/koerselsoverblik/issues/126)

See the [rules for plans](README.md#rules).

## Implementation

### Repositories

- **`src/Repository/RejectedStopRowRepository.php`** (new, `ServiceEntityRepository`). Add
  `repositoryClass` to `#[ORM\Entity]` in `src/Entity/RejectedStopRow.php`. This is not a change
  of the schema.
  - `private function inRange(QueryBuilder $builder, string $alias, ImportRangeDto $range)`: the
    condition of `StopRowReplacer::deleteRange()`, with the subquery on `ImportBatch`. Set
    `first` and `last` with `Types::DATE_IMMUTABLE`, as now.
  - `sourceRowIdsInRange(ImportRangeDto): list<int>`: `SELECT IDENTITY(rejected.sourceRow)` with
    `getSingleColumnResult()`, cast to `int`.
  - `deleteInRange(ImportRangeDto): void`: a query builder `delete()` with the same condition.
- **`StopRowRepository`**: add `sourceRowIdsInRange()` and `deleteInRange(): int`. Follow
  `RouteRunRepository::deleteInRange()` and `StopRowRepository::countInRange()`.
- **`SourceRowRepository`**: replace `deleteOrphans()` with `deleteByIds(list<int> $ids): int`.
  It runs `DELETE ... WHERE source.id IN (:ids)` for each `array_chunk($ids, 1000)`, with
  `ArrayParameterType::INTEGER`. An empty list runs no query.

### Deleter

- **`src/Import/StopRowRangeDeleter.php`** (`final readonly`) gets the three repositories.
  `deleteRange(ImportRangeDto $range): int` keeps the order and the docblock of the current
  `deleteRange()`. It reads the ids of the copies before the deletes, because a deleted row no
  longer points to its copy.
- **`StopRowReplacer`**: replace the private `deleteRange()` and the `SourceRowRepository`
  argument with the deleter. Remove the `Types` import if no other code uses it.

### Tests

Write each test first.

- `tests/Integration/Repository/RejectedStopRowRepositoryTest.php`: the ids and the delete, with
  a dated row inside and outside the range, and a row with no date of a smaller and of a larger
  batch. Use the entity setup of `SourceRowRepositoryTest`.
- `tests/Integration/Repository/StopRowRepositoryTest.php` (new file): the ids and the delete
  inside and outside the range, and the edge dates.
- `SourceRowRepositoryTest`: replace the two `deleteOrphans()` tests with `deleteByIds()` tests:
  only the given ids, an empty list, and more than 1000 ids.
- `tests/Integration/Import/StopRowRangeDeleterTest.php`: the copies of other dates stay, and the
  copies of the range go.
- `tests/Integration/Import/StopRowReplacerTest.php`: no change of the expected values.

### Documentation

- `docs/architecture.md`: the text about the copies names
  `SourceRowRepository::deleteOrphans()`. Replace it with the deleter and `deleteByIds()`.
- `CHANGELOG.md`: an entry under "Unreleased".

## Open points

- The ids of a week stay in the memory during the import. One int for each row is small, but
  [#100](https://github.com/itk-dev/koerselsoverblik/issues/100) measures the memory. If it
  shows growth, read and delete the ids in groups.
