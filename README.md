# Passenger Transport Performance

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
- **Filters:**
  - period: day, week, month or year, with previous/next navigation
  - comparison: previous period, or the same period last year
  - supplier
  - The filters live in the URL, so a view can be shared as a link.
- **Upload driving data:** upload a driving data extract (`.xlsx`).
  - Each date in the file replaces all stored driving data for that date. Rows that are not in
    a corrected file are removed.
  - The figures for the replaced dates are recalculated.
- **Clear driving data:** delete the driving data for a date range, for example after a wrong
  upload.
  - Columns are read through an [import mapping](#import-mappings), so another source can be
    added with a new mapping file instead of code changes.
- **Daily counts:** enter the number of mails, complaints and phone calls per day in a week grid.
- **Administration:** users, suppliers, and which supplier drives each route from which date.
- **Login:** username and password. Every page requires a role.

## Key figures

Every figure is calculated from summed counts for the selected period. Ratios are never
averages of daily percentages.

**Terms:**

- **Route:** a vehicle schedule in the planning system, assigned to a supplier for a date range.
- **Route run:** one chain of pickups and drop-offs on a route, from the first citizen getting in
  until the vehicle is empty again. A route can have several runs a day.

| Figure | Definition | Source |
| --- | --- | --- |
| Empty running (%) | Share of driven kilometres with no citizen in the vehicle | Driving data |
| Trips | Passenger trips: one citizen's trip from pickup to drop-off. Includes cancelled trips | Driving data |
| Citizens | Distinct citizens in the period, counted on the pseudonymised person id | Driving data |
| Solo routes | Route runs where no more than one citizen was in the vehicle at any time. Shown with the share of all route runs | Driving data |
| Punctuality (%) | Share of completed route runs whose last drop-off was within the tolerance of the planned time. Cancelled runs are excluded | Driving data |
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
| `ROLE_UPLOAD_DRIVING_DATA` | Upload driving data |
| `ROLE_CLEAR_DRIVING_DATA` | Clear the driving data for a date range |
| `ROLE_EDIT_DAILY_COUNTS` | Enter daily counts |
| `ROLE_MANAGE_SUPPLIERS` | Manage suppliers, routes and the route → supplier assignments |
| `ROLE_MANAGE_USERS` | Manage users |

`ROLE_ADMIN` gives all the permission roles above. For now, give users `ROLE_ADMIN`. The
permission roles let us give narrower access later without code changes.

Every page requires a permission role. Anonymous users are sent to the login page.

## Requirements

- Docker and Docker Compose
- [itkdev-docker-compose](https://github.com/itk-dev/devops_itkdev-docker)
- [go-task](https://taskfile.dev) (`brew install go-task`)

## Getting started

1. Clone the repository:

   ```shell
   git clone https://github.com/itk-dev/passenger-transport-performance.git
   cd passenger-transport-performance
   ```

2. Create `.env.local` with the values from [Configuration](#configuration).

3. Set up the stack. This creates the `frontend` network, pulls the images, starts the
   containers, installs the Composer dependencies and runs the migrations:

   ```shell
   task setup
   ```

4. Create the first user. You are asked for the name and password:

   ```shell
   task console -- app:user:create <USERNAME> --role=ROLE_ADMIN
   ```

5. Open the site at <https://passenger-transport-performance.local.itkdev.dk>:

   ```shell
   itkdev-docker-compose open
   ```

Run `task` to list every available task.

## Configuration

Set these in `.env.local`:

| Variable | Description | Default |
| --- | --- | --- |
| `APP_AUTH_MODE` | Login method: `password`, `none` or `oidc` (`oidc` is not yet implemented) | `password` |
| `APP_NO_LOGIN_ROLES` | Comma-separated roles given to everyone when `APP_AUTH_MODE=none` | `ROLE_VIEW_OVERVIEW` |
| `APP_ALLOW_NO_LOGIN_IN_PROD` | Set to `1` to allow `APP_AUTH_MODE=none` in `prod`. The app refuses to start otherwise | `0` |
| `APP_PERSON_ID_SECRET` | Secret used to pseudonymise person ids. Keep it stable: changing it breaks distinct counts across old and new imports. See [Data and privacy](#data-and-privacy) | – (required) |
| `APP_PUNCTUALITY_TOLERANCE_MINUTES` | Minutes a last drop-off may be late and still count as on time | `5` |
| `APP_PUNCTUALITY_TARGET` | Target for punctuality, in percent. Drawn as a line on the chart | `90` |
| `APP_DRIVING_DATA_MAPPING` | Name of the [import mapping](#import-mappings) used for uploads | `default` |

**WARNING:** `APP_AUTH_MODE=none` gives everyone the roles in `APP_NO_LOGIN_ROLES` without
logging in. Use it for local development only. For full rights in local development, set
`APP_NO_LOGIN_ROLES=ROLE_ADMIN` explicitly.

## Usage

### Upload driving data

1. Go to **Upload driving data** (requires `ROLE_UPLOAD_DRIVING_DATA`).
2. Choose a driving data extract (`.xlsx`) and upload it.
3. The result page shows the replaced dates and how many rows were deleted, inserted and
   rejected, with a reason for each rejected row.

Each date in the file replaces all stored driving data for that date. Uploading the same file
again changes nothing. Uploading a corrected file gives exactly the rows of that file for its
dates.

**CAUTION:** upload whole days only. A file that holds only part of a day removes the other rows
of that day.

An upload cannot be undone. To correct a wrong upload, upload the correct file for the same
dates, or [clear the driving data](#clear-driving-data) for the dates.

From the command line:

```shell
task console -- app:driving-data:import <PATH_TO_FILE> [--mapping=<MAPPING_NAME>]
```

### Clear driving data

1. Go to **Clear driving data** (requires `ROLE_CLEAR_DRIVING_DATA`).
2. Select the first and the last date.
3. Check the number of rows that will be deleted.
4. Confirm.

This deletes the uploaded driving data and the calculated figures for the dates. The daily
counts of mails, complaints and phone calls do not change. You cannot undo this.

From the command line:

```shell
task console -- app:driving-data:clear <FROM_DATE> <TO_DATE>
```

### Import mappings

An import mapping describes how a source's extract maps to the fields the application uses.
Mappings live in `config/import/driving_data/<MAPPING_NAME>.yaml`. To support a new source, or a
change in an existing extract, add or edit a mapping. No code changes are needed.

A mapping defines:

- **Sheet and header row:** where the data starts in the workbook.
- **Row id (optional):** the column, or columns, that identify a row. If a row id occurs more
  than once in a file, the second and later rows are rejected.
- **Fields:** for each application field, the source column and how to read it:
  - type: `string`, `int`, `float`, `bool`, `datetime`
  - date format: for example an Excel serial date or a format string
  - value maps for codes, for example which status values mean "completed" or which values mean
    "yes"
  - whether the field is required

```yaml
# config/import/driving_data/<MAPPING_NAME>.yaml
sheet: 0
header_row: 1
row_id: [<ROW_ID_COLUMN>]   # optional
fields:
  route:            { column: <COLUMN_NAME>, type: string, required: true }
  person_id:        { column: <COLUMN_NAME>, type: string, required: true }
  stop_type:        { column: <COLUMN_NAME>, type: string, map: { <PICKUP_VALUE>: pickup, <DROP_OFF_VALUE>: drop_off } }
  stop_status:      { column: <COLUMN_NAME>, type: string, map: { <COMPLETED_VALUE>: completed, <CANCELLED_VALUE>: cancelled } }
  route_end:        { column: <COLUMN_NAME>, type: bool, true_values: [<YES_VALUE>] }
  passengers_after: { column: <COLUMN_NAME>, type: int }
  next_distance_km: { column: <COLUMN_NAME>, type: float }
  next_duration_min: { column: <COLUMN_NAME>, type: float }
  planned_time:     { column: <COLUMN_NAME>, type: datetime, format: excel }
  actual_time:      { column: <COLUMN_NAME>, type: datetime, format: excel }
  planned_solo:     { column: <COLUMN_NAME>, type: bool, true_values: [<SOLO_VALUE>] }
```

Validate a mapping against a sample file before using it:

```shell
task console -- app:driving-data:mapping:validate <MAPPING_NAME> <PATH_TO_FILE>
```

This reports missing columns, values that do not match the mapping, and a preview of the first
rows as the application reads them. A mapping with errors is rejected when the application
starts.

### Map routes to suppliers

The driving data extract does not say which supplier drives a route. To assign them:

1. Go to **Administration → Routes** (requires `ROLE_MANAGE_SUPPLIERS`).
2. Assign each route to a supplier from a start date. Leave the end date empty.

When a route changes supplier:

1. Set the end date of the current assignment to the last day with the old supplier.
2. Add a new assignment with the new supplier from the next day.

The assignments of one route cannot overlap. The figures for the old supplier before the change
stay the same.

On a date with no assignment, a route counts as "Unknown". Routes with no current assignment are
listed at the top. When an assignment changes, the figures are recalculated for its dates only.

### Enter daily counts

1. Go to **Daily counts** (requires `ROLE_EDIT_DAILY_COUNTS`).
2. Pick a week and fill in mails, complaints and phone calls for each day.
3. Save. Entering a day again overwrites the earlier numbers.

### Manage users

Under **Administration → Users** (requires `ROLE_MANAGE_USERS`), or from the command line:

```shell
task console -- app:user:create <USERNAME> --role=<ROLE>
task console -- app:user:set-password <USERNAME>
task console -- app:user:set-roles <USERNAME> <ROLE> [<ROLE>...]
```

## Data and privacy

- Person ids from the driving data are never stored in clear text. They are pseudonymised with
  HMAC using `APP_PERSON_ID_SECRET`, and used only to count distinct citizens.
- Names, addresses and other personal details in the extract are not imported.
- Nothing is stored or shown at driver level.
- Uploaded files are deleted once they have been processed.

### The pseudonymisation key

If one attacker gets both the database and `APP_PERSON_ID_SECRET`, the pseudonymisation does
not protect the data. Keep the key apart from the database:

- Set the key in `.env.local`.
- Do not include `.env.local` in the backups of the database host.

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
- **Fixture files:** these are in `tests/fixtures/` and are anonymised. Never commit real extracts.
- **Mutation thresholds:** Infection requires a covered MSI of 100 % for `src/Report` and
  `src/Import`, and 80 % for the rest of the mutated code. Controllers, form types and Twig
  components are not mutated; the functional tests cover them. The HTML report is written to
  `var/infection/`.
- **When mutation testing runs:** feature pull requests to `develop` run it on changed lines only. A
  full run over the whole code base runs on `release/*` branches and is required before a release is
  merged to `main`.
- **Logic placement:** keep logic in plain PHP classes and keep controllers and forms thin, so most
  tests are fast unit tests.
- **Spikes:** throwaway experiments go on `spike/*` branches and are never merged.

### Coding standards and code analysis

```shell
task coding-standards-check
task coding-standards-apply

# PHPStan at max level
task code-analysis
```

`task prepare-code` runs the coding standards, code analysis, tests with the coverage check, and
mutation testing on changed lines. Run it before pushing. GitHub Actions runs the same checks on every pull
request.

### Fixtures

```shell
task fixtures:load
```

This loads:

- a user with `ROLE_ADMIN`, and a user for each permission role
- suppliers and routes
- an imported sample day
- daily counts

### Diagrams

The architecture diagrams are Mermaid files in `docs/diagrams/`. Render them to SVG with:

```shell
task docs:diagrams
```

This uses the `minlag/mermaid-cli` Docker image. CI fails if a rendered SVG is out of date.

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

1. Set these in `.env.local` (staging included):
   - `APP_ENV=prod`. The guard against `none` mode depends on it.
   - `APP_AUTH_MODE=password`
   - `APP_SECRET`
   - `APP_PERSON_ID_SECRET`
   - the database settings
2. Deploy.
3. Create the first admin:

   ```shell
   itkdev-docker-compose-server exec phpfpm bin/console app:user:create <USERNAME> --role=ROLE_ADMIN
   ```

## Documentation

- [Architecture](docs/architecture.md)
- [Architecture decision records](docs/adr/)
- [Plan](docs/plan.md) and [tasks](docs/tasks.md)
- [Changelog](CHANGELOG.md)
