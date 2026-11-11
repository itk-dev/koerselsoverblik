# Kørselsoverblik

A web application that collects data about citizen transport and shows whether it is getting
better or worse over time. All figures are calculated the same way every time, from the same
data, for any period.

Driving data comes from extracts from the planning system, uploaded as Excel files. Daily counts
of mails, complaints and phone calls are entered by hand until integrations are in place.

The user interface is in Danish.

## Contents

- [Features](#features)
- [Key figures](#key-figures)
- [Roles](#roles)
- [Requirements](#requirements)
- [Getting started](#getting-started)
- [Configuration](#configuration)
- [Usage](#usage)
- [Data and privacy](#data-and-privacy)
- [Development](#development)
- [Deployment](#deployment)
- [Documentation](#documentation)

## Features

- **Overview:** eight key figures for a chosen period, each with the change from a comparison
  period and a 12-month trend.
  - Punctuality over 12 months, against the target.
  - The figures per supplier, side by side.
- **Rettidighed (punctuality):** the four punctuality measures of a month against the service
  target: pickups and drop-offs, for each citizen and for each route run.
  - Each measure has its tone, the change from the previous month, the distance to the target
    and a 12-month sparkline.
  - A 12-month chart of the four measures, and the late last drop-offs in delay bands.
  - The figures for each supplier, and the ten routes with the lowest punctuality.
  - Links to the previous and the next month.
- **Filters:**
  - period: day, week, month or year, with previous/next navigation
  - comparison: previous period, or the same period last year
  - supplier
  - The filters live in the URL, so a view can be shared as a link.
- **Daily counts:** enter the number of mails, complaints and phone calls per day in a week grid.
- **Administration:**
  - **Upload driving data:** upload a driving data extract (`.xlsx`) for a date range.
    - The upload replaces all stored driving data in the date range. Rows that are not in a
      corrected file are removed, and a date with no rows in the file becomes empty.
    - A preview shows the changes. The data changes only when you confirm.
    - The figures for the replaced dates are recalculated.
    - The page lists the uploaded dates. You can clear the driving data for a date, for example
      after a wrong upload.
    - Columns are read through an [import mapping](#import-mappings), so a changed column name,
      code or format needs no code change.
  - **Suppliers and sub-agreements:** the suppliers, and which supplier drives each
    sub-agreement.
- **Login:** single sign-on through the identity provider (OIDC). Every page requires a role.

## Key figures

Every figure is calculated from summed counts for the selected period. Ratios are never
averages of daily percentages.

**Terms:**

- **Route:** a vehicle schedule in the planning system, for example `AB-CD-01`.
- **Sub-agreement:** the part of a route name before the last number, for example `AB-CD`. It has
  one supplier.
- **Route run:** one chain of pickups and drop-offs on a route, from the first citizen getting in
  until the vehicle is empty again. A route can have several runs a day. The planning system
  gives each run of a route a block number.

| Figure | Definition | Source |
| --- | --- | --- |
| Empty running (%) | Share of driven kilometres with no citizen in the vehicle | Driving data |
| Trips | Passenger trips: one citizen's trip from pickup to drop-off. Includes cancelled trips | Driving data |
| Citizens | Distinct citizens in the period, counted on the person id of the planning system | Driving data |
| Solo routes | Route runs where no more than one citizen was in the vehicle at any time. Shown with the share of all route runs | Driving data |
| Punctuality (%) | Share of the measured stops that were within the tolerance of the planned time. Four measures: all pickups, all drop-offs, the first pickup of each route run and the last drop-off of each route run. A stop is measured when the driver registered it as completed. Cancelled stops and stops with no registration are excluded | Driving data |
| Complaints | Complaints received. Shown with complaints per 1,000 trips | Manual entry |
| Mails | Citizen enquiries received by mail | Manual entry |
| Phone calls | Phone calls received | Manual entry |

**NOTE:**

- When the selected period has not ended yet, the comparison period is cut to the same number
  of days. Otherwise "fewer trips than last month" would always be true in the middle of a month.
- The supplier filter applies to the driving figures only. Mails, complaints and phone calls are
  counted for the whole office.

Every figure has a **?** in the interface that explains what is counted and where it comes from.

## Roles

Each part of the application has its own permission role:

| Role | Can |
| --- | --- |
| `ROLE_VIEW_OVERVIEW` | View the overview |
| `ROLE_MANAGE_DRIVING_DATA` | Upload driving data, and clear the driving data for a date |
| `ROLE_EDIT_DAILY_COUNTS` | Enter daily counts |
| `ROLE_MANAGE_SUPPLIERS` | Manage suppliers, sub-agreements and the supplier of each sub-agreement |

`ROLE_ADMIN` gives all the permission roles above. For now, give users `ROLE_ADMIN`. The
permission roles let us give narrower access later without code changes.

The identity provider gives the roles. It sends them in a claim (`OIDC_ROLE_CLAIM`), and the
application copies them to the user at each login. Values that are not `ROLE_ADMIN` or a
permission role are ignored. A user with no known role can sign in, but cannot open any page.

Every page requires a permission role. Anonymous users are sent to the identity provider.

## Requirements

- Docker and Docker Compose
- [itkdev-docker-compose](https://github.com/itk-dev/devops_itkdev-docker)
- [go-task](https://taskfile.dev) (`brew install go-task`)

## Getting started

1. Clone the repository:

   ```shell
   git clone https://github.com/itk-dev/koerselsoverblik.git
   cd koerselsoverblik
   ```

2. Create `.env.local` with the values from [Configuration](#configuration).

3. Set up the stack. This creates the `frontend` network, pulls the images, starts the
   containers, installs the Composer dependencies and runs the migrations:

   ```shell
   task setup
   ```

   The stack includes `idp`, a local OIDC mock service
   ([`oidc-provider-mock`](https://github.com/geigerzaehler/oidc-provider-mock)) at
   <http://idp.koerselsoverblik.local.itkdev.dk>. It has one test user with `ROLE_ADMIN`, one
   test user for each permission role, and `user` with no role. The users are in
   `docker-compose.override.yml`.

   The repository tracks `docker-compose.override.yml`, because it holds the `idp` service. Do not
   put personal changes in it.

4. Open the site at <https://koerselsoverblik.local.itkdev.dk>:

   ```shell
   itkdev-docker-compose open
   ```

5. Sign in as one of the test users of the mock service. Click "Authorize as admin" for all
   roles.

   The mock creates new signing keys when its container restarts. If a login then fails with
   `"kid" invalid`, clear the cache:

   ```shell
   task console -- cache:pool:clear cache.app
   ```

Run `task` to list every available task.

## Configuration

Set these in `.env.local`:

| Variable | Description | Default |
| --- | --- | --- |
| `OIDC_METADATA_URL` | The identity provider's discovery URL (`.well-known/openid-configuration`) | The local mock service |
| `OIDC_CLIENT_ID` | The client id of the application in the identity provider | The local mock client |
| `OIDC_CLIENT_SECRET` | The client secret of the application in the identity provider | The local mock secret |
| `OIDC_CLIENT_SECRET_EXPIRES_AT` | The date the client secret expires, for example `2027-01-31`. A login logs a warning 30 days before, and a critical error after the date. Empty means not monitored, and each login logs an error | Empty |
| `OIDC_REDIRECT_URI` | The callback URL of the application, `https://<HOST>/openid-connect/generic`, registered in the identity provider | The local callback URL |
| `OIDC_ROLE_CLAIM` | The claim that holds the user's roles | `roles` |
| `OIDC_ALLOW_HTTP` | Set to `true` to allow an identity provider on HTTP. Only the local mock service needs it, and `.env.dev` sets it | `false` |
| `TRUSTED_PROXIES` | The proxies whose `X-Forwarded-*` headers are trusted, so that URLs use https behind Traefik | `127.0.0.1,REMOTE_ADDR` |
| `APP_PUNCTUALITY_TOLERANCE_MINUTES` | Minutes a pickup or a drop-off may be late and still count as on time | `5` |
| `APP_PUNCTUALITY_TARGET` | Target for all four punctuality measures, in percent. Drawn as a line on the chart | `90` |
| `APP_DRIVING_DATA_MAPPING` | Name of the [import mapping](#import-mappings) used for uploads | `default` |
| `APP_DRIVING_DATA_MAX_DAYS` | The maximum number of days in the date range of one upload. The users upload one day or one week | `7` |
| `APP_DRIVING_DATA_MAX_FILE_SIZE` | The maximum size in bytes of an extract | `20971520` (20 MiB) |
| `APP_DRIVING_DATA_MAX_UNCOMPRESSED_SIZE` | The maximum total size in bytes of the files in the xlsx archive, when they are not compressed. The parser reads all the files before the first row, so this limit also controls the time of an upload. A week of driving data that OpenSpout writes is near 50 MiB | `104857600` (100 MiB) |
| `APP_DRIVING_DATA_MAX_ROWS` | The maximum row number in the sheet of an extract | `50000` |
| `APP_DRIVING_DATA_MAX_COLUMNS` | The maximum number of columns in a row of an extract | `200` |
| `APP_FIXTURES_PUNCTUALITY_START` | Development only. The share in percent of the runs with an on-time last drop-off in the first month of the [development dataset](#fixtures) | `65` |
| `APP_FIXTURES_PUNCTUALITY_TREND` | Development only. The change of the punctuality of the development dataset in percentage points for each month. A negative value makes it worse | `2.5` |

The `OIDC_*` defaults in `.env` point to the local mock service. On a server, set the values
from the real identity provider.

## Usage

### Upload driving data

1. Go to **Administration → Upload driving data** (requires `ROLE_MANAGE_DRIVING_DATA`).
2. Choose a driving data extract (`.xlsx`).
3. Enter the first and the last date of the export, and upload the file.
4. Check the preview. It shows:
   - the date range
   - how many rows will be deleted, inserted and rejected, with a reason for each rejected row
   - the dates in the range that have no rows in the file, and become empty
5. Confirm, or cancel. Nothing changes before you confirm. If the file has no rows in the date
   range, the preview has no confirm button.
6. The result page shows the replaced date range and how many rows were deleted, inserted and
   rejected.

An upload waits for the confirmation for one hour. After that, upload the file again. If the
file cannot be read, for example because a column is missing, the page shows the problem and
nothing changes.

The upload replaces all stored driving data in the date range. A date in the range with no rows
in the file becomes empty. A row with a date outside the range is rejected. Uploading the same
file again for the same range changes nothing. Uploading a corrected file gives exactly the rows
of that file for the range.

The date range can be at most `APP_DRIVING_DATA_MAX_DAYS` days. Upload one day or one week.

**CAUTION:**

- Enter the date range of the export. A range that is longer than the export empties the extra
  dates.
- Upload whole days only. A file that holds only part of a day removes the other rows of that
  day.

An upload cannot be undone. To correct a wrong upload, upload the correct file for the same
range, or [clear the driving data](#clear-driving-data) for the dates.

From the command line:

```shell
task console -- app:driving-data:import <PATH_TO_FILE> <FROM_DATE> <TO_DATE> [--mapping=<MAPPING_NAME>]
```

The command shows the same preview, and asks for confirmation unless `--no-interaction` is
given. The default answer is no. The dates have the format `YYYY-MM-DD`. The default mapping is
the value of `APP_DRIVING_DATA_MAPPING`. A file with no stop rows in the range is not imported,
because the import would only delete data. The command does not delete the file.

### Clear driving data

1. Go to **Administration → Upload driving data** (requires `ROLE_MANAGE_DRIVING_DATA`).
2. Find the date in the list of uploaded dates. Use the previous and next month links.
3. Check the number of stop rows and route runs for the date.
4. Select **Clear**, and confirm.

This deletes the uploaded driving data and the calculated figures for the date. The daily
counts of mails, complaints and phone calls do not change. You cannot undo this.

To clear a date range, use the command line:

```shell
task console -- app:driving-data:clear <FROM_DATE> <TO_DATE>
```

### Import mappings

An import mapping describes how a source's extract maps to the fields the application uses.
Mappings live in `config/import/driving_data/<MAPPING_NAME>.yaml`.

A mapping can change the column names, the code values and the formats of the fixed application
fields. For these changes in an extract, edit the mapping, or add a mapping for a new source.
These changes need code:

- a field that the extract does not have
- a field that is made from more than one column, or a column that holds more than one field
- a new application field

A mapping defines:

- **Sheet and header row:** where the data starts in the workbook. The rows above the header row
  are skipped.
- **Row id (optional):** the source column, or columns, that identify a row. If a row id occurs
  more than once in a file, the second and later rows are rejected.
- **Kept columns (optional, `keep_columns`):** the other columns that the copy of a row keeps.
  The copy holds the field columns, the row id columns and the kept columns, so that a later task
  can read the data again with no new upload. Never add a column with personal data, for example
  names, addresses, labels or comments
  ([ADR 006](docs/adr/006-keep-a-copy-of-each-imported-row.md)). A kept column that is not in a
  file is not an error.
- **Excluded columns (optional, `exclude_columns`):** the columns that the mapping leaves out on
  purpose, for example the personal data. The copy never keeps them, and the validate command
  does not show them as not read. An excluded column cannot also be a field column, a row id
  column or a kept column.
- A column name in the mapping cannot have a space at the start or the end, because the parser
  removes these spaces from the header names.
- **Fields:** each application field must have a source column. For each field, the mapping
  gives the column and how to read it:
  - type: `string`, `int`, `float`, `bool`, `date`, `time`, `datetime`
  - date format, only for a `date` or `datetime` field: `excel` (or no format) for an Excel
    serial number, or a
    [format string](https://www.php.net/manual/en/datetimeimmutable.createfromformat.php) such
    as `Y-m-d` for text. A date cell is accepted with all formats, because Excel can change the
    text of a date to a date cell when a user saves the file.
  - a `time` field is an Excel cell with a duration format, such as `[hh]:mm:ss`. A cell with
    an ordinary time format, such as `hh:mm`, is rejected. The time is a wall-clock time on
    `service_date`. A time of 24:00 or more is on the next day: 25:10 is 01:10 on the day after
    `service_date`.
  - all times are wall-clock times in the time zone of PHP and MariaDB. The import stores them
    with no conversion. See [Times](docs/architecture.md#times).
  - value maps for codes, only for a `string` field, for example which status values mean
    "completed"
  - for a `bool` field: the values that mean true and the values that mean false
  - whether the field is required

Each field has a fixed type, because the calculations use the values. Some fields must be
required, because each stop row needs them:

| Field | Type | Required |
| --- | --- | --- |
| `route` | `string` | Yes |
| `person_id` | `string` | Optional |
| `service_date` | `date` or `datetime` | Yes |
| `run` | `int` | Yes |
| `stop_type` | `string`, with a value map | Yes |
| `stop_status` | `string`, with a value map | Yes |
| `passengers_after` | `int` | Optional |
| `next_distance_km` | `float` | Optional |
| `next_duration_min` | `float` | Optional |
| `planned_time` | `time` or `datetime` | Yes |
| `actual_time` | `time` or `datetime` | Optional |
| `planned_solo` | `bool` | Optional |

A mapping with a `time` field must have a `service_date` with the type `date`.

`stop_type` and `stop_status` must have a value map. The values of the map must be application
values: `pickup` or `drop_off` for `stop_type`, and `completed`, `cancelled` or `pending` for
`stop_status`.

The import rejects a row with a value that a required field cannot read, for example a code that
is not in a value map, or a `bool` value that is in neither list. In an optional field, such a
value gives an empty value and a warning, and the row is imported. The copy of the row keeps the
value. A reason or a warning gives the row number and the field, and never the value.

```yaml
# config/import/driving_data/<MAPPING_NAME>.yaml
sheet: 0
header_row: 1
row_id: [<ROW_ID_COLUMN>]   # optional
keep_columns: [<COLUMN_NAME>, <COLUMN_NAME>]   # optional
exclude_columns: [<COLUMN_NAME>, <COLUMN_NAME>]   # optional
fields:
  route:             { column: <COLUMN_NAME>, type: string, required: true }
  person_id:         { column: <COLUMN_NAME>, type: string, required: true }
  service_date:      { column: <COLUMN_NAME>, type: date, format: Y-m-d, required: true }
  run:               { column: <COLUMN_NAME>, type: int, required: true }
  stop_type:         { column: <COLUMN_NAME>, type: string, required: true, map: { <PICKUP_VALUE>: pickup, <DROP_OFF_VALUE>: drop_off } }
  stop_status:       { column: <COLUMN_NAME>, type: string, required: true, map: { <COMPLETED_VALUE>: completed, <CANCELLED_VALUE>: cancelled, <PENDING_VALUE>: pending } }
  passengers_after:  { column: <COLUMN_NAME>, type: int }
  next_distance_km:  { column: <COLUMN_NAME>, type: float }
  next_duration_min: { column: <COLUMN_NAME>, type: float }
  planned_time:      { column: <COLUMN_NAME>, type: time, required: true }
  actual_time:       { column: <COLUMN_NAME>, type: datetime, format: excel }
  planned_solo:      { column: <COLUMN_NAME>, type: bool, true_values: [<SOLO_VALUE>], false_values: [<SHARED_VALUE>] }
```

The parser reads the rows of an extract through the mapping. It rejects a row with a value that
does not agree with the mapping, with the row number and a reason. The reason never contains a
value from the extract. It rejects the whole file if the sheet, the header row or a column is
missing.

Before the first row, the parser checks the whole file. It rejects a file above one of the
`APP_DRIVING_DATA_MAX_*` limits in [Configuration](#configuration). It also rejects a file with
more than 1000 files in the archive, a file in the archive that it cannot read, and a file with a
`DOCTYPE`. Then it gives no rows.

The `default` mapping reads the extract of the planning system. See
[the reference extract](docs/architecture.md#the-reference-extract).

Validate a mapping against a sample file before using it:

```shell
task console -- app:driving-data:mapping:validate <MAPPING_NAME> <PATH_TO_FILE> [--rows=<NUMBER>]
```

The command changes no data. It shows:

- the missing columns of the mapping, if there are any
- the number of stop rows, rejected rows and warnings, the first and the last date of the stop
  rows, and the number of dates with stop rows
- the rejected rows and the warnings: the count and the first row numbers for each field and
  reason. It never shows a value of a rejected row or a warning
- the kept columns and the excluded columns that are not in the file, and the columns of the file
  that the mapping does not read. Check a column that is not read for personal data, and add it
  to `keep_columns` or to `exclude_columns`. An excluded column that is not in the file can have
  a new name
- a preview of the first stop rows as the application reads them. `--rows` sets the number of
  rows (default 5). The times are wall-clock times, as in the file. The person id shows as
  `(hidden)`. The row key also shows as `(hidden)` when a `row_id` column is the `person_id`
  column

The command does not check a date range. An upload checks the dates against the range that the
user selects.

The command exits with `0` when all rows are valid, also when there are warnings. It exits with
`1` when a column of a field or a row id is missing, a row is rejected, the file has no stop
rows, or the file is not valid. It exits with `2` when the mapping is not valid or `--rows` is
below 1. A mapping with errors is rejected when the application starts.

### Map sub-agreements to suppliers

The driving data extract does not say which supplier drives a route. A route name such as
`AB-CD-01` or `TEST_01` starts with its sub-agreement (`AB-CD`, `TEST`): the part before the last
`-` or `_` and the digits after it. Each sub-agreement has one supplier. The supplier applies to
all its routes on all dates.

To give a sub-agreement a supplier:

1. Go to **Leverandører og delaftaler** (requires `ROLE_MANAGE_SUPPLIERS`).
2. Create the suppliers on the **Leverandører** tab.
3. On the **Delaftaler** tab, open a sub-agreement, or create it.
4. Click **Skift leverandør**.
5. Select the supplier, and click **Gem**.

The import creates a sub-agreement with no supplier. The routes of a sub-agreement with no
supplier count as "Unknown". Sub-agreements with no supplier are listed at the top.

When you change the supplier of a sub-agreement, the figures for all dates of its routes change
to the new supplier. A supplier with sub-agreements cannot be deleted.

### Enter daily counts

1. Go to **Daily counts** (requires `ROLE_EDIT_DAILY_COUNTS`).
2. Pick a week and fill in mails, complaints and phone calls for each day.
3. Save. Entering a day again overwrites the earlier numbers.

### Manage users

Users and roles are managed in the identity provider:

- To give a person access, assign them to the application in the identity provider.
- To change the roles of a user, change the value of the role claim in the identity provider.
  The change applies at the user's next login.

A user is created in the application at the first login. The application has no user list.

### Authentication log

Each login is written to `var/log/openid_connect_audit.<APP_ENV>.log`, for example
`var/log/openid_connect_audit.prod.log`:

- A successful login, with the email, the roles and the time. For example:
  `user foobar@example.com logged in with roles [ROLE_ADMIN] at 2026-10-28T12:00:00+01:00`.
- A login that the identity provider approved but the application rejected, with the IP address
  and the reason. A login that fails in the identity provider does not come back to the
  application, so it is not in this log.

The log holds personal data (emails and IP addresses). Its retention follows the staff data
decision in [ADR 004](docs/adr/004-personal-data-in-driving-data.md).

## Data and privacy

- The stop rows keep the person id of the planning system. It is used only to count distinct
  citizens, and it is never shown. See
  [ADR 004](docs/adr/004-personal-data-in-driving-data.md) (proposed).
- Names, addresses and other personal details in the extract are not imported. The columns with
  free text and labels are not imported either.
- The import keeps a copy of each row with the other columns of the extract, so that the data can
  be read again with no new upload. The copy has no names, addresses, free text or labels. See
  [ADR 006](docs/adr/006-keep-a-copy-of-each-imported-row.md) (proposed).
- Nothing is stored or shown at driver level.
- Uploaded files are kept outside the public folder until you confirm or cancel the upload. A
  scheduled job deletes the files that are older than one hour. An upload that is not confirmed
  in that time expires.

## Development

### Tests

The project is developed test first, with **100 % line coverage** and **mutation testing**.

```shell
# All tests
task test

# Unit tests only (fast)
task test:unit

# A single file
task test:file -- tests/Unit/Report/PeriodRangeFactoryTest.php

# Fail if line coverage is below 100 %, as CI does
task test:coverage:check

# Mutation testing with Infection, on lines changed compared with develop
task test:mutation

# Mutation testing on the whole code base, as the release-branch run does
task test:mutation:full
```

- **Test suites:** tests live in `tests/Unit`, `tests/Integration` and `tests/Functional`.
- **Database:** integration and functional tests run against a MariaDB test database. Each test runs
  in a transaction that is rolled back.
- **Fixture files:** the test extracts are made from YAML scenarios in `tests/fixtures/driving_data/`.
  The data is invented. Never commit real extracts.
- **Functional tests:** they assert behaviour, not markup. Use text and `data-test` attributes, so
  that a layout change does not break them.
- **Coverage:** PHPUnit measures coverage with Xdebug, because the phpfpm image has no pcov. Do
  not use `@codeCoverageIgnore`. The only exclusions are `src/Kernel.php` and `migrations/`.
- **Mutation thresholds:** Infection requires a covered MSI of 100 % for `src/Report` and
  `src/Import`, and 80 % for the rest of the mutated code. Controllers, form types and Twig
  components are not mutated; the functional tests cover them. `DevDatasetGenerator` and the
  fixtures command are not mutated, because their mutants change only invented data. The extract
  writer and the scenario loader are mutated, because the parser tests trust their files.
  The HTML report is written to `var/infection/`.
- **How the two thresholds work:** Infection has one threshold for each run, so
  `infection.json5` sets 80 % for all mutated code. Then `bin/mutation-core-check.php` reads the
  JSON log and fails if a mutant escaped or timed out in `src/Report` or `src/Import`. It also
  reads the text log, and fails if Infection skipped a mutant there, because a skipped mutant is
  not tested.
- **Threads:** Infection runs 4 mutants at the same time on all computers, so the result does not
  depend on the number of cores. To use a different number, set `TASK_INFECTION_THREADS`, for
  example `TASK_INFECTION_THREADS=2 task test:mutation`. Do not give `--threads` after `--`. The
  tasks then create too few test databases, and a database test in a mutant process fails, so
  Infection counts the mutant as killed.
- **Mutation databases:** Infection sets `TEST_TOKEN` to the thread number in each mutant
  process, and the name of the test database ends with it. So each thread has its own database,
  `db_test1` to `db_test4`. The mutation tasks create and migrate these databases before the run.
  To run one test as a mutant process does, set the token, for example
  `docker compose run --rm --env TEST_TOKEN=1 phpfpm php vendor/bin/phpunit <TEST_FILE>`.
- **Equivalent mutants:** list each one with a reason in `infection.json5`. The pull request
  review checks the reason.
- **Timeouts:** a mutant that times out in `src/Report` or `src/Import` fails the run, because
  nobody knows if a test kills it. In the rest of the code, a timeout counts as detected, as
  Infection counts it, and the gate check shows it as a warning. Each mutant has 60 seconds. The
  mutant processes run with Xdebug and share the CPU and the database server, so the database
  tests are slow. Thus Infection runs only the tests that cover
  the mutated line (`--only-covering-test-cases`), not all tests of their classes. Infection
  skips a mutant if all its tests took more than 60 seconds in the initial run.
- **When mutation testing runs:** pull requests that do not go to `main` run it on the lines that
  changed since their base branch. A full run over the whole code base runs on `release/*` and
  `hotfix/*` branches and is required before a release is merged to `main`.
- **Logic placement:** keep logic in plain PHP classes and keep controllers and forms thin, so most
  tests are fast unit tests.
- **Spikes:** throwaway experiments go on `spike/*` branches and are never merged.

### Coding standards and code analysis

```shell
task coding-standards-check
task coding-standards-apply

# PHPStan at max level
task code-analysis

# Rector
task rector:check
task rector:apply
```

`task prepare-code` applies Rector and runs the coding standards, code analysis, tests with the coverage check, and
mutation testing on changed lines. Run it before pushing. GitHub Actions runs the same checks on every pull
request.

### Fixtures

```shell
task fixtures:load
```

This loads:

- suppliers, sub-agreements and routes
- 13 months of generated driving data, imported through the real import
- daily counts

The fixtures do not create users. Sign in with the test users of the local mock service.

To get extract files that you can upload by hand:

```shell
task fixtures:extracts
```

This writes the test scenarios and one file for each ISO week of the last 13 months to
`var/fixtures/driving_data/`. A week file has the name of its Monday, for example
`dev-2026-09-14.xlsx`.

The data is invented, but it has the volume and the patterns of the school transport:

- two groups of sub-agreements, with route names in the formats `<LETTERS>-<LETTERS>-<NN>` and
  `<Name>_<NN>`
- the same citizens on a route each day, and a place name at the institution end of a trip
- a full volume on a school day, a low volume in the Aarhus school holidays, and only booked
  trips at the weekend
- route names that are not routes, as in the real data: a misspelled booked-trips route, a name
  with no separator, and notes

The punctuality does not come from the real data. Set it with `APP_FIXTURES_PUNCTUALITY_START`
and `APP_FIXTURES_PUNCTUALITY_TREND` in `.env.local`, or for one run:

```shell
task fixtures:extracts -- --punctuality-start=80 --punctuality-trend=-1.5
```

To import the 13 months into the local database:

```shell
task fixtures:import
```

This writes the files, and imports each week file for its Monday to its Sunday with
`app:driving-data:import --no-interaction --no-debug`. Then `app:fixtures:suppliers` gives the
sub-agreements the invented suppliers "Vognmand A" to "Vognmand D". A second run replaces the
same weeks. It does not import the test scenarios. The import takes some minutes.

**CAUTION:** The import replaces all driving data in the weeks of the files, also real data.

Each week file has 7 days. If `APP_DRIVING_DATA_MAX_DAYS` is less than 7, the upload rejects
these files.

### Diagrams

The architecture diagrams are Mermaid code blocks in [`docs/architecture.md`](docs/architecture.md).
GitHub renders them. No render step is necessary.

### Branching and releases

Development happens on `develop`:

1. Create a feature branch from `develop`:

   ```shell
   git switch develop
   git pull
   git switch -c feature/issue-<ISSUE_NUMBER>-<SHORT_DESCRIPTION>
   ```

2. Open a pull request to `develop`. Use a Conventional Commits title, and add
   `Closes #<ISSUE_NUMBER>` to the description. Add an entry to `CHANGELOG.md` under
   "Unreleased".

To make a release:

1. Cut a release branch from `develop`:

   ```shell
   git switch -c release/<VERSION> develop
   git push -u origin release/<VERSION>
   ```

   Every push to the release branch deploys to staging and runs the full mutation test.
2. Fix any problems found in testing on the release branch.
3. Open a pull request from the release branch to `main`, and merge it once the checks pass.
4. Create a GitHub release `<VERSION>` on `main`. This deploys to production.
5. Merge `main` back into `develop`.

Hotfixes branch from `main` as `hotfix/<VERSION>` and follow steps 3–5.

## Deployment

Deployment runs on [Woodpecker](https://woodpecker.itkdev.dk):

| Pipeline | Trigger | Target |
| --- | --- | --- |
| `.woodpecker/stg.yml` | Push to a `release/*` or `hotfix/*` branch | Staging |
| `.woodpecker/prod.yml` | A GitHub release | Production |

Each pipeline updates the code on the server and runs:

```shell
itkdev-docker-compose-server up -d --force-recreate
itkdev-docker-compose-server exec phpfpm composer install --no-dev -o --classmap-authoritative
itkdev-docker-compose-server exec phpfpm bin/console doctrine:migrations:migrate --no-interaction
itkdev-docker-compose-server exec phpfpm bin/console cache:clear
```

To set up a new server:

1. Make sure that PHP and MariaDB use the time zone of the driving data, `Europe/Copenhagen`. The
   application supports one time zone only, and does not check it yet
   ([ADR 005](docs/adr/005-dates-and-times-in-one-time-zone.md)):
   - `date.timezone` in `php.ini`. The `itkdev/php8.5-fpm` image sets it from `PHP_TIMEZONE`.
   - `@@global.time_zone` in MariaDB, or the time zone of the host when the value is `SYSTEM`.
1. Set these in `.env.local` (staging included):
   - `APP_ENV=prod`
   - `APP_SECRET`
   - the `OIDC_*` settings from the identity provider
   - the database settings
1. Make sure that the deployment reads `docker-compose.server.override.yml`. It sets the upload
   limits of PHP and nginx (25 MB) above `APP_DRIVING_DATA_MAX_FILE_SIZE`. The template file
   `docker-compose.server.yml` keeps the defaults of the template.

   The upload imports the file in the request. The server keeps the PHP limits of the template:
   30 seconds and 128 MB. These limits are not measured with a file at the row limit
   (`APP_DRIVING_DATA_MAX_ROWS`). If a large upload fails with an error 500, the import rolled
   back, and no data changed. Then raise `PHP_MAX_EXECUTION_TIME` and `PHP_MEMORY_LIMIT` in
   `docker-compose.server.override.yml`.
1. Deploy.
1. Add a cron job that deletes the upload files that nobody confirmed. Run it every 15 minutes:

   ```shell
   */15 * * * * cd <PATH_TO_SITE> && itkdev-docker-compose-server exec -T phpfpm bin/console app:driving-data:purge-uploads --quiet
   ```

1. In the identity provider, give the first administrator `ROLE_ADMIN` in the role claim.
1. Make sure that the administrator can sign in.

## Documentation

- [Architecture](docs/architecture.md)
- [Architecture decision records](docs/adr/)
- [Plans](docs/plans/README.md): the features that remain, and a plan for each feature
- [Changelog](CHANGELOG.md)
