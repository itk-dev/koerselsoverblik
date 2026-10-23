# 003: Driving data import with mappings and replacement of whole dates

| Field | Value |
| --- | --- |
| **Created By** | Troels Ugilt Jensen |
| **Date** | 2026-10-10 |
| **Decision Maker** | Troels Ugilt Jensen |
| **Stakeholders** | ITK Dev developers; the transport office (users who upload) |
| **Status** | Draft |

## Context

Driving data arrives as Excel extracts from the planning system. Extracts are uploaded more than
once: corrected files, overlapping date ranges, and re-exports. A corrected re-export can also
remove rows, for example a trip that was registered by mistake. A source with a different layout
may appear later.

### Drivers

- **Functional:**
  - Re-uploading must overwrite earlier data.
  - A row that is not in a corrected re-export must not count any more.
  - The same data must never be counted twice. Duplicates would make every figure wrong.
  - It must be possible to remove the data of a wrong upload.
  - It must be possible to support another source or a changed layout.
- **Non-functional:** imports are traceable (who, when, what changed), failed imports leave no
  partial data, and the calculations are reproducible from stored data.

### Options Considered

1. **A mapping file per source, upsert on a row key**: the column layout lives in YAML, and a
   unique index on `(mapping_name, row_key)` makes duplicates impossible. But an upsert never
   deletes. A row that is not in a corrected re-export stays and keeps counting. It also needs a
   stable row key in the extract.
2. **A mapping file per source, replace whole dates**: the import deletes all stored rows for each
   date in the file, then inserts the rows of the file. Rows that are not in the new file are
   removed. A file that holds only part of a day removes the other rows of that day.
3. **A mapping file per source, replace the dates per route**: as option 2, but only for the
   routes in the file. A partial day keeps the other routes. But a route that is removed from a
   re-export keeps its old rows.
4. **Column names in code**: every new source needs a code change.
5. **Append-only import with de-duplication at query time**: keeps history, but every query must
   de-duplicate, which is easy to get wrong in aggregate SQL.

## Decision

Option 2:

- **Mapping files:**
  - Each source has a mapping in `config/import/driving_data/<MAPPING_NAME>.yaml`: the sheet and
    header row, an optional row key, and each field's source column, type, date format, value
    maps and whether it is required.
  - Mappings are validated when the container is built.
  - `app:driving-data:mapping:validate` checks a sample file.
- **Replace whole dates:**
  - The import dates are the dates that have at least one valid row in the file.
  - For each import date, the import deletes all `StopRow` rows, from all mappings, and then
    inserts the rows of the file. Two sources can then never count the same date twice.
  - The result page and the `ImportBatch` show the replaced dates and the numbers of deleted,
    inserted and rejected rows.
- **Row key:** if the mapping has a row key, a key that occurs more than once in one file is
  rejected for each occurrence after the first. The unique index on `(mapping_name, row_key)`
  makes this sure at database level.
- **No rollback:** an `ImportBatch` cannot be undone. To correct a wrong upload, upload the
  correct file for the same dates, or clear the driving data for the dates (see below).
- **Clear driving data:** users with `ROLE_CLEAR_DRIVING_DATA` can delete the driving data for a
  date range: `StopRow`, `RouteRun` and `DailyStats`. Manually entered daily counts do not
  change. A console command does the same.
- **Derived data:** `RouteRun` and `DailyStats` are rebuilt for the import dates, or deleted for
  the cleared dates.
- **Transactions:** each import, and each clear, runs in one transaction.

## Consequences

### Positive

- A corrected re-export gives exactly the rows of the new file for its dates. No old rows stay.
- Duplicates are impossible: each date holds the rows of one upload only.
- The import does not depend on a stable row key in the extract.
- A wrong upload can be removed in the user interface without help from a developer.
- A new source or layout change needs only a new mapping file.
- Derived tables can be rebuilt at any time, so a calculation fix needs no re-upload.

### Negative / Trade-offs

- A file that holds only part of a day removes the other rows of that day. Users must upload
  whole days. The result page shows the replaced dates, so a wrong upload is easy to see.
- There is no history of earlier uploads for a date. The `ImportBatch` keeps who uploaded what and
  when, but not the old rows.
- Clearing data cannot be undone. The page shows the number of rows to delete and asks for
  confirmation.
- Storing stop rows keeps more data than storing only aggregates.

### Follow-up Actions

- [ ] Confirm with the transport office that each extract holds whole days.
- [ ] Mapping format and loader (task M1-14).
- [ ] Parser, replacement and rebuild (tasks M1-15 to M1-18).
- [ ] Clear driving data for a date range (task M1-20).
