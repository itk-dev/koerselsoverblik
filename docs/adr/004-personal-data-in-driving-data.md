# 004: Personal data in driving data

| Field | Value |
| --- | --- |
| **Created By** | Troels Ugilt Jensen |
| **Date** | 2026-10-10 |
| **Decision Maker** | The product owner in the transport office, with the data protection adviser |
| **Decide By** | Before task M1-28 (go-live). Task M1-15 implements the proposed decision |
| **Stakeholders** | ITK Dev developers; the transport office; the data protection adviser |
| **Status** | Proposed |

## Context

The goal is to store as little personal data about citizens as possible. The driving data
extract contains a person id: an internal id of the planning system that means nothing outside
it. The organisation has access to the planning system, so the id can be looked up there. A
hashed person id is therefore still personal data for us. It is pseudonymised, not anonymised.

The person id is needed for one thing only: **the Citizens figure**, the number of distinct people
in a period. Every other figure works without it. The import does not need it, because a
re-upload replaces the date range of the upload
([ADR 003](003-driving-data-import-mapping-and-overwrite.md)).

### Drivers

- **Functional:** a distinct count of citizens over any period. Overwrite and no duplicates are
  given by [ADR 003](003-driving-data-import-mapping-and-overwrite.md) for every option.
- **Non-functional:** store as little personal data as possible, and meet GDPR obligations with
  the least ongoing burden.

### Options Considered

1. **Rows with the person id from the extract**: keep stop rows with the person id as it is in
   the extract.
   - Gives exact distinct counts. The derived data can be rebuilt from the rows.
   - The database holds personal data, so it needs a DPIA, retention rules and access control.
   - No secret to manage.
2. **Pseudonymised rows**: keep stop rows with an HMAC-hashed person id.
   - Gives exact distinct counts. The derived data can be rebuilt from the rows.
   - The hash is still personal data, so it needs the same DPIA, retention rules and access
     control as option 1.
   - The key must be kept apart from the database and must never change. A new key breaks the
     distinct counts across old and new imports.
3. **Aggregates only**: store counts per route run and per day, plus a HyperLogLog sketch per day
   for distinct citizens.
   - The sketch gives about 1 % error and holds no identifiers.
   - A calculation fix needs a re-upload of the affected dates.
4. **Rows for a correction window, then aggregates**: keep rows only for as long as corrections
   can arrive.
   - A purge job then deletes them and keeps the aggregates and sketches.
   - Recent data can be rebuilt, and older data holds no identifiers.
5. **Drop the Citizens figure, or show it for single days only**: an exact count per day, stored
   as a number at import. No identifiers are stored.

**Applies to all options:**

- No names, addresses or other personal details are imported.
- The columns with free text and labels are never imported. They can hold the needs of a
  citizen, and that can be health data.
- The parser reads only the columns in the import mapping. Other columns of the extract are not
  read.
- Logs and rejection messages contain no field values.
- Uploaded files are deleted after processing.

## Decision

**Proposed:** option 1, rows with the person id from the extract.

- The HMAC in option 2 gives little protection. The id means nothing outside the planning
  system, and the organisation can look it up there. The obligations of options 1 and 2 are the
  same.
- The HMAC adds a key that must be kept apart from the database, and that must never change.
- Options 3 to 5 lose exact counts or the rebuild of the derived data.

The product owner in the transport office decides, with the data protection adviser. Task M1-15
implements the proposal. The decision must be accepted before go-live (task M1-28), because real
data is imported only then. If the decision changes, the parser and the data model change.

## Consequences

### Positive

- The Citizens figure is exact for any period.
- `RouteRun` and `DailyStats` can be rebuilt from the stop rows.
- There is no key to manage, to keep apart from the backups, or to rotate.

### Negative

- The database holds personal data. It needs:
  - a DPIA
  - a retention period, and a job that deletes the older stop rows
  - access control: the person id is never shown in the user interface
  - encryption at rest (the `ENCRYPT` option of the `itkdev/mariadb` image) and protected
    backups

### Follow-up Actions

- [ ] Get the data protection adviser's view.
- [ ] Get the decision accepted, or changed, before go-live.
- [ ] Decide the retention period of the stop rows.
- [ ] Decide what staff account data is stored (username, display name).
