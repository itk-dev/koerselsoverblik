# Plans

This folder lists the features that the application must get, and holds a plan for each feature.

## Contents

- [Rules](#rules)
- [Add a feature](#add-a-feature)
- [Status values](#status-values)
- [Features](#features)
- [Done](#done)

## Rules

- **The GitHub issue is the task specification** (see [AGENTS.md](../../AGENTS.md)). A plan file
  does not repeat the issue.
- **A plan file holds the text that is not in the issue:**
  - the issue text, until somebody creates the issue
  - the implementation details, the order and the open points
- **The design of merged work** is in the [README](../../README.md),
  [docs/architecture.md](../architecture.md) and the [ADRs](../adr/README.md). The pull request
  that closes a feature deletes its plan file, and moves its row to [Done](#done). Git keeps the
  old plan.
- **Open questions for the transport office** are in
  [Open decisions](../architecture.md#open-decisions). A plan file links to them.
- **The repository is public.** Do not write personal data or values from a real extract.
- Write in ASD-STE100 Simplified Technical English, as all other documentation.

## Add a feature

1. Copy [_template.md](_template.md) to `<SHORT_NAME>.md` in this folder.
2. Write the issue text in the plan file.
3. Add a row to [Features](#features) with the status "Draft".
4. Create the issue on GitHub.
5. Add the issue number to the row and to the plan file. Remove the issue text from the plan
   file.
6. Add the implementation details to the plan file when the work starts.
7. In the pull request that closes the issue, delete the plan file, and move the row to
   [Done](#done).

## Status values

| Status | Meaning |
| --- | --- |
| Draft | The plan file has the issue text. Nobody has created the issue yet |
| Ready | The issue exists, and the work can start |
| Blocked | The work needs a feature or an answer first. The "Needs" column tells which |
| In progress | A branch has work for the issue |

## Features

### Punctuality page

| Feature | Status | Issue | Needs | Plan |
| --- | --- | --- | --- | --- |
| Filter the pages by period, comparison and supplier | Ready | [#82](https://github.com/itk-dev/koerselsoverblik/issues/82) | – | [filters.md](filters.md) |
| Make the late drop-offs clear | Ready | [#86](https://github.com/itk-dev/koerselsoverblik/issues/86) | – | [punctuality-late-drop-offs.md](punctuality-late-drop-offs.md) |
| Show the ten locations with the lowest punctuality | Blocked | [#87](https://github.com/itk-dev/koerselsoverblik/issues/87) | #75 | [punctuality-worst-locations.md](punctuality-worst-locations.md) |
| Calculate the 12 months in one query | Blocked | [#85](https://github.com/itk-dev/koerselsoverblik/issues/85) | #82 | [punctuality-months-query.md](punctuality-months-query.md) |
| Show the driver changes | Blocked | – | Open decision "Driver changes" | – |
| Show the no-shows for each supplier | Blocked | – | Open decision "No-shows" | – |

### Driving data

| Feature | Status | Issue | Needs | Plan |
| --- | --- | --- | --- | --- |
| Read the remaining prototype fields from the extract | Ready | [#75](https://github.com/itk-dev/koerselsoverblik/issues/75) | – | [prototype-fields.md](prototype-fields.md) |
| Show the uploaded dates and clear the driving data of a date | Ready | [#81](https://github.com/itk-dev/koerselsoverblik/issues/81) | – | [clear-dates.md](clear-dates.md) |
| Read a stop row from its stored copy | Ready | [#61](https://github.com/itk-dev/koerselsoverblik/issues/61) | – | [read-stop-row-copy.md](read-stop-row-copy.md) |
| Move the deletes of the stop row replacer to the repositories | Draft | – | – | [stop-row-replacer-deletes.md](stop-row-replacer-deletes.md) |
| Aggregate the daily stats | Blocked | – | Open decisions "Stops with no registration" and "Passengers and distances of a run" | [daily-stats.md](daily-stats.md) |

### Tooling

| Feature | Status | Issue | Needs | Plan |
| --- | --- | --- | --- | --- |
| Give each Infection thread its own test database | In progress | [#83](https://github.com/itk-dev/koerselsoverblik/issues/83) | – | [mutation-test-databases.md](mutation-test-databases.md) |

## Done

| Feature | Issue | Pull request |
| --- | --- | --- |
| Import mapping | – | [#32](https://github.com/itk-dev/koerselsoverblik/pull/32) |
| Fixture extracts and generator | – | [#34](https://github.com/itk-dev/koerselsoverblik/pull/34) |
| Extract parser | [#36](https://github.com/itk-dev/koerselsoverblik/issues/36) | [#37](https://github.com/itk-dev/koerselsoverblik/pull/37) |
| File limits and XXE | [#38](https://github.com/itk-dev/koerselsoverblik/issues/38) | [#39](https://github.com/itk-dev/koerselsoverblik/pull/39) |
| Validate command | [#40](https://github.com/itk-dev/koerselsoverblik/issues/40) | [#41](https://github.com/itk-dev/koerselsoverblik/pull/41) |
| Suppliers, sub-agreements and dated assignments | [#44](https://github.com/itk-dev/koerselsoverblik/issues/44) | [#45](https://github.com/itk-dev/koerselsoverblik/pull/45) |
| All dates and times in one time zone | [#47](https://github.com/itk-dev/koerselsoverblik/issues/47) | [#51](https://github.com/itk-dev/koerselsoverblik/pull/51) |
| One role for the driving data | [#49](https://github.com/itk-dev/koerselsoverblik/issues/49) | [#52](https://github.com/itk-dev/koerselsoverblik/pull/52) |
| Read the planned time from `PlannedDeparture` | [#53](https://github.com/itk-dev/koerselsoverblik/issues/53) | [#54](https://github.com/itk-dev/koerselsoverblik/pull/54) |
| Keep a copy of each imported row | [#55](https://github.com/itk-dev/koerselsoverblik/issues/55) | [#56](https://github.com/itk-dev/koerselsoverblik/pull/56) |
| Replace the stop rows of a date range | [#50](https://github.com/itk-dev/koerselsoverblik/issues/50) | [#62](https://github.com/itk-dev/koerselsoverblik/pull/62) |
| Build the route runs, with the first pickup | [#57](https://github.com/itk-dev/koerselsoverblik/issues/57) | [#72](https://github.com/itk-dev/koerselsoverblik/pull/72) |
| Import command | [#58](https://github.com/itk-dev/koerselsoverblik/issues/58) | [#76](https://github.com/itk-dev/koerselsoverblik/pull/76) |
| Calculate the punctuality figures | [#59](https://github.com/itk-dev/koerselsoverblik/issues/59) | [#77](https://github.com/itk-dev/koerselsoverblik/pull/77) |
| The punctuality page | [#60](https://github.com/itk-dev/koerselsoverblik/issues/60) | [#78](https://github.com/itk-dev/koerselsoverblik/pull/78) |
| Upload driving data with a preview | [#79](https://github.com/itk-dev/koerselsoverblik/issues/79) | [#80](https://github.com/itk-dev/koerselsoverblik/pull/80) |
