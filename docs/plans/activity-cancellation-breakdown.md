# Show where and when the cancellations come

**Issue:** not created. Its row in [Activity page](README.md#activity-page) shows what the work
needs.

See the [rules for plans](README.md#rules).

## Issue text

```markdown
## Background

The prototype page "Kørselsaktivitet" has the panel "Hvor og hvornår kommer aflysningerne?". It
shows the cancellations by the institution, by the direction, by the time of day and by the
notice. The transport office can then find the places and the times where many trips are
cancelled.

This issue needs the late cancellations (#167), because the notice bands use the notice of a
cancellation.

## Tasks

- Add the panel "Hvor og hvornår kommer aflysningerne?" to the activity page:
  - the 10 locations with the most cancelled trips, with the share of all cancelled trips
  - the cancelled trips to the institution and from the institution
  - the cancelled trips by the time of day of the planned pickup
  - the cancelled trips by the notice
- Add a derived table with the cancelled trips for each route, date, location, direction, time
  band and notice band. The import builds it, as `LocationDayTotals`. It has no person ids.
- Show a location only as a group of trips, never with a person id.
- Describe the panel and the table in `docs/architecture.md`.

## Acceptance criteria

- A scenario with cancelled trips at different locations, directions, times and notices gives
  the expected counts.
- The supplier filter applies to the panel.
- The routes of an ignored sub-agreement are not counted.
- A location with fewer cancelled trips than the minimum is not shown.
```

## Implementation

- Copy `LocationDayTotalsBuilder` and `LocationDayTotalsRepository`. Rebuild the new table in
  `app:driving-data:rebuild-totals`.
- The prototype shows the counts as ranked bars. The application has no ranked-bar component.
  Use tables, or add a component.

## Open points

- How does the application find the direction of a trip? `Placename` is the institution end of
  a trip. A pickup with a location is maybe "from the institution", and a drop-off with a
  location is maybe "to the institution". Is this correct?
- Is a cancelled trip counted at its pickup, at its drop-off, or at both?
- Which time bands and which notice bands must the panel show?
- What is the minimum of cancelled trips for a location, as
  `APP_PUNCTUALITY_LOCATION_MIN_STOPS`, so that a location with few cancellations does not
  identify a citizen?
