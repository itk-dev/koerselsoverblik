# Plan: data import

This is the plan for the import of driving data in milestone 1. It is step 7 of the
[plan](plan.md). The calculation rules and the master data (suppliers and routes) stay in the
plan. The tasks are M1-14 to M1-20 in [tasks](tasks.md).

## Upload flow

Administration → Upload driving data, `ROLE_UPLOAD_DRIVING_DATA`:

- The upload form has the file, and the first and the last date of the export.
- The range can be at most `APP_DRIVING_DATA_MAX_DAYS` days (default 31).
- **Preview:** the page shows the range, the rows to delete, insert and reject, and the dates
  that become empty. No data changes. The file is kept in `var/`, and its token in the session.
- **Confirm:** the import runs, and creates an `ImportBatch`: file name, uploaded by, the
  replaced date range, row counts (deleted/inserted/rejected), errors. **Cancel** deletes the
  file.
- The file is processed **synchronously** in the request, and the result page is shown.
  - Move to async processing only if file sizes require it (see
    [later milestones](plan.md#later-milestones-outline)).
- CLI: `app:driving-data:import <PATH_TO_FILE> <FROM_DATE> <TO_DATE> [--mapping=<MAPPING_NAME>]`.
  It shows the preview, and asks for confirmation unless `--no-interaction` is given.
- The uploaded file is deleted after the import or the cancel. A file that is not confirmed is
  deleted at the next upload after one hour.
- **Uploaded dates:** the upload page lists the dates with driving data, one month at a time.
  Each date shows the stop rows, the route runs and the last upload.

## Import mapping

- How a source's columns map to the application's fields is described in a mapping file,
  `config/import/driving_data/<MAPPING_NAME>.yaml`, not in code. This lets us support another source,
  or a changed extract, by adding or editing a mapping.
- `APP_DRIVING_DATA_MAPPING` selects the mapping used for uploads. The default is `default`.
- **A mapping defines:**
  - **Location:** the sheet and the header row.
  - **Row id (optional):** one or more columns. A row id that occurs more than once in one file is
    rejected after the first occurrence.
  - **Field mapping:** a column for each application field.
  - **Parsing per field:** type (`string`, `int`, `float`, `bool`, `datetime`), date format (Excel
    serial or a format string), value maps for codes (stop type, stop status, yes/no), and whether
    the field is required.
- **Implementation:**
  - `MappingLoader` reads the YAML and validates it with the Symfony Config component (`TreeBuilder`).
  - The app refuses to start if a mapping is invalid.
  - `StopRowParser` reads the workbook only through a `Mapping` value object. It never refers to column names directly.
- `app:driving-data:mapping:validate <MAPPING_NAME> <PATH_TO_FILE>` checks a sample file against a
  mapping. It reports missing columns and unmapped values, and previews the first parsed rows.
- **Tests:**
  - unit tests for the loader and validation, and for each field type and value map
  - parser tests run against a test mapping in `tests/fixtures/mappings/`

## Storage and replacement

See [ADR 003](adr/003-driving-data-import-mapping-and-overwrite.md).

- `StopRow` holds one row per stop from the extract.
- **Replace the date range:** `StopRowReplacer` deletes all `StopRow` rows in the date range that
  the user entered, from all mappings, and then inserts the rows of the file.
  - Rows that are not in a corrected file are removed.
  - A date in the range with no rows in the file becomes empty.
  - A row with a date outside the range is rejected.
  - Two uploads can never count the same date twice.
  - A file that holds only part of a day removes the other rows of that day. The preview lists
    the dates that become empty.
- If the mapping has a row id, a unique index on `(mapping_name, row_key)` stops duplicate rows in one file.
- **No rollback:** an `ImportBatch` cannot be undone. Upload the correct file, or clear the dates.
- Only the fields the calculations need are kept:
  - route, date, stop type (pickup/drop-off), stop status, end-of-route flag
  - passengers after the stop
  - distance and duration to the next stop
  - cancellation fields, planned solo
  - planned time, actual time
- The person id is stored as it is in the extract, with no HMAC
  ([ADR 004](adr/004-personal-data-in-driving-data.md), proposed). It is never shown.

## Derived data

- After an import, `RouteRun` and `DailyStats` are rebuilt for the **date range**:
  - **`RouteRun`** (one per route run): last drop-off planned and actual, delay, peak passengers, cancelled.
  - **`DailyStats`** (date × supplier): trips, completed, cancelled, route runs, last drop-offs, on
    time, solo runs, driven km, empty km, driven minutes, empty minutes.

## Clear driving data

`ROLE_CLEAR_DRIVING_DATA`:

- Each date in the list of uploaded dates has a **Clear** button. The user confirms before the
  data is deleted. There is no separate page.
- `DrivingDataClearer` deletes `StopRow`, `RouteRun` and `DailyStats` for a date range in one
  transaction. The upload page clears one date. `DailyCount` does not change.
- The action is logged: who, when, the range and the deleted counts. No field values.
- CLI: `app:driving-data:clear <FROM_DATE> <TO_DATE>`, with the same service.

## Open questions

The table gives the owner, the date when the answer is needed and the task each question
blocks. The owners are roles. The PM puts names on them. The details are in the list after
the table. The other open questions of milestone 1 are in the
[plan](plan.md#open-questions-affecting-milestone-1).

| Question | Owner | Needed by | Blocks |
| --- | --- | --- | --- |
| Whole days | Product owner (transport office) | Before M1-16 | M1-16 |
| Maximum range | Developer, with the transport office | Before M1-28 (go-live) | Nothing. The default is 31 days until a test export shows what the planning system can export. |
| Row id | Product owner, with a developer | Before M1-14 | M1-14 |
| Actual time | Product owner | Before M1-14 | M1-14 |
| Supplier mapping | Product owner | Before M1-28 (go-live) | Nothing. Administrators keep the assignments (M1-13) until a source is known. |
| Test data | Product owner | Before M1-15 | M1-15, M1-27 |

- **Whole days:** does each extract hold whole days? Re-upload replaces the date range of the
  upload, so a partial day removes the other rows of that day.
- **Maximum range:** what can the planning system export? Test an export. Then evaluate the
  default of `APP_DRIVING_DATA_MAX_DAYS` (31 days), the maximum number of days in one upload.
- **Row id:** is there a stable row id column in the extract? It is optional. If it exists, the
  import uses it to reject duplicate rows in one file.
- **Actual time:** which column holds the actual time? It is set in the mapping until this is confirmed.
- **Supplier mapping:** where does route → supplier come from? It is admin-maintained until a source is known.
- **Test data:** we need an anonymised sample extract for the fixtures.
