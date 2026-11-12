# Plans

This folder lists the features that the application must get, and holds a plan file for each
feature that has an issue or an issue text.

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
- **A blocked feature can have no plan file**, if nobody can write the issue text before the
  answer. Its row in [Features](#features) has no plan.
- **The status and the needs are in this file only.** A plan file links to its issue, but does
  not show the status.
- **The design of merged work** is in the [README](../../README.md),
  [docs/architecture.md](../architecture.md) and the [ADRs](../adr/README.md). The pull request
  that closes a feature deletes its plan file, and moves its row to [Done](#done). Git keeps the
  old plan.
- **Open questions for the transport office** are in
  [Open decisions](../architecture.md#open-decisions). A plan file links to them.
- **The repository is public.** Do not write personal data, cell values or numbers from the real
  driving data, for example counts, shares, sizes or punctuality. Write `XX` in place of such a
  number. The same rule applies to the GitHub issues and the pull requests. The limits in `.env`
  and public facts, for example the school holidays, are not driving data.
- Write in ASD-STE100 Simplified Technical English, as all other documentation.

## Add a feature

1. Copy [_template.md](_template.md) to `<SHORT_NAME>.md` in this folder.
2. Write the issue text in the plan file.
3. Add a row to [Features](#features) with the status "Draft".
4. Create the issue on GitHub.
5. Add the issue number to the row and to the plan file. Remove the issue text from the plan
   file.
6. Set the status to "Ready", or to "Blocked" if the work needs a feature or an answer first.
7. Set the status to "In progress" when a branch has work for the issue. Add the implementation
   details to the plan file.
8. In the pull request that closes the issue, delete the plan file, and move the row to
   [Done](#done).

## Status values

| Status | Meaning |
| --- | --- |
| Draft | The plan file has the issue text. Nobody has created the issue yet |
| Ready | The issue exists, and the work can start |
| Blocked | The work needs a feature or an answer first. The "Needs" column tells which. The issue can be missing, if the issue text needs the answer first |
| In progress | A branch has work for the issue |

## Features

### Overview

| Feature | Status | Issue | Needs | Plan |
| --- | --- | --- | --- | --- |
| Show the trips and the citizens on the overview | In progress | [#106](https://github.com/itk-dev/koerselsoverblik/issues/106) | – | – |
| Show the punctuality on the overview | In progress | [#109](https://github.com/itk-dev/koerselsoverblik/issues/109) | – | – |
| Show the real solo runs on the overview | In progress | [#110](https://github.com/itk-dev/koerselsoverblik/issues/110) | – | – |
| Show the suppliers side by side on the overview | In progress | [#113](https://github.com/itk-dev/koerselsoverblik/issues/113) | – | – |
| Show the conclusion on the overview | Blocked | [#122](https://github.com/itk-dev/koerselsoverblik/issues/122) | #116, #117 | [overview-conclusion.md](overview-conclusion.md) |

### Punctuality page

| Feature | Status | Issue | Needs | Plan |
| --- | --- | --- | --- | --- |
| Filter the pages by period, comparison and supplier (parent issue) | In progress | [#82](https://github.com/itk-dev/koerselsoverblik/issues/82) | #115, #116, #117, #118, #122 | [filters.md](filters.md) |
| Calculate the selected period, the comparison and the 12 months | In progress | [#115](https://github.com/itk-dev/koerselsoverblik/issues/115) | – | [filters.md](filters.md) |
| Filter the punctuality page and the overview by period and comparison | In progress | [#116](https://github.com/itk-dev/koerselsoverblik/issues/116) | – | [filters.md](filters.md) |
| Filter the punctuality page and the overview by supplier | In progress | [#117](https://github.com/itk-dev/koerselsoverblik/issues/117) | – | [filters.md](filters.md) |
| Remove the planning system from the source texts | In progress | [#118](https://github.com/itk-dev/koerselsoverblik/issues/118) | – | – |
| Make the late drop-offs clear | Ready | [#86](https://github.com/itk-dev/koerselsoverblik/issues/86) | – | [punctuality-late-drop-offs.md](punctuality-late-drop-offs.md) |
| Show the ten locations with the lowest punctuality | Blocked | [#87](https://github.com/itk-dev/koerselsoverblik/issues/87) | #75, #117 | [punctuality-worst-locations.md](punctuality-worst-locations.md) |
| Calculate the 12 months in one query | Blocked | [#85](https://github.com/itk-dev/koerselsoverblik/issues/85) | #115 | [punctuality-months-query.md](punctuality-months-query.md) |
| Rank the worst routes by the number of late runs | Ready | [#103](https://github.com/itk-dev/koerselsoverblik/issues/103) | – | – |
| Compare the delays of the months as shares | Ready | [#104](https://github.com/itk-dev/koerselsoverblik/issues/104) | – | – |
| Fix the month chooser, the chart labels and the number format | Blocked | [#105](https://github.com/itk-dev/koerselsoverblik/issues/105) | #116 | [punctuality-layout.md](punctuality-layout.md) |
| Show the ignored sub-agreements on the punctuality page | Ready | [#99](https://github.com/itk-dev/koerselsoverblik/issues/99) | – | [ignored-sub-agreements-note.md](ignored-sub-agreements-note.md) |
| Show the driver changes | Blocked | – | Open decision "Driver changes" | – |
| Show the no-shows for each supplier | Blocked | – | Open decision "No-shows" | – |

### Driving data

| Feature | Status | Issue | Needs | Plan |
| --- | --- | --- | --- | --- |
| Read the remaining prototype fields from the extract | Ready | [#75](https://github.com/itk-dev/koerselsoverblik/issues/75) | – | [prototype-fields.md](prototype-fields.md) |
| Show the uploaded dates and clear the driving data of a date | Ready | [#81](https://github.com/itk-dev/koerselsoverblik/issues/81) | – | [clear-dates.md](clear-dates.md) |
| Show the imported stop rows and rejected rows of a date range | Ready | [#96](https://github.com/itk-dev/koerselsoverblik/issues/96) | – | [driving-data-view.md](driving-data-view.md) |
| Read a stop row from its stored copy | Ready | [#61](https://github.com/itk-dev/koerselsoverblik/issues/61) | – | [read-stop-row-copy.md](read-stop-row-copy.md) |
| Keep the memory of the import flat | Ready | [#100](https://github.com/itk-dev/koerselsoverblik/issues/100) | – | [import-memory.md](import-memory.md) |
| Serialise the changes of the driving data | Draft | – | – | [driving-data-lock.md](driving-data-lock.md) |
| Aggregate the daily stats | Blocked | – | Open decisions "Stops with no registration" and "Passengers and distances of a run" | [daily-stats.md](daily-stats.md) |

### Release

| Feature | Status | Issue | Needs | Plan |
| --- | --- | --- | --- | --- |
| Merge the migrations into one before the first release | Blocked | [#97](https://github.com/itk-dev/koerselsoverblik/issues/97) | All other schema changes before the first release | [merge-migrations.md](merge-migrations.md) |

## Done

The table lists each merged pull request that closes an issue. It does not list the chores and
the documentation changes with no issue. [CHANGELOG.md](../../CHANGELOG.md) has all merged work.

| Feature | Issue | Pull request |
| --- | --- | --- |
| Project skeleton | [#5](https://github.com/itk-dev/koerselsoverblik/issues/5) | [#6](https://github.com/itk-dev/koerselsoverblik/pull/6), [#19](https://github.com/itk-dev/koerselsoverblik/pull/19) |
| Test tooling | [#7](https://github.com/itk-dev/koerselsoverblik/issues/7) | [#8](https://github.com/itk-dev/koerselsoverblik/pull/8) |
| Mutation testing | [#9](https://github.com/itk-dev/koerselsoverblik/issues/9) | [#10](https://github.com/itk-dev/koerselsoverblik/pull/10) |
| Diagram check in CI | [#11](https://github.com/itk-dev/koerselsoverblik/issues/11) | [#12](https://github.com/itk-dev/koerselsoverblik/pull/12) |
| Permission roles and deny by default | [#14](https://github.com/itk-dev/koerselsoverblik/issues/14) | [#15](https://github.com/itk-dev/koerselsoverblik/pull/15) |
| OIDC login and mock service | [#21](https://github.com/itk-dev/koerselsoverblik/issues/21) | [#22](https://github.com/itk-dev/koerselsoverblik/pull/22) |
| Base layout, design system and menu | [#24](https://github.com/itk-dev/koerselsoverblik/issues/24) | [#25](https://github.com/itk-dev/koerselsoverblik/pull/25) |
| Upload a date range, and the "Administration" section | [#26](https://github.com/itk-dev/koerselsoverblik/issues/26) | [#27](https://github.com/itk-dev/koerselsoverblik/pull/27) |
| Mermaid diagrams in the architecture | [#18](https://github.com/itk-dev/koerselsoverblik/issues/18) | [#28](https://github.com/itk-dev/koerselsoverblik/pull/28) |
| Remove the plan and the task list from the repository | [#29](https://github.com/itk-dev/koerselsoverblik/issues/29) | [#30](https://github.com/itk-dev/koerselsoverblik/pull/30) |
| Import mapping | [#31](https://github.com/itk-dev/koerselsoverblik/issues/31) | [#32](https://github.com/itk-dev/koerselsoverblik/pull/32) |
| Fixture extracts and generator | [#33](https://github.com/itk-dev/koerselsoverblik/issues/33) | [#34](https://github.com/itk-dev/koerselsoverblik/pull/34) |
| The project name Kørselsoverblik | [#23](https://github.com/itk-dev/koerselsoverblik/issues/23) | [#35](https://github.com/itk-dev/koerselsoverblik/pull/35) |
| Extract parser | [#36](https://github.com/itk-dev/koerselsoverblik/issues/36) | [#37](https://github.com/itk-dev/koerselsoverblik/pull/37) |
| File limits and XXE | [#38](https://github.com/itk-dev/koerselsoverblik/issues/38) | [#39](https://github.com/itk-dev/koerselsoverblik/pull/39) |
| Validate command | [#40](https://github.com/itk-dev/koerselsoverblik/issues/40) | [#41](https://github.com/itk-dev/koerselsoverblik/pull/41) |
| Infection with 4 threads | [#42](https://github.com/itk-dev/koerselsoverblik/issues/42) | [#43](https://github.com/itk-dev/koerselsoverblik/pull/43) |
| Suppliers, sub-agreements and dated assignments | [#44](https://github.com/itk-dev/koerselsoverblik/issues/44) | [#45](https://github.com/itk-dev/koerselsoverblik/pull/45) |
| All dates and times in one time zone | [#47](https://github.com/itk-dev/koerselsoverblik/issues/47) | [#51](https://github.com/itk-dev/koerselsoverblik/pull/51) |
| One role for the driving data | [#49](https://github.com/itk-dev/koerselsoverblik/issues/49) | [#52](https://github.com/itk-dev/koerselsoverblik/pull/52) |
| Read the planned time from `PlannedDeparture` | [#53](https://github.com/itk-dev/koerselsoverblik/issues/53) | [#54](https://github.com/itk-dev/koerselsoverblik/pull/54) |
| Keep a copy of each imported row | [#55](https://github.com/itk-dev/koerselsoverblik/issues/55) | [#56](https://github.com/itk-dev/koerselsoverblik/pull/56) |
| Replace the stop rows of a date range | [#50](https://github.com/itk-dev/koerselsoverblik/issues/50) | [#62](https://github.com/itk-dev/koerselsoverblik/pull/62) |
| OIDC review follow-up | [#63](https://github.com/itk-dev/koerselsoverblik/issues/63) | [#64](https://github.com/itk-dev/koerselsoverblik/pull/64) |
| Strict types | [#46](https://github.com/itk-dev/koerselsoverblik/issues/46) | [#65](https://github.com/itk-dev/koerselsoverblik/pull/65) |
| Build the route runs, with the first pickup | [#57](https://github.com/itk-dev/koerselsoverblik/issues/57) | [#72](https://github.com/itk-dev/koerselsoverblik/pull/72) |
| Import command | [#58](https://github.com/itk-dev/koerselsoverblik/issues/58) | [#76](https://github.com/itk-dev/koerselsoverblik/pull/76) |
| Calculate the punctuality figures | [#59](https://github.com/itk-dev/koerselsoverblik/issues/59) | [#77](https://github.com/itk-dev/koerselsoverblik/pull/77) |
| The punctuality page | [#60](https://github.com/itk-dev/koerselsoverblik/issues/60) | [#78](https://github.com/itk-dev/koerselsoverblik/pull/78) |
| Upload driving data with a preview | [#79](https://github.com/itk-dev/koerselsoverblik/issues/79) | [#80](https://github.com/itk-dev/koerselsoverblik/pull/80) |
| Give each Infection thread its own test database | [#83](https://github.com/itk-dev/koerselsoverblik/issues/83) | [#84](https://github.com/itk-dev/koerselsoverblik/pull/84) |
| Give each sub-agreement one supplier | [#91](https://github.com/itk-dev/koerselsoverblik/issues/91) | [#93](https://github.com/itk-dev/koerselsoverblik/pull/93) |
| Ignore a sub-agreement in the figures | [#90](https://github.com/itk-dev/koerselsoverblik/issues/90) | [#94](https://github.com/itk-dev/koerselsoverblik/pull/94) |
| Make the development dataset realistic | [#101](https://github.com/itk-dev/koerselsoverblik/issues/101) | [#107](https://github.com/itk-dev/koerselsoverblik/pull/107) |
| Keep the copy of a row in its own entity | [#95](https://github.com/itk-dev/koerselsoverblik/issues/95) | [#124](https://github.com/itk-dev/koerselsoverblik/pull/124) |
| Delete the rows of a range in the repositories, and only their copies | [#126](https://github.com/itk-dev/koerselsoverblik/issues/126) | [#127](https://github.com/itk-dev/koerselsoverblik/pull/127) |
