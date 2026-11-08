# 006: Keep a copy of each imported row

| Field | Value |
| --- | --- |
| **Created By** | Troels Ugilt Jensen |
| **Date** | 2026-11-06 |
| **Decision Maker** | The product owner in the transport office, with the data protection adviser |
| **Decide By** | Before task M1-28 (go-live), together with [ADR 004](004-personal-data-in-driving-data.md) |
| **Stakeholders** | ITK Dev developers; the transport office; the data protection adviser |
| **Status** | Proposed |

## Context

An upload deletes the extract after the import. The import keeps only the 12 fields that the
figures of milestone 1 use. The extract has more than 60 columns. Data is then lost for all the
dates that are already uploaded, when:

- a later milestone needs another column, for example the cancellation columns or the price per
  minute;
- a rule changes, as the planned time did (`PlannedArrival` to `PlannedDeparture`);
- a row is rejected because of one field, for example a new status code.

To get the data again, the transport office must export each date again, and somebody must
upload it again.

### Drivers

- **Functional:** all the views of the prototype, and the later milestones, have data from the
  first upload.
- **Non-functional:** store no personal data other than the person id
  ([ADR 004](004-personal-data-in-driving-data.md)). A new column with personal data is not
  stored by mistake.

### Options Considered

1. **Typed fields, and a copy of each row in JSON.** Keep the typed fields for the calculations.
   Keep a copy of each row with the allowed columns in one JSON column. A later task reads the
   copies again through a changed mapping, with no new upload.
2. **A typed field for each column.** Add a field and a database column for each operational
   column now. Most of them have no use yet, and the mapping and the schema become large.
3. **Keep the original files.** Simple, but the files have the columns with personal data, and
   they cannot be read again without these columns.

## Decision

**Proposed:** option 1, a copy of each row in JSON.

- **What the copy holds:** the field columns, the row id columns and the columns in the
  `keep_columns` list of the mapping. The copy keeps the type of each cell, so that the parser can
  read a copy in the same way as a row of the file.
- **What the copy never holds:** the personal data, `PersonName`, `Adresse`, `Post-nr`, `By`,
  `Labels` and `Comment`. A column is kept only when the mapping names it, so a new column in the
  extract is not kept until a person adds it after a check for personal data.
- **The person id:** the copy keeps `PersonID`, because a new read needs the required field
  `person_id`. The stop row already stores it ([ADR 004](004-personal-data-in-driving-data.md)),
  so the copy adds no new personal data.
- **The ids of the planning system** (trips, requests, routes, addresses, locations, places and
  paths) are kept. They mean nothing outside the planning system.
- **Rejected rows** also get a copy, except a row with a date outside the range of the upload.
  The upload for that date imports it.
- **Optional fields:** a value that the parser cannot read gives an empty value and a warning, not
  a rejected row. The copy keeps the value. A new code in an optional field then does not reject
  the row.

## Consequences

### Positive

- A later milestone can use a column from the first upload, with no new export.
- A changed rule can be applied to the stored data, with no new upload.
- A row that is rejected because of a mapping error can be imported when the mapping is fixed.

### Negative

- The database is larger: about 2 KB of JSON for each stop row, roughly 3 GB each year at 4,000
  stops each day.
- The copy has more operational data than the figures need. It is in the same database, with the
  same access control, encryption and retention as the stop rows
  ([ADR 004](004-personal-data-in-driving-data.md)).
- A bad value in an optional field no longer rejects the row. The warning in the preview shows it.

### Follow-up Actions

- [ ] Store the copies with the stop rows and the rejected rows (task M1-16).
- [ ] Add a command that reads the stored copies again through a changed mapping. A copy leaves
  out the empty cells, so it has no header. The command needs its own rule for a column that is
  in no copy: the column can be empty in all the rows, or not in the extract.
- [ ] Include the copies in the retention period of the stop rows.
