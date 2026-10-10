# Plan: Passenger Transport Performance

## Context

There is no regular, consistent measurement of citizen transport. Annual figures are compiled
by hand. A concept and a static HTML/JS prototype exist:

- **Prototype:** an overview page, five theme pages and a management report, running on generated demo data.
- **Concept documentation:** data sources, calculation rules, themes and open questions.

This project builds the real application on that concept and prototype.

**Stack:**

- Symfony 8 on the ITK Dev `symfony-8` template (php8.4-fpm, nginx, MariaDB, Traefik, Taskfile)
- GitHub Actions for CI, Woodpecker for deployment
- Hand-built Symfony forms and Twig (no EasyAdmin)
- The prototype's styles and the itkaarhus design system, ported to Twig
- The UI is in Danish. Code and documentation are in English.

**Auth:**

- Configurable via `APP_AUTH_MODE=none|password|oidc`.
- Every route is guarded by a role.

---

# Development approach: test-driven, 100 % coverage, mutation testing

These rules apply to every step from step 4 onwards. Steps 1–3 are docs and planning only.

**TDD:**

- Each change starts with a failing test (red), then the minimal code to pass (green), then refactoring.
- No production code is written without a test that requires it.
- Commits follow the cycle, so the history shows test before code.

**Keeping TDD light:**

- **Thin glue.** Controllers, form types, commands and authenticators only wire things
  together. All logic lives in plain PHP classes (`Report/`, `Import/`, value objects), so most
  tests are fast unit tests. Only a few slow tests boot the kernel or hit the database.
- **Spikes are allowed.** When exploring something unknown, for example a new extract or a chart
  layout, write throwaway code on a `spike/*` branch without tests. Spikes are never merged. The
  kept implementation is then written test first.
- **UI tests assert behaviour, not markup.** Functional tests check what is shown (figures,
  labels, links, access per role), using text and `data-test` attributes. Changing the layout
  does not break them.
- **Fast feedback loop.** Coverage uses `pcov`, which is much faster than Xdebug. The unit suite
  runs on its own in seconds (`task test:unit`). Infection runs only on changed lines in PRs.

**Test layers** (PHPUnit):

- **Unit** (`tests/Unit`):
  - pure logic: `PeriodRangeFactory`, `KpiCalculator` maths, `Delta`, `Tone`
  - the import mapping loader and validation, the parser, upserter, `RouteBuilder`, `DailyStatsAggregator`
  - `FormatExtension`, the authenticators' `supports()`, value objects
- **Integration** (`tests/Integration`, `KernelTestCase` + MariaDB test DB, isolated with `dama/doctrine-test-bundle`):
  - repositories and the SUM and distinct-count queries
  - upsert by row id
  - console commands (`CommandTester`)
- **Functional** (`tests/Functional`, `WebTestCase`):
  - every route × role (200/403/redirect)
  - forms: valid submit, validation errors, CSRF
  - login, logout and rate limiting
  - upload end-to-end with the fixture file
  - the overview with filters
  - the route-guard test
- **Fixtures:**
  - Foundry factories for every entity.
  - An anonymised one-day extract in `tests/fixtures/`, with its expected totals worked out by hand.
  - Small hand-made extracts for edge cases: cancelled routes, unmapped routes, re-upload with changed rows, malformed files.

**100 % code coverage:**

- PHPUnit with `pcov`, installed in the phpfpm image for development and CI. Xdebug coverage mode is only a fallback.
- `phpunit.dist.xml` `<source>` covers all of `src/`. The only exclusions are `src/Kernel.php` and `migrations/`.
- `@codeCoverageIgnore` is not used.
- CI fails below 100 % line coverage (`coverage-check` on the Clover report). Branch coverage is reported.
- Twig templates are not measured by coverage, so the functional tests assert on rendered content.

**Mutation testing (Infection):**

- `infection/infection` with `infection.json5`.
  - It mutates `src/` using the PHPUnit suite with `--threads=max`.
  - **Not mutated:** `src/Controller`, `src/Form`, `src/Twig/Components` and `src/Kernel.php`. This glue is still covered (100 % lines) by the functional tests. Mutating it mostly produces equivalent mutants and slow runs.
  - Reports are written to `var/infection/`.
- **Thresholds, enforced in CI:**
  - `minCoveredMsi: 100` for `src/Report` and `src/Import`, where a wrong number is the worst bug.
  - `minCoveredMsi: 80` for the rest of the mutated code (`Security`, `Command`, `Twig` extensions, entities and repositories). Raise it when it is reached comfortably.
- Equivalent mutants are listed with a reason in `infection.json5` and reviewed in PRs.
- **When it runs:**
  - **On every feature PR to `develop`, for changed lines only** (`--git-diff-lines --git-diff-base=origin/develop`).
    - Fast, because only the mutants in the lines the PR touched are run.
    - This is the merge gate.
  - **Full run on release branches.**
    - `.github/workflows/mutation-full.yml` runs Infection over the whole code base on push to `release/*` or `hotfix/*`, and on their PRs to `main`.
    - It runs only when a release is being prepared, so no time is spent when nothing has changed.
    - It catches what changed-lines runs miss. A change in one class can let mutants survive in another, untouched class, for example when a test that used to kill them is changed or deleted.
    - It is a required status check on the release PR to `main`, so production is never released with surviving mutants above the thresholds.
    - The full HTML report is kept as a workflow artifact.
    - Survivors are fixed on the release branch before merging.
    - Staging deploys from the same push (Woodpecker) still happen, so testers aren't blocked while the run completes.
  - **Locally:** `task test:mutation:full` runs the same full run whenever a developer wants it, for example after a larger refactoring.

**Expected cost:** roughly 20–30 % extra time early on, falling as factories, fixtures and test helpers build up. This is an estimate, to be revisited after the first tasks.

**Taskfile tasks** (following the economics conventions):

- `task test`, `task test:unit` and `task test:file -- <FILE>`
- `task test:coverage` and `task test:coverage:check`, which fails if coverage is below 100 %
- `task test:mutation`: changed lines only, compared with `develop`
- `task test:mutation:full`: the whole code base, as the release-branch run does
- `task code-analysis`: PHPStan at max level
- `task coding-standards:check` and `task coding-standards:apply`
- `task prepare-code`: everything above, with mutation testing on changed lines

**CI** (GitHub Actions, next to the template workflows):

- **PR workflow:** coding standards, PHPStan, tests with the coverage gate, and mutation testing on changed lines.
- **Release workflow:** a full mutation run on push to `release/*` or `hotfix/*`, and on their PRs to `main` (a required check).

**Branching** (our usual flow):

- `develop` is the default branch, and all development starts from it.
- **Feature work:** feature branches (`feature/<ISSUE>-<SHORT_DESCRIPTION>`) are cut from `develop` and merged back through a PR. The PR workflow and changed-lines mutation testing gate the merge.
- **Release:**
  1. Cut a release branch (`release/<VERSION>`) from `develop`.
  2. Every push to it deploys to staging (Woodpecker) and runs the full mutation run.
  3. Fixes found in testing go on the release branch.
  4. The release branch is merged to `main` through a PR. The full mutation run is a required check.
- **Production:** a GitHub release (tag `<VERSION>`) on `main` deploys to production (Woodpecker).
- **Back-merge:** after the release, `main` is merged back into `develop`, so release fixes are not lost.
- **Hotfixes:** `hotfix/<VERSION>` is cut from `main`, follows the same path as a release (full run, PR to `main`, release), and is merged back into `develop`.
- `CHANGELOG.md` gets an entry per feature PR under "Unreleased". The release branch turns "Unreleased" into the version.
- **To set up before step 4:** create `develop` from `main` and make it the default branch. Protect `develop` and `main` with the required checks.
- A MariaDB service container for the integration and functional tests.

---

# Milestone 1 – first deliverable

**Scope:**

- **Docs and planning:**
  - README written up front, as if the project were implemented
  - architecture description with diagrams
  - the plan broken into tasks (`docs/tasks.md`)
- **Setup and access:**
  - project setup
  - username/password login (users are created with a command)
  - base layout and menu
- **Data in:**
  - upload of driving data (rows with the same id are overwritten)
  - manual entry of mails, complaints and phone calls per day
- **Views:**
  - empty running (%)
  - trips
  - citizens
  - solo routes
  - punctuality (%)
  - complaints
  - mails
  - phone calls

## 1. README first

Write `README.md` **as if the project had already been implemented** (README-driven
development). It is the spec for milestone 1 and is reviewed before any code is written.
Afterwards it is kept in sync whenever the implementation diverges.

**Contents:**

- **What it is:** the purpose, and the eight figures with short definitions
- **Features:** login and roles, upload with overwrite by row id, daily counts, filters
- **Getting started:** `task setup`, the first user, the local URL
- **Configuration:** auth mode, the pseudonymisation secret, the punctuality settings
- **Usage:** upload, mapping routes to suppliers, daily counts, users
- **Data and privacy**
- **Development:** tests, coverage, mutation testing, coding standards, fixtures, diagrams
- **Deployment:** Woodpecker
- **Links** to the other docs

## 2. Architecture and diagrams

Write `docs/architecture.md`, illustrated with Mermaid diagrams.

**Diagrams:**

- **System context:** users by role, the application, the driving data extract, and the later sources (telephony system, mailbox, case management system, identity provider).
- **Containers:** Traefik → nginx → phpfpm (Symfony) → MariaDB.
- **Components:**
  - controllers and forms
  - `Security`: authenticators per mode, role guards
  - `Import/DrivingData`: mapping (YAML) → parser → upserter → route builder → daily stats aggregator
  - `Report`: `PeriodRangeFactory`, `KpiCalculator`, `Delta`/`Tone`
  - Twig components
- **Data model:** an ER diagram of `User`, `Supplier`, `Route`, `ImportBatch`, `StopRow`, `RouteRun`, `DailyStats` and `DailyCount`.
- **Sequence: upload.** Form → `ImportBatch` → parse → upsert by row id → rebuild `RouteRun`/`DailyStats` for the affected dates → result page.
- **Sequence: overview request.** Authenticator by mode → `IsGranted` → `PeriodFilter` → `KpiCalculator` (current period, comparison period, 12 months) → Twig.
- **Auth modes:** a flowchart of how `APP_AUTH_MODE` selects the authenticator.

**Files and rendering:**

- Mermaid sources live in `docs/diagrams/*.mmd`. They are rendered to SVG with the mermaid-cli Docker image:

  ```shell
  docker run --rm -u "$(id -u):$(id -g)" -v "$PWD/docs/diagrams:/data" \
    minlag/mermaid-cli -i /data/<NAME>.mmd -o /data/<NAME>.svg
  ```

- `task docs:diagrams` renders every `.mmd`. CI re-renders them and fails if an SVG is out of date.
- `docs/architecture.md` embeds the SVGs and links to the sources.
- Key decisions become ADRs in `docs/adr/`:
  - `0001-stack.md`
  - `0002-auth-modes.md`
  - `0003-driving-data-import-mapping-and-overwrite.md`
  - `0004-pseudonymised-person-id.md`

## 3. Break the plan into tasks

Once the README and architecture are approved, break steps 4–10 into small, independently
deliverable tasks in `docs/tasks.md`. Each task becomes one branch and one PR.

**Each task has:**

- **ID and title**, e.g. `M1-11 Upsert stop rows by row id`
- **Step:** the plan step, and the README or architecture section it implements
- **Depends on:** the tasks that must be merged first
- **Tests first:** the failing tests that define the task
- **Acceptance criteria:** observable behaviour, taken from the README
- **Definition of done:**
  - tests green, 100 % coverage and the MSI thresholds met
  - PHPStan and coding standards pass
  - docs and diagrams updated if behaviour changed
  - a CHANGELOG entry

**Sizing:** each task fits in one PR that can be reviewed in one sitting (roughly ≤ 1 day). Split anything bigger.

**Ordering:**

1. Setup and the quality gates.
2. Security.
3. Features as a vertical slice, so an editor can upload a file and see one figure on the overview as early as possible.

**Provisional breakdown:**

| ID | Task | Step | Depends on |
|---|---|---|---|
| M1-01 | ITK `symfony-8` template, Symfony skeleton, MariaDB | 4 | – |
| M1-02 | Test tooling: PHPUnit suites, pcov, coverage gate, Infection, Taskfile tasks, PR and release CI workflows, smoke test | 4 | 01 |
| M1-03 | Diagram rendering task and CI check | 4 | 01 |
| M1-04 | Role hierarchy, deny-by-default `access_control`, route-guard test | 5 | 02 |
| M1-05 | `User` entity and the `app:user:*` commands | 5 | 04 |
| M1-06 | Password login: CSRF, rate limiting, remember-me, logout | 5 | 05 |
| M1-07 | `APP_AUTH_MODE` wiring and the `none` authenticator with its prod guard | 5 | 06 |
| M1-08 | Base layout, design system assets, menu by role, `FormatExtension` | 6 | 04 |
| M1-09 | `Supplier`/`Route` entities and admin forms | 7 | 08 |
| M1-10a | Import mapping: YAML format, `MappingLoader` with validation, `mapping:validate` command | 7 | 02 |
| M1-10b | Extract parser driven by the mapping: field types, dates, value maps, person id pseudonymisation | 7 | 10a |
| M1-11 | `StopRow` upsert by row id and `ImportBatch` | 7 | 10b |
| M1-12 | `RouteBuilder`: last drop-off, delay, peak passengers, cancellations | 7 | 11 |
| M1-13 | `DailyStatsAggregator`: rebuild the affected dates | 7 | 12 |
| M1-14 | Upload form, result page, `app:driving-data:import` | 7 | 13, 08 |
| M1-15 | `DailyCount` entity and the week grid form | 8 | 08 |
| M1-16 | `PeriodFilter` and `PeriodRangeFactory` | 9 | 02 |
| M1-17 | `KpiCalculator`, `Kpis`, `Delta`, `Tone` | 9 | 13, 15, 16 |
| M1-18 | Twig components: Tile, Sparkline, LineChart, Panel | 9 | 08 |
| M1-19 | Overview page: filters, 8 tiles, punctuality chart, supplier table | 9 | 17, 18 |
| M1-20 | Woodpecker staging deploy: pipeline, server compose, migrations, first admin | 10 | 19 |

## 4. Project setup (ITK Dev conventions)

1. Install the template:

   ```shell
   itkdev-docker-compose template:install symfony-8
   ```

   - Project name: `passenger-transport-performance`
   - Domain: `passenger-transport-performance.local.itkdev.dk`
2. Create the Symfony 8 skeleton inside phpfpm:
   - Run `composer create-project symfony/skeleton:"8.*"` into a temporary directory and copy it into `/app`.
   - Restore `COMPOSE_PROJECT_NAME`, `COMPOSE_DOMAIN` and `ITKDEV_TEMPLATE` at the top of `.env`.
   - Run `composer install`.
3. Packages:
   - `symfony/orm-pack`, with the MariaDB `DATABASE_URL`
   - `twig-bundle`, `asset-mapper`, `form`, `validator`, `security-bundle`, `translation`, `rate-limiter`, `twig/intl-extra`
   - `phpoffice/phpspreadsheet`, `symfony/ux-twig-component`
4. Design system:
   - Require `itk-dev/itkaarhus-design-system` from the vcs repository `https://github.com/itk-dev/itkaarhus-design-system`.
   - Map it in AssetMapper as `itkaarhus-ds`.
5. Dev tooling:
   - php-cs-fixer, twig-cs-fixer, PHPStan (symfony + doctrine, max level), PHPUnit
   - `rregeer/phpunit-coverage-check`
   - doctrine-fixtures, `zenstruck/foundry`, `dama/doctrine-test-bundle`
   - `infection/infection`, and pcov in the phpfpm image
   - Write the Taskfile from the economics conventions (`setup`, `compose`, `composer`, `phpfpm`, `test*`, `code-analysis`, `prepare-code`, `coding-standards:*`, `db:migrate`, `fixtures:load`, `docs:diagrams`).
   - **Smoke test first:** a failing functional test that `/` redirects to `/login`.
6. Docs:
   - Check `README.md` against the actual setup.
   - Add `CHANGELOG.md`.

## 5. Security: role guards and username/password login

**Roles** (hierarchy `ROLE_ADMIN` > `ROLE_EDITOR` > `ROLE_USER`):

- `ROLE_USER`: view the overview
- `ROLE_EDITOR`: upload driving data, enter daily counts
- `ROLE_ADMIN`: users, suppliers and routes

**Guarding:**

- Every controller has a class-level `#[IsGranted]`, with stricter rules per action where needed.
- `access_control` denies by default (`^/` → `ROLE_USER`). Only `/login` and assets are public.
- A test walks the router and fails if any application route lacks `IsGranted`.

**Mode wiring** (ready for `none` and `oidc`):

- One firewall. Each authenticator's `supports()` checks `APP_AUTH_MODE`.
- The default is `password`.
- Login routes for inactive modes return 404.

**`password` mode:**

- `User` entity: username, name, roles, password hash. Doctrine entity provider.
- Login form with CSRF, rate limiting, remember-me and logout.
- Commands:
  - `app:user:create <USERNAME> [--role=<ROLE>]` (prompts for name and password)
  - `app:user:set-password`
  - `app:user:set-roles`

**`none` mode** (development convenience):

- `NoLoginAuthenticator` signs every request in as a local user with the roles in `APP_NO_LOGIN_ROLES`.
- The app refuses to start in `prod` unless `APP_ALLOW_NO_LOGIN_IN_PROD=1`.

## 6. Base layout and menu

**Shell:**

- Port the prototype's shell to `templates/base.html.twig`:
  - side navigation
  - top bar with the filters and a user menu
  - mobile navigation drawer
  - an "about the figures" dialog
- Port the prototype's stylesheet to `assets/styles/app.css`. It uses design system tokens only.

**Menu:**

- Overview
- Upload driving data (editor)
- Daily counts (editor)
- Administration: users, suppliers, routes (admin)

Menu items are shown with `is_granted()`.

**Language and formatting:**

- Danish locale, with texts in `translations/messages+intl-icu.da.yaml`.
- `FormatExtension` provides: percent, percentage points, signed delta, kilometres, period labels, and "–" for missing values.

## 7. Upload of driving data

**Upload flow** (`ROLE_EDITOR`):

- An upload form creates an `ImportBatch`: file name, uploaded by, row counts (added/updated/rejected), errors.
- The file is processed **synchronously** in the request, and the result page is shown.
  - Move to async processing only if file sizes require it (see "Later milestones").
- CLI: `app:driving-data:import <PATH_TO_FILE> [--mapping=<MAPPING_NAME>]`.
- The uploaded file is deleted after processing.

**Import mapping:**

- How a source's columns map to the application's fields is described in a mapping file,
  `config/import/driving_data/<MAPPING_NAME>.yaml`, not in code. This lets us support another source,
  or a changed extract, by adding or editing a mapping.
- `APP_DRIVING_DATA_MAPPING` selects the mapping used for uploads. The default is `default`.
- **A mapping defines:**
  - **Location:** the sheet and the header row.
  - **Row id:** one or more columns. This is the overwrite key.
  - **Field mapping:** a column for each application field.
  - **Parsing per field:** type (`string`, `int`, `float`, `bool`, `datetime`), date format (Excel serial or a format string), value maps for codes (stop type, stop status, yes/no), and whether the field is required.
- **Implementation:**
  - `MappingLoader` reads the YAML and validates it with the Symfony Config component (`TreeBuilder`).
  - The app refuses to start if a mapping is invalid.
  - `StopRowParser` reads the workbook only through a `Mapping` value object. It never refers to column names directly.
- `app:driving-data:mapping:validate <MAPPING_NAME> <PATH_TO_FILE>` checks a sample file against a mapping. It reports missing columns and unmapped values, and previews the first parsed rows.
- **Tests:**
  - unit tests for the loader and validation, and for each field type and value map
  - parser tests run against a test mapping in `tests/fixtures/mappings/`

**Storage and overwrite:**

- `StopRow` holds one row per stop from the extract. The **unique key is the row id** defined by the mapping, together with the mapping name.
- On re-upload, an existing row id is **overwritten**, and new ids are inserted.
- Only the fields the calculations need are kept:
  - route, date, stop type (pickup/drop-off), stop status, end-of-route flag
  - passengers after the stop
  - distance and duration to the next stop
  - cancellation fields, planned solo
  - planned time, actual time
- The person id is stored only **pseudonymised** (HMAC with `APP_PERSON_ID_SECRET`).

**Derived data:**

- After an import, `RouteRun` and `DailyStats` are rebuilt for the **affected dates**:
  - **`RouteRun`** (one per route run): last drop-off planned and actual, delay, peak passengers, cancelled.
  - **`DailyStats`** (date × supplier): trips, completed, cancelled, route runs, last drop-offs, on time, solo runs, driven km, empty km, driven minutes, empty minutes.

**Calculation rules:**

- **Punctuality:**
  - The last drop-off of a completed route run is on time if it is at most `APP_PUNCTUALITY_TOLERANCE_MINUTES` after the planned time.
  - The actual time is the `actual_time` field in the mapping.
  - Cancelled runs are excluded.
- **Empty running %:** the distance to the next stop where no passengers are on board, divided by the total distance.
- **Solo routes:** route runs whose peak number of simultaneous passengers is ≤ 1.
- **Trips:** one per pickup/drop-off pair.
- **Citizens:** a distinct count of the pseudonymised person id over the period.
- Dates are converted to local time (Europe/Copenhagen), using the format given in the mapping.

**Master data** (`ROLE_ADMIN`):

- `Supplier` and `Route` (route → supplier), maintained with hand-built forms.
- The extract has no supplier, so admins maintain the mapping.
- Unmapped routes count as "Unknown" and are listed at the top of the routes page.

## 8. Manual entry of daily counts

**`DailyCount` entity**, unique per date:

- `mails`, `complaints`, `phoneCalls`
- `updatedBy`, `updatedAt`

**Form** (`ROLE_EDITOR`):

- A week grid: seven days with three number inputs each.
- Previous/next week navigation.
- Validation: integers ≥ 0, no future dates.
- Saving a day again updates the existing row.

## 9. Overview

**Page:** `/` (`ROLE_USER`), driven by GET filters:

- `p`: period (day/week/month/year)
- `d`: date
- `c`: comparison (previous period / same period last year)
- `s`: supplier

**Report services:**

- **`PeriodFilter`:** a `#[MapQueryString]` DTO. Invalid values fall back to the defaults.
- **`PeriodRangeFactory`:**
  - period ranges, previous/next navigation and comparison ranges
  - partial periods: the comparison is cut to the same number of days
  - the trailing 12 months
- **`KpiCalculator`:**
  - SQL `SUM`s over `DailyStats` and `DailyCount`, plus the distinct count of citizens, returned as a `Kpis` value object.
  - Ratios come from summed counts and are never averaged.
- **`Delta`/`Tone`:** the change from the comparison period, and the ok/warn/danger thresholds from one config file.

**Eight tiles.** Each tile shows the value, the change from the comparison period, a 12-month sparkline and a `?` help text.

| Tile | Value | Better when |
|---|---|---|
| Empty running | % of km | lower |
| Trips | count | – |
| Citizens | distinct count | – |
| Solo routes | count, with the share of route runs | lower |
| Punctuality | % | higher |
| Complaints | count, with the number per 1,000 trips | lower |
| Mails | count | – |
| Phone calls | count | – |

- The supplier filter narrows the driving figures. Daily counts are office-wide, and their tiles say so when a supplier is selected.
- A 12-month punctuality line chart with the target line.
- A supplier table for the driving figures.
- Charts are server-rendered inline SVG Twig components (`Sparkline`, `LineChart`), each with a hidden data table for screen readers.

## 10. Deployment with Woodpecker

Deployment uses Woodpecker, following the economics conventions.

**Pipelines:**

| Pipeline | Trigger | Target |
|---|---|---|
| `.woodpecker/stg.yml` | Push to `release/*` or `hotfix/*` | Staging |
| `.woodpecker/prod.yml` | `release` event | Production |

**Steps:** both pipelines use `itkdev/ansible-plugin` to run:

```shell
git reset --hard
git fetch origin <BRANCH>
git checkout <BRANCH>
git pull
itkdev-docker-compose-server up -d --force-recreate
itkdev-docker-compose-server exec phpfpm composer install --no-dev -o --classmap-authoritative
itkdev-docker-compose-server exec phpfpm bin/console doctrine:migrations:migrate --no-interaction
itkdev-docker-compose-server exec phpfpm bin/console cache:clear
```

**Server configuration:**

- `.env.local` on the server holds `APP_SECRET`, `APP_PERSON_ID_SECRET`, `APP_AUTH_MODE=password` and `DATABASE_URL`.
- Woodpecker secrets hold the host, path, user and keys.

**After the first deploy:** create the first admin with `app:user:create`.

---

# Later milestones (outline)

1. **Theme pages:** activity, punctuality (delay bands, the worst routes), capacity (empty running cost, planned vs actual solo, contract lots).
2. **Detailed enquiries and complaints:**
   - telephony details (answered, abandoned, answer time)
   - complaint cases (category, outcome, deadline in working days)
   - a generated verdict on the overview
3. **Management report:** any period, split by area, with a printable one-pager.
4. **OIDC login:** via the identity provider, with group claims mapped to roles.
5. **Async processing:** Symfony Messenger with a supervisor-run worker, when upload sizes or integrations need it.
6. **Integrations** (nightly, via the scheduler):
   - planning system API
   - telephony system
   - mailbox
   - complaints source
7. **Demo data:** a generator for presentations, ported from the prototype.

---

## Proposed structure (milestone 1)

```text
src/
  Controller/            OverviewController, ImportController, DailyCountController,
                         SecurityController, Admin/{User,Supplier,Route}Controller
  Entity/                User, Supplier, Route, ImportBatch, StopRow, RouteRun,
                         DailyStats, DailyCount
  Form/                  ImportType, DailyCountWeekType, SupplierType, RouteType, UserType
  Report/                PeriodFilter, PeriodRangeFactory, KpiCalculator, Kpis, Delta, Tone
  Import/DrivingData/    Mapping, MappingLoader, StopRowParser, StopRowUpserter, RouteBuilder,
                         DailyStatsAggregator
  Security/              PasswordAuthenticator, NoLoginAuthenticator
  Twig/                  FormatExtension, Components/{Tile,Sparkline,LineChart,Panel}
  Command/               User{Create,SetPassword,SetRoles}Command, DrivingDataImportCommand,
                         DrivingDataMappingValidateCommand
config/import/driving_data/   default.yaml (import mapping for the current source)
templates/ assets/styles/ translations/ migrations/ tests/
docs/                    architecture.md, plan.md, tasks.md, adr/, diagrams/
```

## Open questions affecting milestone 1

- **Row id:** which column in the extract is a stable row id? It is set in the mapping. If there is no single column, the mapping uses a composite key (route, date, person id, stop type).
- **Actual time:** which column holds the actual time? It is set in the mapping until this is confirmed.
- **Supplier mapping:** where does route → supplier come from? It is admin-maintained until a source is known.
- **Solo routes:** do we count actual solo runs (as assumed) or trips planned as solo?
- **Test data:** we need an anonymised sample extract for the fixtures.
- **No PII in the database (not decided yet).** The goal is to store no personal data about citizens.
  - **Problem:** a hashed person id or row id is still personal data under GDPR (pseudonymised, not anonymised), because it can be linked back through the source system.
  - **Hard requirements:** re-imports must overwrite earlier data, and the same data must never be counted twice. Duplicates would make every figure wrong.
  - **Options:**
    1. **Pseudonymised rows** (the current plan): exact distinct counts and row-level overwrite, but the database holds personal data. Needs a DPIA, retention rules and access control.
    2. **Aggregates only:** store counts per route run and per day, with a HyperLogLog sketch per day for distinct citizens (about 1 % error, no identifiers). Overwrite means re-uploading replaces all data for the dates in the file.
       - Duplicates are impossible.
       - A file that contains only part of a day would replace the whole day.
    3. **Rows for a correction window, then aggregates:** keep pseudonymised rows only for as long as corrections can arrive (for example 60 days). After that, a purge job deletes them and keeps the aggregates and sketches. This gives row-level overwrite for recent data and no identifiers in older data.
  - **Applies to every option:**
    - No names, addresses or other personal details are imported.
    - Rejected-row messages and logs never contain field values.
    - Uploaded files are deleted after processing.
  - **About the person id:**
    - It is an internal id of the planning system and means nothing outside it.
    - That lowers the risk, but for us it is still personal data: the organisation has access to the planning system and can look the id up.
    - Hashing it with a secret means the database alone doesn't reveal the planning-system id. The organisation can still re-identify through the planning system, so for us it remains pseudonymised personal data.
  - **What the person id is used for:**
    - **The Citizens figure** (distinct people in a period). This is the only figure that needs it. Trips, solo routes, empty running and punctuality don't.
    - **The fallback row key** (route, date, person id, stop type). This is only needed if the extract has no stable row id column of its own.
    - If the extract has a stable row id, the person id does not need to be stored. At most it is read during the import for the Citizens count. The options for Citizens without storing it:
      - drop the figure, or show it for single days only (an exact count per day, stored as a number at import)
      - a HyperLogLog sketch per day, built during the import, after which the id is discarded. Weeks, months and years are calculated by combining the sketches, with about 1 % error.
      - take distinct counts per period from the source system, if it can provide them
  - **Inputs to the decision (still undecided):**
    - Does the extract have a stable row id column? If yes, the person id is not needed for the overwrite key.
    - How late can corrections to an extract arrive? This sets the correction window in option 3.
    - Does one file always contain whole days? If not, option 2 would wipe the rest of a partial day.
  - **Staff accounts** (username, name) are also personal data. Decide what to store when this is settled.
  - **Decide before task M1-10b.** The parser and the `StopRow` design depend on it.

## Verification

- **Docs:**
  - The README, architecture and task list are approved before step 4.
  - At the end of the milestone, everything in the README works as described.
  - `task docs:diagrams` renders cleanly, and the diagrams match the code.
- **Quality gates** (required in CI before merge):
  - 100 % line coverage
  - the MSI thresholds
  - PHPStan at max level
  - coding standards
- **Environment:**
  - `task setup` gives a working site at the local URL.
  - `doctrine:schema:validate` is clean.
- **Auth:**
  - Login works for a user created with `app:user:create`.
  - Wrong passwords are rejected and rate limited.
  - The route-guard test passes, and each route returns the expected status per role.
- **Import:**
  - The fixture extract produces its hand-computed totals for every figure.
  - `app:driving-data:mapping:validate` accepts the default mapping with the fixture, and rejects a mapping with a missing column or an unknown value.
  - Re-importing the same file gives 0 added rows, only updated ones, and unchanged totals.
  - Changing one row and re-importing changes only that row.
- **Daily counts:** saving a week, then editing a day, updates the existing rows.
- **Overview:**
  - Unit tests for periods (weeks, partial periods, clamping), the KPI maths, `Delta` and `Tone`.
  - Manual: upload, enter counts, and check the eight tiles, the deltas and the supplier filter.
