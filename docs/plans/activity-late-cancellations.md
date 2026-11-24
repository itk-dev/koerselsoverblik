# Show the late cancellations on the driving activity page

**Issue:** not created. Its row in [Activity page](README.md#activity-page) shows what the work
needs.

See the [rules for plans](README.md#rules).

## Issue text

```markdown
## Background

A cancellation that comes too late costs money: the car is planned, the driver drives, and the
seat cannot go to another citizen. The prototype page "Kørselsaktivitet" splits the cancelled
trips into "Rettidigt aflyst" and "For sent aflyst".

The extract has the notice of a cancellation (`aflysningsvarsel`) and a code that tells if the
notice was in time (`RettidigAflysningsvarsel`). The import keeps them only in the copy of each
row. No figure uses them.

This issue needs the answer to the open decision "Cancellation notice" in
`docs/architecture.md`, and the activity page (#163).

## Tasks

### Import

- Add typed fields to `StopRow`: the notice in minutes, and whether the cancellation was in
  time. Map them in `config/import/driving_data/default.yaml`. Follow the answer of the open
  decision.
- Add two counters to `RouteDayTotals`: the trips that were cancelled in time, and the trips that
  were cancelled too late.
- The application is not live, so a new import of the stored weeks fills the new fields. No
  backfill from the copies is necessary.

### Page

- Add the tile "For sent aflyst": the share of all trips. The note gives the share of the
  cancelled trips.
- Split "Aflyst" into "Rettidigt aflyst" and "For sent aflyst" in the distribution bar and in the
  column chart.
- Add the series "For sent aflyst" to the line chart of the cancellations. Both series are
  shares of all trips.
- Add "For sent aflyst" and "For sent aflyst %" to the table of the suppliers.

### Documentation

- Move the notice and the in-time code from "Columns in the copy only" to the mapping table in
  `docs/architecture.md`.
- Mark the open decision "Cancellation notice" as answered.

## Acceptance criteria

- A scenario with cancellations in time and too late gives the expected counts.
- A cancelled trip with no notice value has a defined rule, and a test for that rule.
- The parts of the distribution bar add up to all trips.
```

## Implementation

- The development dataset already fills `aflysningsvarsel` and `RettidigAflysningsvarsel`
  (`DevDatasetGenerator`). Check that its values agree with the answer of the open decision.
- A scenario row can give kept values (`ExtractRow::$kept`). Make the new fields typed fields in
  the scenarios.
- See [Read a stop row from its stored copy](read-stop-row-copy.md) if a backfill from the copies
  is necessary after go-live.

## Open points

- The open decision "Cancellation notice" in
  [Open decisions](../architecture.md#open-decisions).
- What is the rule for a cancelled trip with no notice value?
