# 003: Driving data import with mappings and overwrite by row key

| Field | Value |
|-------|-------|
| **Created By** | Troels Ugilt Jensen |
| **Date** | 2026-10-10 |
| **Decision Maker** | Troels Ugilt Jensen |
| **Stakeholders** | ITK Dev developers; the transport office (editors) |
| **Status** | Draft |

## Context

Driving data arrives as Excel extracts from the planning system. Extracts are uploaded more than
once: corrected files, overlapping date ranges, and re-exports. A source with a different layout
may appear later.

### Drivers

- **Functional:**
  - Re-uploading must overwrite earlier data.
  - The same data must never be counted twice. Duplicates would make every figure wrong.
  - It must be possible to support another source or a changed layout.
- **Non-functional:** imports are traceable (who, when, what changed), failed imports leave no
  partial data, and the calculations are reproducible from stored data.

### Options Considered

1. **A mapping file per source, upsert on a row key, rebuild derived data for affected dates**:
   the column layout lives in YAML, and a unique index on `(mapping_name, row_key)` makes
   duplicates impossible.
2. **Column names in code, replace whole dates on upload**: simpler, but every new source needs a
   code change, and a partial file would wipe the rest of a day.
3. **Append-only import with de-duplication at query time**: keeps history, but every query must
   de-duplicate, which is easy to get wrong in aggregate SQL.

## Decision

Option 1:

- **Mapping files:**
  - Each source has a mapping in `config/import/driving_data/<MAPPING_NAME>.yaml`: the sheet and
    header row, the row key columns, and each field's source column, type, date format, value
    maps and whether it is required.
  - Mappings are validated when the container is built.
  - `app:driving-data:mapping:validate` checks a sample file.
- **Upsert:** stop rows are upserted on `(mapping_name, row_key)`, a unique index.
- **Derived data:** `RouteRun` and `DailyStats` are rebuilt for the dates an import touched.
- **Transactions:** each import runs in one transaction.

## Consequences

### Positive

- Duplicates are prevented by the database, not only by code.
- A new source or layout change needs only a new mapping file.
- Derived tables can be rebuilt at any time, so a calculation fix needs no re-upload.

### Negative / Trade-offs

- It depends on the extract having a stable row key. If no single column is stable, the mapping
  needs a composite key, which could include the person id (see [ADR 004](004-personal-data-in-driving-data.md)).
- Storing stop rows keeps more data than storing only aggregates.
- Two sources describing the same trips would not be de-duplicated against each other, because
  their keys differ. Only one mapping should be active for a given source system.

### Follow-up Actions

- [ ] Confirm which column(s) form a stable row key.
- [ ] Mapping format and loader (task M1-10a).
- [ ] Parser, upsert and rebuild (tasks M1-10b to M1-13).
