# 004: Personal data in driving data

| Field | Value |
| --- | --- |
| **Created By** | Troels Ugilt Jensen |
| **Date** | 2026-10-10 |
| **Decision Maker** | The product owner in the transport office, with the data protection adviser |
| **Decide By** | Before task M1-15 (the extract parser) starts |
| **Stakeholders** | ITK Dev developers; the transport office; the data protection adviser |
| **Status** | Draft |

## Context

The goal is to store no personal data about citizens. The driving data extract contains a person
id: an internal id of the planning system that means nothing outside it. The organisation has
access to the planning system, so the id can be looked up there. A hashed person id is therefore
still personal data for us. It is pseudonymised, not anonymised.

The person id is needed for one thing only: **the Citizens figure**, the number of distinct people
in a period. Every other figure works without it. The import does not need it, because a
re-upload replaces whole dates ([ADR 003](003-driving-data-import-mapping-and-overwrite.md)).

### Drivers

- **Functional:** a distinct count of citizens over any period. Overwrite and no duplicates are
  given by [ADR 003](003-driving-data-import-mapping-and-overwrite.md) for every option.
- **Non-functional:** store as little personal data as possible, and meet GDPR obligations with
  the least ongoing burden.

### Options Considered

1. **Pseudonymised rows**: keep stop rows with an HMAC-hashed person id.
   - Gives exact distinct counts. The derived data can be rebuilt from the rows.
   - The database holds personal data, so it needs a DPIA, retention rules and access control.
2. **Aggregates only**: store counts per route run and per day, plus a HyperLogLog sketch per day
   for distinct citizens.
   - The sketch gives about 1 % error and holds no identifiers.
   - A calculation fix needs a re-upload of the affected dates.
3. **Rows for a correction window, then aggregates**: keep pseudonymised rows only for as long as
   corrections can arrive.
   - A purge job then deletes them and keeps the aggregates and sketches.
   - Recent data can be rebuilt, and older data holds no identifiers.
4. **Drop the Citizens figure, or show it for single days only**: an exact count per day, stored
   as a number at import. No identifiers are stored.

**Applies to all options:**

- No names, addresses or other personal details are imported.
- Logs and rejection messages contain no field values.
- Uploaded files are deleted after processing.

## Decision

Not decided. These inputs are needed:

- How late can corrections to an extract arrive? This sets the window in option 3.
- Is an approximate Citizens figure (about 1 % error) acceptable? Options 2 and 3 depend on it.
- The data protection adviser's view on storing a pseudonymised person id.

The product owner in the transport office decides, with the data protection adviser. The
developers prepare the inputs and a recommendation.

Until it is decided, the architecture assumes option 1, so the work before task M1-15 can
continue. The decision is needed before task M1-15 starts, because the parser decides what is
read from the person id. Tasks M1-16 to M1-19 also depend on it.

## Consequences

To be completed when decided.

### Follow-up Actions

- [ ] Get answers to the open inputs above.
- [ ] Get the data protection adviser's view.
- [ ] Decide, and update the data model, the README and the plan.
- [ ] Decide what staff account data is stored (username, display name).
