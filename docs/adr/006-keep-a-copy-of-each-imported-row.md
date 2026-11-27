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
- **Non-functional:** store no personal data about a citizen other than the person id
  ([ADR 004](004-personal-data-in-driving-data.md)). Store no personal data about a driver other
  than the driver id. A new column with personal data is not stored by mistake.

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
  extract is not kept until a person adds it after a check for personal data. The mapping names
  these columns in `exclude_columns`, so that the decision is visible. The validation stops a
  column that is both kept and excluded. No view of the prototype needs them: the locations are
  the institutions in `Placename`, not the address of the citizen.
- **The person id:** the copy keeps `PersonID`, because a new read needs the required field
  `person_id`. The stop row already stores it ([ADR 004](004-personal-data-in-driving-data.md)),
  so the copy adds no new personal data.
- **The driver id:** the copy keeps `chauffør`, the id of the driver of the route in the planning
  system (#177). The driver changes of a route need it. It identifies a person, so it is personal
  data about the driver. The extract has no name or other data about the driver. The application
  never shows the driver id, or writes it to a log or to the output of a command. The mapping
  names it in `hidden_columns`, so the detail pages of a row show `(skjult)` in place of the
  value, as for the person id.
- **The vehicle and the operator:** the copy keeps the vehicle id (`vogn`), the vehicle type
  (`vogntype`), the driver type (`chaufførtype`) and the operator id (`operatør`) (#181). They
  are not personal data for the application, so they are not hidden. The vehicle id is an id of
  the planning system, not the registration number. The operator is the supplier, which the
  pages already show by name. A supplier with one driver is a person, so a vehicle or an operator
  can point to that driver. The pages show these columns only as counts for each supplier, never
  with a driver id or a person id.
- **The ids of the planning system** (trips, addresses, locations, places and cancellations) are
  kept. They mean nothing outside the planning system. The mapping keeps only the columns of the
  current report. A column that the report does not export is not in the mapping (#177).
- **Rejected rows** also get a copy, except a row with a date outside the range of the upload.
  The upload for that date imports it.
- **Optional fields:** a value that the parser cannot read gives an empty value and a warning, not
  a rejected row. The copy keeps the value. A new code in an optional field then does not reject
  the row.
- **Storage:** the copy is a `SourceRow` in its own table, with a one-to-one relation from the
  stop row or the rejected row. Doctrine does not read the copy when it reads a row, so a page
  that reads the rows, for example the driving data view (#96), does not read the JSON.

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

- [x] Store the copies with the stop rows and the rejected rows (task M1-16).
- [x] Add a command that reads the stored copies again through a changed mapping (#61). A copy
  leaves out the empty cells, so it has no header. A field column that is in no copy of the range
  stops the command for a required field, and gives no value for an optional field.
- [ ] Include the copies in the retention period of the stop rows.
