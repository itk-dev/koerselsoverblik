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
  - **Parsing per field:** type (`string`, `int`, `float`, `bool`, `date`, `time`, `datetime`),
    date format (Excel serial or a format string), value maps for codes (stop type, stop status),
    the true and false values of a `bool` field, and whether the field is required.
  - **Unknown values are rejected:** a code that is not in a value map, or a `bool` value that is
    in neither list, rejects the row. A new code in the extract then shows in the preview.
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
  - route, date, run (the block number), stop type (pickup/drop-off), stop status
  - passengers after the stop
  - distance and duration to the next stop
  - planned solo
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

## The reference extract

A reference extract from the planning system gives the layout for the `default` mapping. The
extract is not in Git, and the docs contain no values from it. A local script printed only its
structure: the column headers, the cell types, the counts and the number formats.

**Layout:**

- One sheet. Row 1 is a title, row 2 is empty, and row 3 has the column headers.
- One row for each stop. Each trip has a pickup row and a drop-off row.
- The extract holds one service day. The date is in its own column, and the times have no date.
- The columns with names, addresses and places are empty in the reference extract.

**Columns in the `default` mapping:**

| Field | Column | Type in the extract | Note |
| --- | --- | --- | --- |
| row id | `RouteStopID` | text | Unique for each row. |
| `route` | `RouteName` | text | To be confirmed: see "Route column" below. |
| `person_id` | `PersonID` | text | [ADR 004](adr/004-personal-data-in-driving-data.md) |
| `service_date` | `Date` | text, `Y-m-d` | The service day of all the times in the row. |
| `run` | `blocknumber` | integer | The route run. A route run is the route, the date and the run. |
| `stop_type` | `StopType` | text code | Two codes: pickup and drop-off. |
| `stop_status` | `Status` | text code | Three codes: completed, cancelled, and no registration (`pending`). |
| `passengers_after` | `passengersaftercancel` | integer | The passengers after the stop, when the cancellations are removed. |
| `next_distance_km` | `ToNextDistance` | decimal | Kilometres. Can be empty. |
| `next_duration_min` | `ToNextDuration` | decimal | Minutes. Can be empty when the distance is not. |
| `planned_time` | `PlannedArrival` | Excel time, `[hh]:mm:ss` | Added to `service_date`. Can be 24:00 or more. |
| `actual_time` | `StatusUpdateTime` | Excel date and time | Empty when the stop has no registration. |
| `planned_solo` | `kørselstype` | text code | Two codes: solo and shared. |

**Why the run comes from `blocknumber`:** the extract also has a column for the start and a column
for the end of a block. In the reference extract, one run has a start but no end. A split on the
end flag joins that run with the next run. The block number gives each run directly.

**Columns that are not imported:**

- Names, addresses, postcodes, towns and places. They are empty in the reference extract.
- `Labels` and `Comment`. They can hold the needs of a citizen or free text, and that can be
  health data ([ADR 004](adr/004-personal-data-in-driving-data.md)).
- The ids of addresses, locations, places, trips and trip requests.
- The other planning columns, for example the requested times, the service times and the paths.

**Columns for milestone 2** (not imported in milestone 1):

- The cancellation columns: the cancellation id, the time of the cancellation, the notice in
  minutes (negative when the cancellation came after the planned time), and whether the notice
  was in time. See Q-M2-03 in the [plan](plan.md#open-questions-for-milestone-2).
- `prisprminut`, the price per minute, for the estimated cost of empty running.
- `passengers`, the passengers after the stop before the cancellations are removed.

## Open questions

The table gives the owner, the date when the answer is needed and the task each question
blocks. The owners are roles. The PM puts names on them. The details are in the list after
the table. The other open questions of milestone 1 are in the
[plan](plan.md#open-questions-affecting-milestone-1).

| Question | Owner | Needed by | Blocks |
| --- | --- | --- | --- |
| Whole days | Product owner (transport office) | Before M1-16 | M1-16 |
| All routes in one extract | Product owner (transport office) | Before M1-16 | M1-16 |
| Maximum range | Developer, with the transport office | Before M1-28 (go-live) | Nothing. The default is 31 days until a test export shows what the planning system can export. |
| Route column | Product owner (transport office) | Before M1-13 | M1-13, M1-14 |
| Supplier mapping | Product owner | Before M1-28 (go-live) | Nothing. Administrators keep the assignments (M1-13) until a source is known. |
| Booked trips | Product owner (transport office) | Before M1-13 | M1-13 |
| Stops with no registration | Product owner (transport office) | Before M1-18 | M1-18, M1-23 |
| Actual time | Product owner | Before M1-15 | Nothing. The mapping uses `StatusUpdateTime` until then. |
| Test data | Product owner | Before M1-15 | M1-15, M1-27 |

- **Whole days:** does each extract hold whole days? Re-upload replaces the date range of the
  upload, so a partial day removes the other rows of that day.
- **All routes in one extract:** does one extract hold all the routes of a day, or only one area
  or contract? The import replaces all stop rows in the date range. If the extracts are per
  area, an upload for one area deletes the other areas. Then the replacement must be limited to
  the routes or the area of the file ([ADR 003](adr/003-driving-data-import-mapping-and-overwrite.md)).
- **Maximum range:** what can the planning system export? Test an export. Then evaluate the
  default of `APP_DRIVING_DATA_MAX_DAYS` (31 days), the maximum number of days in one upload.
- **Route column:** which column identifies a route: the route name or the route id? The route
  id can be an id for one day only. The `default` mapping uses the route name until this is
  confirmed.
- **Supplier mapping:** how do we know which company drives each route? For example, is there a
  list from the transport office, a field in the planning system, or a part of the route name?
  The assignments are admin-maintained until a source is known.
- **Booked trips:** some trips are booked outside the fixed routes, and have a common route name.
  Who drives them? Can more than one supplier drive them? With one route for all of them, they
  get one supplier assignment.
- **Stops with no registration:** how do the figures treat a stop that has no status
  registration from the driver (`pending`)? The proposal: it counts as a trip, because it is not
  cancelled. A run whose last drop-off has no actual time is not counted for punctuality.
- **Actual time:** is `StatusUpdateTime` the actual time of the stop? It is the time of the status
  registration by the driver.
- **Test data:** we need an anonymised sample extract for the fixtures.
