# Architecture

This document describes the architecture of Kørselsoverblik for milestone 1:
OIDC login, upload of driving data, manual entry of daily counts, and an overview
with eight key figures. See the [README](../README.md) for what the application does.

The diagrams are Mermaid code blocks in this document. GitHub renders them. Edit a block to change
its diagram.

## Contents

- [Goals and constraints](#goals-and-constraints)
- [System context](#system-context)
- [Containers](#containers)
- [Components](#components)
- [Data model](#data-model)
- [Importing driving data](#importing-driving-data)
- [Calculating the figures](#calculating-the-figures)
- [Security](#security)
- [User interface](#user-interface)
- [Testing](#testing)
- [Deployment](#deployment)
- [Open decisions](#open-decisions)
- [Decision records](#decision-records)

## Goals and constraints

- **Correct figures first.** Every figure is calculated the same way for any period, from summed
  counts. A wrong number is the worst possible bug, so calculation code has the strictest tests.
- **No duplicates.** Re-importing data replaces all stored data in the date range of the upload.
  The same data must never be counted twice, and rows that are not in a corrected file must not
  stay.
- **Configurable column layout.** A mapping file gives the column names, the code values and the
  formats of an extract, so a change of these needs no code change. A new field needs code (see
  [Import mappings](#import-mappings)).
- **Minimal personal data.** No names, addresses, free text or driver data are imported. The
  person id is kept only to count citizens. The decision is proposed, not accepted (see
  [open decisions](#open-decisions)).
- **Simple to run.** One Symfony application and one database. No queue workers, no separate
  frontend build, no client-side chart library.
- **Every route requires a role.** Access is denied by default.

## System context

```mermaid
---
title: System context
---
flowchart LR
    user["Viewer<br/>(ROLE_VIEW_OVERVIEW)"]
    editor["Data editor<br/>(ROLE_EDIT_DAILY_COUNTS)"]
    admin["Administrator<br/>(ROLE_MANAGE_DRIVING_DATA,<br/>ROLE_MANAGE_SUPPLIERS)"]

    app["Kørselsoverblik<br/>(Symfony web application)"]

    planning[("Planning system<br/>driving data extract (.xlsx)")]
    idp["Identity provider (OIDC)<br/>login and roles"]

    subgraph later["Later milestones"]
        telephony["Telephony system"]
        mailbox["Mailbox"]
        cases["Case management system /<br/>self-service forms"]
    end

    user -- "views overview" --> app
    editor -- "enters daily counts" --> app
    admin -- "uploads extracts, clears dates,<br/>manages suppliers and routes" --> app
    planning -. "exported by an administrator<br/>and uploaded" .-> admin
    idp -- "login,<br/>role claim" --> app

    telephony -. "call statistics" .-> app
    mailbox -. "mail counts" .-> app
    cases -. "complaints" .-> app

    classDef future stroke-dasharray: 5 5
    class telephony,mailbox,cases future
```

**Users:**

- **Viewers** read the overview.
- **Data editors** enter the daily counts of mails, complaints and phone calls.
- **Administrators** export extracts from the planning system and upload them, clear the
  driving data for a date, and manage suppliers and which supplier drives each route in each
  period. The identity provider manages the users.

These are tasks, not roles. Each task has its own permission role (see
[Authorization](#security)), so one user can do one task or many.

**Identity provider:** users sign in through the identity provider (OIDC). It also gives the
roles of each user.

**Later milestones:** the dashed systems feed the application directly, replacing manual entry.

## Containers

```mermaid
---
title: Containers
---
flowchart LR
    browser["Browser"]

    subgraph server["Docker host (itkdev-docker-compose)"]
        traefik["Traefik<br/>TLS and routing"]
        nginx["nginx<br/>static assets, FastCGI"]
        phpfpm["phpfpm<br/>Symfony 8 / PHP 8.4"]
        mariadb[("MariaDB<br/>application data")]
    end

    console["Console<br/>bin/console app:*"]
    idp["Identity provider (OIDC)<br/>idp mock service in local development"]

    browser -- "HTTPS" --> traefik
    browser -- "login redirect" --> idp
    phpfpm -- "token request" --> idp
    traefik -- "HTTP" --> nginx
    nginx -- "FastCGI" --> phpfpm
    phpfpm -- "Doctrine DBAL" --> mariadb
    console -- "docker compose exec phpfpm" --> phpfpm
```

The application runs on the ITK Dev Docker setup (`symfony-8` template):

| Container | Role |
| --- | --- |
| Traefik | TLS termination and routing to the site |
| nginx | Serves static assets and passes PHP requests to phpfpm |
| phpfpm | The Symfony 8 application on PHP 8.4 |
| MariaDB | All application data |
| idp | Local development only: an OIDC mock service (`ghcr.io/geigerzaehler/oidc-provider-mock`) with test users |

Console commands (`app:driving-data:*`) run in the phpfpm container.

Uploads are processed synchronously within the request. A queue (Symfony Messenger) and a worker
are added only when file sizes or integrations need them.

## Components

```mermaid
---
title: Components (milestone 1)
---
flowchart TB
    subgraph web["Web layer"]
        controllers["Controllers<br/>Overview, DailyCount, Security,<br/>Admin/Import, Admin/ClearDrivingData,<br/>Admin/Supplier, Admin/Route"]
        forms["Form types<br/>Import, DailyCountWeek,<br/>Supplier, Route"]
        twig["Twig templates and components<br/>Tile, Sparkline, LineChart, Panel"]
        format["FormatExtension"]
    end

    subgraph security["Security"]
        authenticators["OidcAuthenticator<br/>OidcRoleMapper"]
        guards["Role guards<br/>#[IsGranted], access_control"]
    end

    subgraph import["Import/DrivingData"]
        mapping["MappingLoader → MappingDto<br/>config/import/driving_data/*.yaml"]
        importrange["ImportRangeDto<br/>max APP_DRIVING_DATA_MAX_DAYS"]
        importer["DrivingDataImporter<br/>preview, import"]
        parser["StopRowParser"]
        replacer["StopRowReplacer"]
        builder["RouteBuilder"]
        aggregator["DailyStatsAggregator"]
        clearer["DrivingDataClearer"]
    end

    subgraph report["Report"]
        filter["PeriodFilter"]
        periods["PeriodRangeFactory"]
        kpi["KpiCalculator → Kpis"]
        delta["Delta / Tone"]
    end

    commands["Console commands<br/>app:driving-data:*"]
    repositories[("Doctrine entities<br/>and repositories")]

    controllers --> forms
    controllers --> twig
    twig --> format
    guards --> controllers
    authenticators --> guards

    controllers -- "upload: preview, confirm" --> importer
    commands --> importer
    importer --> parser
    importer --> replacer
    mapping --> parser
    importrange --> replacer
    parser --> replacer --> builder --> aggregator
    replacer --> repositories
    controllers -- "clear a date" --> clearer
    commands --> clearer
    clearer --> repositories
    builder --> repositories
    aggregator --> repositories

    controllers -- "overview" --> filter --> periods --> kpi
    kpi --> repositories
    kpi --> delta --> twig
```

| Namespace | Responsibility |
| --- | --- |
| `Controller` | Thin HTTP layer: checks the role, handles the form, calls a service, renders a template |
| `Form` | Hand-built form types for upload, daily counts and administration |
| `Security` | The OIDC authenticator, the mapping from the role claim to roles, and the role guards |
| `Import\DrivingData` | Reads an extract through a mapping and turns it into stored data and aggregates |
| `Supplier` | The periods of the supplier assignments, the overlap check, and the change event |
| `Report` | Period handling and the calculation of every figure |
| `Twig` | Formatting helpers and server-rendered components: tiles, sparklines, line charts, panels |
| `Command` | Console commands for imports and clearing |
| `Entity`, `Repository` | Doctrine entities and the aggregate queries |

**Rules:**

- **Logic lives in plain PHP classes.** All logic is in `Report` and `Import`, as plain classes with
  no framework dependencies where possible. Controllers, forms and commands only wire things
  together. This keeps most tests fast unit tests.
- **One import path.** The web upload and the console command use the same import services.

## Data model

```mermaid
---
title: Data model (milestone 1)
---
erDiagram
    USER ||--o{ IMPORT_BATCH : "uploads"
    USER ||--o{ DAILY_COUNT : "updates"
    SUPPLIER ||--o{ SUB_AGREEMENT_SUPPLIER_ASSIGNMENT : "drives"
    SUB_AGREEMENT ||--o{ SUB_AGREEMENT_SUPPLIER_ASSIGNMENT : "is assigned"
    SUB_AGREEMENT ||--o{ ROUTE : "has"
    ROUTE ||--o{ ROUTE_RUN : "runs"
    ROUTE ||--o{ STOP_ROW : "has"
    IMPORT_BATCH ||--o{ STOP_ROW : "inserted"
    IMPORT_BATCH ||--o{ REJECTED_STOP_ROW : "rejected"
    SUPPLIER |o--o{ DAILY_STATS : "aggregated per"

    USER {
        int id PK
        string email UK
        json roles
        datetime last_login_at
    }
    SUPPLIER {
        int id PK
        string name UK
    }
    SUB_AGREEMENT {
        int id PK
        string reference UK "for example AB-CD"
    }
    ROUTE {
        int id PK
        string source_key UK "route name in the extract"
        int sub_agreement_id FK
    }
    SUB_AGREEMENT_SUPPLIER_ASSIGNMENT {
        int id PK
        int sub_agreement_id FK
        int supplier_id FK
        date valid_from
        date valid_to "null = no end date"
    }
    IMPORT_BATCH {
        int id PK
        string mapping_name
        string file_name
        int uploaded_by FK "null = the console"
        datetime uploaded_at
        date first_date "first date of the replaced range"
        date last_date "last date of the replaced range"
        int rows_deleted
        int rows_inserted
        int rows_rejected
        int warning_count
        json errors "groups of field and reason, no field values"
        json warnings "groups of field and reason, no field values"
    }
    STOP_ROW {
        int id PK
        string mapping_name UK "unique with row_key"
        string row_key UK "optional, from the mapping"
        int route_id FK
        date service_date
        string stop_type "pickup or drop_off"
        string stop_status
        int run "block number in the extract"
        int passengers_after
        float next_distance_km
        float next_duration_min
        datetime planned_time
        datetime actual_time
        bool planned_solo
        string person_id "from the extract (ADR 004)"
        json source_values "the copy of the row (ADR 006)"
        int import_batch_id FK
    }
    REJECTED_STOP_ROW {
        int id PK
        int import_batch_id FK
        int sheet_row_number "row_number is a reserved word in MariaDB"
        string reason "RejectionReason code"
        string field "null = the row id"
        date service_date "null if the date field is not valid"
        json source_values "the copy of the row (ADR 006)"
    }
    ROUTE_RUN {
        int id PK
        int route_id FK
        date service_date
        int run "block number in the extract"
        datetime last_drop_planned
        datetime last_drop_actual
        int delay_minutes
        int peak_passengers
        bool cancelled
        float driven_km
        float empty_km
        float driven_min
        float empty_min
    }
    DAILY_STATS {
        int id PK
        date service_date UK "unique with supplier_id"
        int supplier_id FK "null = Unknown"
        int trips
        int trips_completed
        int trips_cancelled
        int route_runs
        int last_drop_offs
        int on_time
        int solo_runs
        float driven_km
        float empty_km
        float driven_min
        float empty_min
    }
    DAILY_COUNT {
        date day PK
        int mails
        int complaints
        int phone_calls
        int updated_by FK
        datetime updated_at
    }
```

**Terms:**

- **Route (vognløb):** a vehicle schedule in the planning system, identified by its name in the
  extract, for example `AB-CD-01`.
- **Sub-agreement (delaftale):** the part of a route name before the last `-` or `_` and the
  digits after it, for example `AB-CD` in `AB-CD-01`, or `TEST` in `TEST_01`. A name without such
  an end is a sub-agreement as a whole. The application does not split the sub-agreement further,
  because not all names have the same format. A reference can itself end in a number, for example
  `TEST-2` from `TEST-2-01`. Thus the form does not apply the route name rule to a typed
  reference. An administrator assigns each sub-agreement to a supplier for a date range. On a date
  with no assignment, its routes count as "Unknown".
- **Route run:** one chain of pickups and drop-offs on a route, from the first citizen getting in
  until the vehicle is empty again. A route can have several runs a day. The extract gives each
  run a block number. Punctuality and solo routes are measured per run.
- **Trip:** one citizen's journey from pickup to drop-off.

| Entity | Purpose |
| --- | --- |
| `User` | An account from the identity provider: email (the `upn` claim), roles from the last login, time of the last login. No password |
| `Supplier` | A transport supplier |
| `SubAgreement` | A sub-agreement. `SubAgreementReference::fromRouteName()` finds it from a route name |
| `Route` | A route from the extract, with its sub-agreement. The import creates it |
| `SubAgreementSupplierAssignment` | The supplier that drives the routes of a sub-agreement from `valid_from` to `valid_to` (empty = no end date) |
| `ImportBatch` | One upload: mapping, file name, who and when, the replaced date range, row counts, and the rejection reasons and warnings as groups of field and reason |
| `StopRow` | One stop from the extract, with an optional row key from the mapping and the copy of the row. See [open decisions](#open-decisions) |
| `RejectedStopRow` | A rejected row in the range of its upload, with the row number, the reason and the copy of the row. A later read through a corrected mapping can import it |
| `RouteRun` | Derived: one route run with its last drop-off, delay, peak passengers and distances |
| `DailyStats` | Derived: the counters for one date and supplier that every driving figure is summed from |
| `DailyCount` | Manually entered mails, complaints and phone calls for one date |

**Supplier assignments have dates.** A sub-agreement can change supplier from a given date. The
supplier comparison must keep the past figures of each supplier, so:

- An assignment has a `valid_from` date and an optional `valid_to` date. Both dates are part of
  the period.
- The assignments of one sub-agreement must not overlap. `AssignmentDto` holds the form data, and
  the `NoOverlappingAssignment` constraint compares it with the other assignments. The database
  cannot prevent overlap. A unique index on `(sub_agreement_id, valid_from)` only rejects two
  assignments with the same first date, for example the same form sent two times. If overlap
  occurs anyway, the assignment with the latest first date wins.
- To change supplier, end the current assignment and add a new one from the change date.
- `AssignmentManager` saves or deletes an assignment, and dispatches `SubAgreementSupplierChanged`
  with the old and the new period.
- `DailyStatsAggregator` finds the supplier for each route and date through the sub-agreement of
  the route.

**Derived tables are always rebuildable.** `RouteRun` and `DailyStats` are rebuilt from `StopRow`
for the dates an import touched. When an assignment is added, changed or deleted, `DailyStats` is
rebuilt only for the dates of the old and the new date range. Figures for other dates do not
change. The derived tables hold no information that can't be recalculated.

## Importing driving data

```mermaid
---
title: Upload driving data
---
sequenceDiagram
    autonumber
    actor Admin as Administrator
    participant C as Admin\ImportController
    participant P as StopRowParser
    participant M as MappingDto
    participant U as StopRowReplacer
    participant B as RouteBuilder
    participant A as DailyStatsAggregator
    participant DB as MariaDB

    Admin->>C: POST /admin/import (file, first date, last date)
    C->>C: check ROLE_MANAGE_DRIVING_DATA and CSRF
    C->>C: ImportRangeDto(first, last), at most APP_DRIVING_DATA_MAX_DAYS
    C->>C: keep the file in var/, and its token in the session
    C->>P: parse(file, mapping, range)
    P->>M: column, type and value map per field
    P->>P: reject rows outside the range (ImportRangeDto::contains)
    P-->>C: rows, and rejected rows: row number and reason, no values
    C->>DB: count the stored rows in the range
    C-->>Admin: preview: range, rows to delete, insert and reject, dates that become empty
    Note over Admin,C: no data changes before the confirmation

    Admin->>C: POST confirm (token from the session, CSRF)
    C->>C: remove the token from the session (one time only)
    C->>P: parse(file, mapping, range)
    P-->>C: rows, rejected rows
    C->>DB: begin transaction, create ImportBatch
    C->>U: replace(range, rows)
    U->>DB: delete the StopRow and RejectedStopRow rows in the range
    U->>DB: insert the rows and the rejected rows of the file, with their copies
    Note over U,DB: each date in the range holds only the rows of the file
    U-->>C: ImportResultDto: counts, reasons, warnings, empty dates
    C->>B: rebuild(range)
    B->>DB: replace RouteRun rows in the range
    C->>A: rebuild(range)
    A->>DB: replace DailyStats rows in the range
    C->>DB: update ImportBatch counts, commit
    C->>C: delete the file
    C-->>Admin: result page
```

### Import mappings

A mapping file, `config/import/driving_data/<MAPPING_NAME>.yaml`, describes one source's extract:

- the sheet and the header row
- an optional **row key**: one or more source columns that identify a row. It finds duplicate
  rows in one file
- optional **kept columns**: the other columns that the copy of a row keeps (see
  [Keep a copy of each imported row](#keep-a-copy-of-each-imported-row))
- optional **excluded columns**: the columns that the mapping leaves out on purpose, for example
  the personal data
- for each application field: the source column, type, date format, value maps for codes, and
  whether it is required

`MappingLoader` validates the file with the Symfony Config component when the container is built,
so an invalid mapping stops the application from starting. The parser removes the spaces at the
start and the end of the header names, so the validation rejects a column name with such a space.
An excluded column cannot also be a field column, a row id column or a kept column. The loader
then produces an immutable `MappingDto` value object. `StopRowParser` reads the workbook only
through `MappingDto` and never refers to column names itself.

`app:driving-data:mapping:validate` checks a sample file against a mapping before it is used. It
uses the same parser without a date range, and `ExtractSummaryDto` counts the result. The output
hides the person id and never shows a value of a rejected row or a warning. It also hides the row
key when a row id column is the person id column. `ExtractColumnsDto` compares the header with the
mapping. The output names the kept columns and the excluded columns that are not in the file, and
the columns that the mapping does not read. An excluded column is a decision, so it is never a
column that is not read. The fixture writer writes the excluded columns with no values, as the
reference extract has them.

**What a mapping can change:** the column names, the code values and the formats of the fixed
application fields. Each application field must have one source column. These changes need code:

- a field that the extract does not have
- a field that is made from more than one column, or a column that holds more than one field
- a new application field

### Keep a copy of each imported row

An upload deletes the extract after the import, so a column that the import does not keep is lost
for all the uploaded dates. Thus the parser keeps a copy of each row
([ADR 006](adr/006-keep-a-copy-of-each-imported-row.md), proposed):

- The copy holds the field columns, the row id columns and the kept columns of the mapping, keyed
  by the column name. A later task can read the copies again through a changed mapping, with no
  new upload.
- `SourceCells` makes the copy, and the cells again from a copy. The copy keeps the type of each
  cell: text, numbers and booleans are JSON values, and a duration, a date and time and an Excel
  error are objects with one key, for example `{"duration": "P0DT25H10M0S"}`. An empty cell is
  left out.
- A formula cell gives its computed value, in the parser (`CellConverter`) and in the copy. The
  formula is not kept. When the result of the formula is an error, the reader gives no computed
  value, so the cell is empty.
- A rejected row also has a copy, and its service date if the date field is valid. A row outside
  the range of the upload has no copy, because the upload for its date imports it.
- In an optional field, a value that the parser cannot read gives an empty value and a
  `FieldWarningDto`. The row is imported, and the copy keeps the value.
- The copy never holds the personal data of the extract. A column is kept only when the mapping
  names it, so a new column is not kept until a person adds it. The `default` mapping names the
  personal columns in `exclude_columns`, and the validation stops a column that is both kept and
  excluded.
- The copy has the values on purpose. It never goes to a log, a message or the output of the
  validate command.

### The reference extract

A reference extract from the planning system gives the layout for the `default` mapping. The
extract is not in Git, and the docs contain no values from it.

**Layout:**

- One sheet. Row 1 is a title, row 2 is empty, and row 3 has the column headers.
- One row for each stop. Each trip has a pickup row and a drop-off row.
- The extract holds one service day. The date is in its own column, and the times have no date.
- The columns with names, addresses and places are empty in the reference extract.

**Columns in the `default` mapping:**

| Field | Column | Type in the extract | Note |
| --- | --- | --- | --- |
| row key | `RouteStopID` | text | Unique for each row. |
| `route` | `RouteName` | text | To be confirmed: see "Route column" in [open decisions](#open-decisions). |
| `person_id` | `PersonID` | text | [ADR 004](adr/004-personal-data-in-driving-data.md) |
| `service_date` | `Date` | text, `Y-m-d` | The service day of all the times in the row. |
| `run` | `blocknumber` | integer | The route run. A route run is the route, the date and the run. |
| `stop_type` | `StopType` | text code | Two codes: pickup and drop-off. |
| `stop_status` | `Status` | text code | Three codes: completed, cancelled, and no registration (`pending`). |
| `passengers_after` | `passengersaftercancel` | integer | The passengers after the stop, when the cancellations are removed. |
| `next_distance_km` | `ToNextDistance` | decimal | Kilometres. Can be empty. |
| `next_duration_min` | `ToNextDuration` | decimal | Minutes. Can be empty when the distance is not. |
| `planned_time` | `PlannedDeparture` | Excel time, `[hh]:mm:ss` | A wall-clock time on `service_date`. Can be 24:00 or more (see [Times](#times)). |
| `actual_time` | `StatusUpdateTime` | Excel date and time | Empty when the stop has no registration. |
| `planned_solo` | `kørselstype` | text code | Two codes: solo and shared. |

**Why the planned time comes from `PlannedDeparture`:** the extract has a planned arrival
(`PlannedArrival`) and a planned departure (`PlannedDeparture`) for each stop. The departure is
some minutes after the arrival. The transport office measures punctuality against the planned
departure, because the driver registers the stop when the citizen is in or out of the car.
`StatusUpdateTime` is that registration.

**Why the run comes from `blocknumber`:** the extract also has a column for the start and a column
for the end of a block. In the reference extract, one run has a start but no end. A split on the
end flag joins that run with the next run. The block number gives each run directly.

**Columns in the copy only:** the `default` mapping keeps all the other columns in the copy of a
row, but not the personal data. For example:

- the ids of trips, trip requests, routes, addresses, locations, places and paths
- the requested times, the service times, the planned arrival and the planned activity times
- the cancellation columns: the cancellation id, the time of the cancellation, the notice in
  minutes (negative when the cancellation came after the planned time), and whether the notice
  was in time
- `prisprminut`, the price per minute, for the cost of empty running
- `passengers`, the passengers after the stop before the cancellations are removed
- `blokstart` and `blokslut`, and `Placename`, the institution of a trip

Milestone 2 can add typed fields for these columns, and fill them from the copies.

**Columns that are never kept:** the personal data. `PersonName`, `Adresse`, `Post-nr` and `By`
identify a citizen. `Labels` and `Comment` can hold the needs of a citizen or free text, and that
can be health data ([ADR 004](adr/004-personal-data-in-driving-data.md)).

### Times

A `time` field is an Excel cell with a duration format, such as `[hh]:mm:ss`. OpenSpout gives
such a cell as a `DateInterval`. A cell with an ordinary time format, such as `hh:mm`, is
rejected. The time is a wall-clock time on the `service_date` of the row. A time of 24:00 or
more is on the next day. For example, 25:10 is 01:10 on the day after `service_date`.

The application supports one time zone only
([ADR 005](adr/005-dates-and-times-in-one-time-zone.md)). The times in the extract are
wall-clock times in the PHP default time zone (`date.timezone` in `php.ini`, `Europe/Copenhagen`).
MariaDB must use the same time zone. The import stores the times with no conversion:

- `CellConverter` reads a date and time as a wall-clock time. It uses UTC for the PHP object,
  because UTC has no change to summer time.
- `StopRowDto` reads the wall-clock time in the PHP default time zone.
- `service_date` and the dates of `ImportRangeDto` are calendar dates at 00:00 in the PHP
  default time zone.
- A time that does not exist moves one hour later. For example, 02:30 on the night of the change
  to summer time is read as 03:30 summer time.
- A time that occurs two times, on the night of the change to winter time, is read as winter
  time (the second one).
- Calculate a delay from `getTimestamp()`. A delay across a change to or from summer time is
  then correct.

The browser shows the times as they are stored.

### Date range

The user enters the first and the last date of the export, together with the file.
`ImportRangeDto` holds the two dates. The import replaces exactly this range
([ADR 003](adr/003-driving-data-import-mapping-and-overwrite.md)):

- A date in the range with no rows in the file becomes empty. This includes the first and the
  last date.
- A row with a date outside the range is rejected. A wrong date in one row cannot change other
  dates. `ImportRangeDto::contains()` is the only check. `StopRowParser` calls it for each row,
  so the preview and the import reject the same rows.
- The range can be at most `APP_DRIVING_DATA_MAX_DAYS` days (default 31). A longer range is
  rejected, and no data changes.

### Pipeline

`DrivingDataImporter` is the one service for the upload page and the import command.
`preview()` does step 1, and `import()` does steps 3 to 7 in one transaction
(`EntityManager::wrapInTransaction()`). The replacement assumes that each extract holds whole
days and all routes of a day (questions 3 and 4 in [open decisions](#open-decisions)).

1. **Preview.** The upload keeps the file in `var/` with a random name, and keeps its token in
   the session. The parser reads the file, and the page shows the range, the rows to delete,
   insert and reject, and the dates that become empty. No data changes.
2. **Confirm.** The user confirms. The controller removes the token from the session before the
   import, so the token works one time only. A second confirmation, for example after a double
   click, finds no token. It shows a message and imports nothing. The steps below parse the file
   again and import it. If the user cancels, the file is deleted.
3. **Parse.** Before the first row, `ExtractInspector` checks the whole file against the
   `ExtractLimitsDto` limits. See "Data protection" in [Security](#security). Then
   `StopRowParser` reads each row and converts the fields. Invalid rows are rejected
   with their row number and a reason. A row with a date that `ImportRangeDto::contains()` does
   not accept is also rejected. A bad value in an optional field gives a warning, not a rejection.
   The reason and the warning are `RejectionReason` codes, so field values are never written to
   messages or logs. The person id is kept as it is in the extract
   ([ADR 004](adr/004-personal-data-in-driving-data.md), proposed). Each row, and each rejected
   row in the range, has a copy of its columns (see
   [Keep a copy of each imported row](#keep-a-copy-of-each-imported-row)).
4. **Replace.** `StopRowReplacer` deletes all `StopRow` rows in the range, from all mappings, and
   then inserts the parsed rows. It does not check the range again. Rows that are not in the new
   file are removed, and two uploads can never count the same date twice. A row key that occurs
   more than once in the file is rejected after the first occurrence, with the reason
   `duplicate_row_id`, and a unique index on `(mapping_name, row_key)` makes this sure.
   - The replacer also deletes the `RejectedStopRow` rows with a date in the range, and the
     rejected rows with no date of the earlier batches whose whole range is in the new range. Then
     it stores the rejected rows of the file with their copies. A row outside the range is not
     stored, because the upload for its date imports it.
   - It finds each route by its name, or creates it. A new route finds or creates its
     sub-agreement with `SubAgreementReference`. A new sub-agreement has no assignment, so its
     routes count as "Unknown".
   - It saves the rows in groups of 500, and then the entity manager forgets them, so the memory
     use does not grow with the file.
   - It returns an `ImportResultDto`: the deleted and inserted counts, the rejected rows and the
     warnings as `RejectionGroups` (field, reason, count and the first 10 row numbers), and the
     dates of the range with no rows.
   - A row key that is stored on a date outside the range breaks the unique index, and the whole
     import fails with no change. With whole days and unique row ids, this does not occur.
5. **Build route runs.** `RouteBuilder` replaces `RouteRun` rows for the range. It groups the
   stops by route, date and run (the block number in the extract), and orders the stops of each
   run by planned time.
6. **Aggregate.** `DailyStatsAggregator` replaces `DailyStats` rows for the range, one per
   supplier.
7. **Clean up.** The uploaded file is deleted, and the `ImportBatch` is updated with the counts,
   the rejection reasons and the warnings (`ImportBatch::complete()`). They never have a field
   value.

Steps 3 to 7 run in one database transaction. A failed import leaves the data as it was.

**Unconfirmed files:** `app:driving-data:purge-uploads` deletes the upload files that are older
than one hour. A cron job on the server runs it every 15 minutes, and each new upload also runs
it. A confirmation after the purge shows that the upload has expired, and imports nothing.

**Whole days and the date range:** a file that holds only part of a day removes the other rows of
that day. A range that is longer than the export empties the extra dates. Users must upload whole
days and enter the range of the export. The preview lists the dates that become empty.

**No rollback:** an `ImportBatch` cannot be undone. To correct a wrong upload, upload the correct
file for the same range, or clear the dates.

### Clearing driving data

The upload page lists the uploaded dates, one month at a time. Each date shows the number of stop
rows and route runs, and the last upload (file name, who, when). Users with
`ROLE_MANAGE_DRIVING_DATA` can delete the driving data for a date, for example after a wrong
upload.

1. The user selects **Clear** for a date.
2. The user confirms.
3. `DrivingDataClearer` deletes `StopRow`, `RouteRun` and `DailyStats` for the date in one
   transaction. `DailyCount` does not change.
4. The application logs who cleared which date, and the deleted counts. The log has no field
   values.

`app:driving-data:clear <FROM> <TO>` uses the same service to clear a date range.

## Calculating the figures

```mermaid
---
title: Overview request
---
sequenceDiagram
    autonumber
    actor User
    participant FW as Firewall / authenticator
    participant C as OverviewController
    participant F as PeriodFilter
    participant R as PeriodRangeFactory
    participant K as KpiCalculator
    participant DB as MariaDB
    participant T as Twig

    User->>FW: GET /?p=month&d=2026-09-18&c=previous&s=3
    FW->>FW: user from the session (OIDC login)
    FW->>C: IsGranted ROLE_VIEW_OVERVIEW
    C->>F: map query string (invalid values fall back to defaults)
    C->>R: current, comparison and 12 trailing months
    R-->>C: date ranges (comparison cut to the same number of days if partial)
    loop current, comparison, each month
        C->>K: calculate(range, supplier)
        K->>DB: SUM over DailyStats and DailyCount, distinct citizens
        K-->>C: Kpis
    end
    C->>C: Delta and Tone per figure
    C->>T: render tiles, chart and supplier table
    T-->>User: HTML with inline SVG charts
```

**Filters:** `PeriodFilter` maps the query string (`p` period, `d` date, `c` comparison,
`s` supplier). Invalid values fall back to the defaults, so a link always works.

**Periods:** `PeriodRangeFactory` turns the filter into three sets of date ranges:

- the selected period
- the comparison period: the previous period, or the same period last year. If the selected
  period has not ended, the comparison is cut to the same number of days.
- the 12 trailing months for sparklines and the trend chart

**Calculation:** `KpiCalculator` runs one aggregate query per range and returns a `Kpis` value
object.

| Figure | Calculation |
| --- | --- |
| Empty running (%) | `SUM(empty_km) / SUM(driven_km)` |
| Trips | `SUM(trips)` |
| Citizens | `COUNT(DISTINCT person_id)` over the `StopRow` rows in the range ([ADR 004](adr/004-personal-data-in-driving-data.md)) |
| Solo routes | `SUM(solo_runs)`, shown with `SUM(solo_runs) / SUM(route_runs)` |
| Punctuality (%) | `SUM(on_time) / SUM(last_drop_offs)` |
| Complaints | `SUM(complaints)`, shown with `SUM(complaints) / SUM(trips) × 1000` |
| Mails | `SUM(mails)` |
| Phone calls | `SUM(phone_calls)` |

**Rules:**

- Ratios are calculated from sums and never averaged from daily percentages.
- A zero denominator gives "no value" (shown as "–"), not zero.
- The supplier filter applies to `DailyStats`. `DailyCount` is office-wide.

**Delta and tone:** `Delta` gives the change from the comparison period, and whether it is better
or worse for that figure. `Tone` maps a value to ok, warn or danger using thresholds from one
config file.

## Security

```mermaid
---
title: Authentication (OIDC)
---
flowchart TD
    request["Request"] --> public{"Public path?<br/>/openidconnect/*, /openid-connect/generic, assets"}
    public -- yes --> serve["Serve"]
    public -- no --> session{"Signed in?"}

    session -- no --> idp["Redirect to the identity provider"]
    idp -- "login fails in the identity provider" --> idpfail["No redirect back<br/>not in the authentication log"]
    idp -- "user approved" --> callback["Callback route<br/>OidcAuthenticator checks state and token"]
    callback -- invalid --> fail["Login failed<br/>written to the authentication log"]
    callback -- valid --> mapper["OidcRoleMapper<br/>role claim → ROLE_ADMIN and permission roles"]
    mapper --> user["Create or update User<br/>email, roles, last login"]
    user --> log["Authentication log<br/>email, roles, time"]
    log --> guard

    session -- yes --> guard{"#[IsGranted] role?"}
    guard -- granted --> controller["Controller"]
    guard -- denied --> forbidden["403"]
```

**Authentication:** OIDC is the only login method (see
[ADR 002](adr/002-oidc-authentication.md)).

- There is one firewall with `OidcAuthenticator`. It extends `OpenIdLoginAuthenticator` from
  `itk-dev/openid-connect-bundle`.
- An anonymous user is sent to the bundle's login route under `/openidconnect`, and from there
  to the identity provider. After the login, the
  identity provider sends the user back to the callback route, `/openid-connect/generic`.
- At each login, `OidcRoleMapper` reads the role claim (`OIDC_ROLE_CLAIM`). It keeps the values
  that are `ROLE_ADMIN` or a permission role, and ignores the rest. The `User` is created or
  updated with these roles.
- The application does not edit roles. A role change in the identity provider applies at the
  next login.
- Logout ends the session in the application.
- A failed login shows a page with a "Prøv igen" link (`OidcLoginFailureListener`): 403 when the
  identity provider refused, and 500 otherwise. It does not redirect, so a broken login cannot
  loop.
- **Authentication log:** the bundle's audit trail records each successful login. It also
  records each login that the identity provider approved but the application rejected, with the
  IP address and the reason. A login that fails in the identity provider does not come back to
  the application, so it is not in this log. `OidcLogin` adds a record with the email and the
  roles at each login. Both go to the `openid_connect_audit` channel, which Monolog writes to
  `var/log/openid_connect_audit.<APP_ENV>.log`.
- Local development uses the `idp` mock service. Functional tests sign in with `loginUser()`.

**Authorization:**

- Each part of the application has its own permission role. Controllers check only these:

  | Permission role | Grants |
  | --- | --- |
  | `ROLE_VIEW_OVERVIEW` | The overview |
  | `ROLE_MANAGE_DRIVING_DATA` | The upload of driving data, and clearing the driving data for a date on the upload page |
  | `ROLE_EDIT_DAILY_COUNTS` | The daily counts form |
  | `ROLE_MANAGE_SUPPLIERS` | Suppliers, sub-agreements and sub-agreement → supplier assignments |

- `ROLE_ADMIN` gives all permission roles. At first, users get `ROLE_ADMIN` only. Narrower
  access later needs only other values in the role claim, or a new bundle in `role_hierarchy`.

  ```yaml
  role_hierarchy:
    ROLE_ADMIN:
      - ROLE_VIEW_OVERVIEW
      - ROLE_MANAGE_DRIVING_DATA
      - ROLE_EDIT_DAILY_COUNTS
      - ROLE_MANAGE_SUPPLIERS
  ```

- Every signed-in user also has `ROLE_USER`. It grants nothing alone.
- Every controller has a class-level `#[IsGranted]` with a permission role.
- `access_control` denies everything to anonymous users except the OIDC routes
  (`/openidconnect/*` and `/openid-connect/generic`) and the assets.
- A test walks the router. It fails if an application route has no `IsGranted`, or if it checks
  `ROLE_ADMIN` or `ROLE_USER`.

**Data protection:**

- Forms use CSRF tokens.
- Uploads are limited to `.xlsx` and a maximum size. The file is kept in `var/`, outside the
  public folder, until the user confirms or cancels. The scheduled purge deletes a file that is
  older than one hour (see [Pipeline](#pipeline)). The extract can hold health data, so the file
  must not stay.
- The confirmation uses a token from the user's session, so a user cannot confirm the upload of
  another user. The token works one time only, so a double click cannot import the file two
  times.
- The parser reads the extract with OpenSpout, which streams one row at a time. Before OpenSpout
  opens the file, `ExtractInspector` checks the file size, and that the xlsx archive has at most
  1000 files. Then it reads each XML file in the archive one node at a time, and checks the
  row numbers and the columns against configurable limits (ASVS V12.1.2). A file above a limit
  gives no rows. The inspector reads every XML file, because OpenSpout does not check the
  `spans` of a row: a small file can make one row use all the memory.
- The inspector counts the bytes that it reads from the archive, and stops one byte after the
  uncompressed-size limit. It does not use the sizes in the archive, because libzip does not
  check them: a zip bomb can give a false size. Thus the limit also controls the time of an
  upload. A file in the archive that libzip cannot read, for example an encrypted file, is
  rejected.
- The inspector rejects XML with a `DOCTYPE`, so an extract cannot use external entities (XXE,
  ASVS V5.5.2). OpenSpout does not read an external entity, but it ignores a cell with one. The
  tests keep this protection if a new version of OpenSpout changes.
- The import runs synchronously in the request. The limits above keep it short.
- Rejection reasons and logs never contain field values from the extract.

**Passwords and multi-factor authentication** (ASVS V2.1, V4.3.1): the application stores no
passwords. The identity provider applies its password rules and multi-factor authentication.

**Dependencies** (ASVS V14.2.1): the PR workflow runs `composer audit`. Dependabot is an
option for a later milestone.

**HTTP security headers** (ASVS V14.4): `nelmio/security-bundle` adds these headers to every
response. The configuration is in `config/packages/nelmio_security.yaml`, and
`tests/Functional/SecurityHeadersTest.php` checks it.

| Header | Value |
| --- | --- |
| `Content-Security-Policy` | `default-src 'self'; script-src 'self' 'strict-dynamic' 'nonce-…'; img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'` |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `same-origin` |

- There is no `style-src`, because `default-src 'self'` gives the same rule.
- A redirect has no content, so it gets only `Referrer-Policy`.
- In debug mode, Symfony removes the policy from its exception page.

`Strict-Transport-Security` is not sent yet. Nelmio sends it only when Symfony sees HTTPS. Behind
Traefik, that needs trusted proxies, and a wrong configuration causes a redirect loop. A separate
issue adds the header.

**Effects of the Content Security Policy:**

- Templates have no inline scripts, no inline event handlers and no `style` attributes.
- The only inline scripts are the import map and its entry point. `importmap()` writes them, and
  they get the nonce from `csp_nonce('script')`. A later inline script also uses the nonce.
- `'strict-dynamic'` lets the scripts with the nonce load the modules. The AssetMapper
  documentation recommends it, because the import map has a `data:` entry for each CSS import.
- The es-module-shims polyfill is off (`importmap_polyfill: false`). It is an inline script that
  loads a file from a CDN, and all supported browsers have import maps.
- The server-rendered SVG charts use classes and SVG presentation attributes for styling.
- The `popover` help texts need no script.

## User interface

**Rendering:**

- Server-rendered Twig with AssetMapper. There is no Node build step.
- Styling comes from the itkaarhus design system, mapped in AssetMapper as `itkaarhus-ds`, plus
  the prototype's stylesheet ported to `assets/styles/app.css`.
- Charts are Twig components that render inline SVG on the server. Each chart includes a hidden
  data table for screen readers. No chart JavaScript is used.
- Help texts (**?**) use the native HTML `popover` attribute.
- Icons are Lucide icons with Symfony UX Icons (`ux_icon('lucide:car')`). The SVG files are in
  `assets/icons/` and in git, so the application makes no calls to the Iconify API. To add an
  icon, run `task console -- ux:icons:import lucide:<NAME>`.
- The page loads no file from an external host. The design system has no font files, so the
  layout uses the fallback fonts of `--itkaarhus-font-sans` (Helvetica Neue, Arial). Inter has
  the SIL Open Font License, so a later task can add it to `assets/fonts`.

**Menu:** `MainMenu` defines the items, and `MenuBuilder` removes the items that the user must
not see. An item is visible only if the user has its permission role and its route exists.
`MenuBuilder` generates each URL one time and gives `MenuLink` objects to the template. A menu
route takes no parameters. `MainMenu` already has the item for daily counts, so it shows when
the route is added.

The menu has these items:

- Overview
- Daily counts
- Administration: Upload driving data, and Leverandører og delaftaler. `MenuBuilder` has no
  groups now. The upload page adds the group, because the group then has two items. Until then,
  "Leverandører og delaftaler" is an item at the top level, and it goes to
  `admin_supplier_index`.

**Language:** the UI is in Danish. Texts live in `translations/messages+intl-icu.da.yaml`, and
`FormatExtension` formats numbers, percentages, percentage points, deltas and period labels.

**Filters:** all state is in the URL, so every view can be bookmarked and shared.

## Testing

The project is developed test first, with 100 % line coverage and mutation testing. See
[Tests](../README.md#tests) in the README for the full rules.

| Layer | What | Tools |
| --- | --- | --- |
| Unit | `Report`, `Import`, value objects, `FormatExtension` | PHPUnit |
| Integration | Repositories and aggregate queries, replacement and clearing, commands | `KernelTestCase`, MariaDB, `dama/doctrine-test-bundle` |
| Functional | Every route × role, forms, the OIDC login with a stubbed identity provider, upload end to end, a second confirmation of one upload, the overview | `WebTestCase` |

**Mutation testing (Infection):**

- `src/Report` and `src/Import` require a covered MSI of 100 %. The rest of the mutated code
  requires 80 %.
- Controllers, form types and Twig components are not mutated.
- Pull requests run it on changed lines. Release branches run it on the whole code base.

### Test data

The tests and the local site use generated extracts. No real extract is committed.

- **The generator:** `ExtractWriter` writes an `.xlsx` with the layout of the `default` mapping:
  the rows above the header, the column headers and the code values. The input uses the
  application field names and values, for example `pickup` and `completed`. So a change of the
  mapping changes the files, and the scenarios stay the same.
- **Scenarios:** YAML files in `tests/fixtures/driving_data/`. Each scenario has an `expected`
  section with the totals worked out by hand. The test compares the result of the import with
  these totals.
- **Bad data:** a stop can give a raw cell value, so that a scenario can hold a bad date, an
  unknown code or text in a number column. A scenario can also leave out a column.
- **Times:** the parser tests include times of 24:00 or more, and the spring and autumn dates of
  the change to and from daylight saving time in Europe/Copenhagen (see [Times](#times)).
- **Development dataset:** `DevDatasetGenerator` makes 13 months of data from a seed, one file
  for each calendar month. The routes and the suppliers are the same as in the development
  fixtures, so the overview shows figures for each supplier.
- **Maximum range:** a development file holds one calendar month, so it has at most 31 days. This
  is the default of `APP_DRIVING_DATA_MAX_DAYS`. If the setting is less than 31, the upload
  rejects these files.
- **Files:** `task fixtures:extracts` writes all files to `var/fixtures/driving_data/`. Upload
  them by hand to test the upload page.

## Deployment

**Pipelines:** Woodpecker deploys with `itkdev/ansible-plugin`:

- a push to `release/*` or `hotfix/*` deploys to staging
- a GitHub release on `main` deploys to production

**Deploy steps:** each deploy updates the code and runs `composer install --no-dev`, the
Doctrine migrations and `cache:clear` in the server's phpfpm container.

**Configuration:** server configuration lives in `.env.local` on the server. Woodpecker secrets
hold the connection details.

**Cron:** a cron job on the server runs `app:driving-data:purge-uploads` every 15 minutes in the
phpfpm container.

**CI:** GitHub Actions runs coding standards, PHPStan, tests with the coverage gate, mutation
testing and the diagram check on every pull request.

## Open decisions

- **Personal data in driving data** ([ADR 004](adr/004-personal-data-in-driving-data.md),
  proposed).
  - The proposal: `StopRow` keeps the person id from the extract, with no HMAC. The data model
    above shows it.
  - The product owner in the transport office decides, with the data protection adviser.
  - The parser implements the proposal. The decision must be accepted before go-live.
- **Whole days.** Does each extract hold whole days? The replacement of the date range depends
  on it. A partial day removes the other rows of that day. The product owner in the transport
  office answers before the replacement is built.
- **All routes in one extract.** Does one extract hold all the routes of a day, or only one area
  or contract? The import replaces all stop rows in the date range. If the extracts are per
  area, an upload for one area deletes the other areas. Then the replacement must be limited to
  the routes or the area of the file ([ADR 003](adr/003-driving-data-import-mapping-and-overwrite.md)).
  The product owner in the transport office answers before the replacement is built.
- **Maximum range.** What can the planning system export? Evaluate the default of
  `APP_DRIVING_DATA_MAX_DAYS` (31 days) after a test export. The developer answers this with the
  transport office before go-live.
- **Route column.** Which column identifies a route: the route name or the route id? The route id
  can be an id for one day only. The `default` mapping uses the route name until this is
  confirmed. The product owner in the transport office answers before the route administration
  is built.
- **Supplier mapping (answered).** A route belongs to the sub-agreement in its name: the part
  before the last `-nn` or `_nn`. An administrator assigns each sub-agreement to a supplier for a
  date range. See the terms in [Data model](#data-model).
- **Booked trips.** Some trips are booked outside the fixed routes, and have a common route name.
  Who drives them? Can more than one supplier drive them? With one route for all of them, they
  get one supplier assignment. The product owner in the transport office answers before the
  route administration is built.
- **Stops with no registration.** How do the figures treat a stop that has no status
  registration from the driver (`pending`)? The proposal: it counts as a trip, because it is not
  cancelled. A run whose last drop-off has no actual time is not counted for punctuality. The
  product owner in the transport office answers before the daily stats are built.
- **Actual time.** Is `StatusUpdateTime` the actual time of the stop? It is the time of the
  status registration by the driver. The mapping uses it until the product owner answers.
- **Role claim.** Which claim holds the roles, and which values does the identity provider
  send? Which group is assigned to the application? IT operations sets this up before go-live.

## Decision records

See [`adr/`](adr/README.md):

- [001: Stack](adr/001-stack.md)
- [002: OIDC authentication with roles from the identity provider](adr/002-oidc-authentication.md)
- [003: Driving data import with mappings and replacement of the entered date range](adr/003-driving-data-import-mapping-and-overwrite.md)
- [004: Personal data in driving data](adr/004-personal-data-in-driving-data.md)
- [005: Dates and times in one time zone](adr/005-dates-and-times-in-one-time-zone.md)
- [006: Keep a copy of each imported row](adr/006-keep-a-copy-of-each-imported-row.md)
