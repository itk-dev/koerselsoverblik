# 004: Personal data in driving data

| Field | Value |
|-------|-------|
| **Created By** | Troels Ugilt Jensen |
| **Date** | 2026-10-10 |
| **Decision Maker** | Not decided |
| **Stakeholders** | ITK Dev developers; the transport office; the data protection adviser |
| **Status** | Draft |

## Context

The goal is to store no personal data about citizens. The driving data extract contains a person
id: an internal id of the planning system that means nothing outside it. The organisation has
access to the planning system, so the id can be looked up there. A hashed person id, or a row key
that can be traced back, is therefore still personal data for us. It is pseudonymised, not
anonymised.

The person id is needed for two things only:

- **The Citizens figure:** the number of distinct people in a period.
- **A fallback row key:** only if the extract has no stable row id column of its own.

Every other figure works without it.

### Drivers

- **Functional:** overwrite on re-import, no duplicates (see [ADR 003](003-driving-data-import-mapping-and-overwrite.md)),
  and a distinct count of citizens over any period.
- **Non-functional:** store as little personal data as possible, and meet GDPR obligations with
  the least ongoing burden.

### Options Considered

1. **Pseudonymised rows**: keep stop rows with an HMAC-hashed person id.
   - Gives exact distinct counts and row-level overwrite.
   - The database holds personal data, so it needs a DPIA, retention rules and access control.
2. **Aggregates only**: store counts per route run and per day, plus a HyperLogLog sketch per day
   for distinct citizens.
   - The sketch gives about 1 % error and holds no identifiers.
   - Overwrite means re-uploading replaces all data for the dates in the file, so duplicates are
     impossible.
   - A file that contains only part of a day would wipe the rest of that day.
3. **Rows for a correction window, then aggregates**: keep pseudonymised rows only for as long as
   corrections can arrive.
   - A purge job then deletes them and keeps the aggregates and sketches.
   - Gives row-level overwrite for recent data, and no identifiers in older data.
4. **Drop the Citizens figure, or show it for single days only**: an exact count per day, stored
   as a number at import. No identifiers are stored.

**Applies to all options:**

- No names, addresses or other personal details are imported.
- Logs and rejection messages contain no field values.
- Uploaded files are deleted after processing.

## Decision

Not decided. These inputs are needed:

- Does the extract have a stable row id column? If yes, the person id is not needed for the row
  key.
- How late can corrections to an extract arrive? This sets the window in option 3.
- Does one file always contain whole days? If not, option 2 would wipe parts of days.
- The data protection adviser's view on storing a pseudonymised person id.

Until it is decided, the architecture assumes option 1, so the work before task M1-10b can
continue. The decision is needed before the extract parser is built.

## Consequences

To be completed when decided.

### Follow-up Actions

- [ ] Get answers to the open inputs above.
- [ ] Get the data protection adviser's view.
- [ ] Decide, and update the data model, the README and the plan.
- [ ] Decide what staff account data is stored (username, display name).
