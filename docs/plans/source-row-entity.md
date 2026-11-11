# Keep the copy of a row in its own entity

**Issue:** [#95](https://github.com/itk-dev/koerselsoverblik/issues/95)

See the [rules for plans](README.md#rules).

## Implementation

### Entity `SourceRow`

Add `src/Entity/SourceRow.php`:

- `id`, and `private array $values` with `#[ORM\Column(name: 'source_values', type: Types::JSON)]`.
  `VALUES` is a reserved word in MariaDB, and Doctrine does not quote it. `RejectedStopRow` uses
  `sheet_row_number` for the same reason.
- `@phpstan-import-type SourceValue from SourceCells`. The property type is
  `array<array-key, SourceValue>`.
- `__construct(array $values)`, `getId()` and `getValues()`.
- `repositoryClass: SourceRowRepository::class`.

### `StopRow` and `RejectedStopRow`

- Replace `$sourceValues` with this relation:

  ```php
  #[ORM\OneToOne(cascade: ['persist', 'detach'])]
  #[ORM\JoinColumn(nullable: false)]
  private SourceRow $sourceRow;
  ```

- The constructor sets `$this->sourceRow = new SourceRow($row->sourceValues);`.
- Replace `getSourceValues()` with `getSourceRow(): SourceRow`.
- Do not map an inverse side on `SourceRow`. Doctrine loads an inverse one-to-one side eagerly.
  The owning side gives a lazy object, so Doctrine does not load the copy with the row.
- The foreign key has no `ON DELETE`. The replacer deletes the rows first, and then the copies.
- ORM 3.7 supports the `detach` cascade (`$allCascades` in `ClassMetadata`). Thus
  `StopRowReplacer::write()` needs no change: the detach of a row also detaches its copy.

### Repository `SourceRowRepository`

Add `src/Repository/SourceRowRepository.php` with `deleteOrphans(): int`. It is a DQL delete of
each `SourceRow` with no `StopRow` and no `RejectedStopRow`:

```text
DELETE FROM SourceRow s
WHERE NOT EXISTS (SELECT r.id FROM StopRow r WHERE IDENTITY(r.sourceRow) = s.id)
  AND NOT EXISTS (SELECT j.id FROM RejectedStopRow j WHERE IDENTITY(j.sourceRow) = s.id)
```

The unique indexes on `stop_row.source_row_id` and `rejected_stop_row.source_row_id` serve the
two subqueries.

### `StopRowReplacer`

- Inject `SourceRowRepository`.
- In `deleteRange()`, call `deleteOrphans()` after the deletes of the rejected rows and the stop
  rows. Update the docblock.

### Migration

- Run `task console -- doctrine:migrations:diff`. The diff creates `source_row`, adds
  `source_row_id` with a unique index and a foreign key to the two tables, and drops the two
  `source_values` columns.
- The migration does not copy the stored JSON, because the project is not live. A developer
  database with driving data cannot get the non-null foreign key. Thus delete the driving data
  before `task db:migrate`, or create the database again with `task setup`. Write this step in
  the pull request.

### Tests

Write each test first.

- `tests/Unit/Entity/SourceRowTest.php`: the constructor keeps the values.
- `tests/Unit/Entity/StopRowTest.php` and `RejectedStopRowTest.php`: the row makes a `SourceRow`
  with the values of the DTO.
- `tests/Integration/Repository/SourceRowRepositoryTest.php`: `deleteOrphans()` deletes a copy
  with no row, and keeps the copies of a stop row and of a rejected row. It gives the count.
- `tests/Integration/Import/StopRowReplacerTest.php`:
  - Change `getSourceValues()` to `getSourceRow()->getValues()`.
  - After a second import of the same range, the count of `SourceRow` is the count of the stop
    rows plus the rejected rows.
  - A loaded stop row has an uninitialized copy:
    `$entityManager->getUnitOfWork()->isUninitializedObject($row->getSourceRow())`.
  - An import with a row outside the range stores no copy for that row.
- The other tests that construct `StopRow` (`RouteBuilderTest`, `PunctualityCalculatorTest`,
  `PunctualityPageBuilderTest` and `DrivingDataImporterTest`) need no change, because the
  constructor signature stays the same.

### Documentation

- `docs/architecture.md`: add `SOURCE_ROW` to the entity diagram, with the two relations. Replace
  `json source_values` in `STOP_ROW` and `REJECTED_STOP_ROW` with `int source_row_id FK`. Add
  `SourceRow` to the entity table. In "Keep a copy of each imported row", tell that the copy is a
  `SourceRow`, and that the replacer deletes the copies with no row.
- `docs/adr/006-keep-a-copy-of-each-imported-row.md`: the status is "Proposed", so edit it in
  place. The copy is in its own table, and Doctrine does not load it with the row.
- `CHANGELOG.md`: add an entry under "Unreleased".

### Order

This feature changes the schema. Merge it before #97,
[Merge the migrations into one before the first release](merge-migrations.md).

## Open points

- `deleteOrphans()` reads all copies at each import, about 1.5 million rows after one year. If
  the import becomes slow, select the ids of the copies of the range before the deletes, and
  delete only these ids.
- [Move the deletes of the stop row replacer to the repositories](stop-row-replacer-deletes.md)
  is a draft. If this feature merges first, add `SourceRowRepository::deleteOrphans()` to the
  issue text of that plan.
- #61 reads a stop row from its copy. After this feature, it uses `getSourceRow()->getValues()`.
