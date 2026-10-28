<!-- The top-level headings group the plan into its parts. -->
<!-- markdownlint-disable MD025 -->

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
  - the import mapping loader and validation, the parser, `RouteBuilder`, `DailyStatsAggregator`
  - `FormatExtension`, the authenticators' `supports()`, value objects
- **Integration** (`tests/Integration`, `KernelTestCase` + MariaDB test DB, isolated with `dama/doctrine-test-bundle`):
  - repositories and the SUM and distinct-count queries
  - replacement of whole dates, and clearing a date range
  - console commands (`CommandTester`)
- **Functional** (`tests/Functional`, `WebTestCase`):
  - every route × permission role (200/403/redirect)
  - forms: valid submit, validation errors, CSRF
  - login, logout and rate limiting
  - the HTTP security headers
  - upload end-to-end with the fixture file
  - the overview with filters
  - the route-guard test
- **Fixtures:**
  - Foundry factories for every entity.
  - An anonymised one-day extract in `tests/fixtures/`, with its expected totals worked out by hand.
  - Small hand-made extracts for edge cases: cancelled routes, unmapped routes, re-upload with
    changed and removed rows, malformed files.
  - Supplier changes: a route that changes supplier in the middle of a period, and the figures
    before the change stay the same.

**100 % code coverage:**

- PHPUnit with `pcov`, installed in the phpfpm image for development and CI. Xdebug coverage mode is only a fallback.
- `phpunit.dist.xml` `<source>` covers all of `src/`. The only exclusions are `src/Kernel.php` and `migrations/`.
- `@codeCoverageIgnore` is not used.
- CI fails below 100 % line coverage (`coverage-check` on the Clover report). Branch coverage is reported.
- Twig templates are not measured by coverage, so the functional tests assert on rendered content.

**Mutation testing (Infection):**

- `infection/infection` with `infection.json5`.
  - It mutates `src/` using the PHPUnit suite with `--threads=max`.
  - **Not mutated:** `src/Controller`, `src/Form`, `src/Twig/Components` and `src/Kernel.php`. This
    glue is still covered (100 % lines) by the functional tests. Mutating it mostly produces
    equivalent mutants and slow runs.
  - Reports are written to `var/infection/`.
- **Thresholds, enforced in CI:**
  - `minCoveredMsi: 100` for `src/Report` and `src/Import`, where a wrong number is the worst bug.
  - `minCoveredMsi: 80` for the rest of the mutated code (`Security`, `Command`, `Twig` extensions,
    entities and repositories). Raise it when it is reached comfortably.
- Equivalent mutants are listed with a reason in `infection.json5` and reviewed in PRs.
- **When it runs:**
  - **On every feature PR to `develop`, for changed lines only** (`--git-diff-lines --git-diff-base=origin/develop`).
    - Fast, because only the mutants in the lines the PR touched are run.
    - This is the merge gate.
  - **Full run on release branches.**
    - `.github/workflows/mutation-full.yml` runs Infection over the whole code base on push to
      `release/*` or `hotfix/*`, and on their PRs to `main`.
    - It runs only when a release is being prepared, so no time is spent when nothing has changed.
    - It catches what changed-lines runs miss. A change in one class can let mutants survive in
      another, untouched class, for example when a test that used to kill them is changed or
      deleted.
    - It is a required status check on the release PR to `main`, so production is never released
      with surviving mutants above the thresholds.
    - The full HTML report is kept as a workflow artifact.
    - Survivors are fixed on the release branch before merging.
    - Staging deploys from the same push (Woodpecker) still happen, so testers aren't blocked while the run completes.
  - **Locally:** `task test:mutation:full` runs the same full run whenever a developer wants it, for
    example after a larger refactoring.

**Expected cost:** roughly 20–30 % extra time early on, falling as factories, fixtures and test
helpers build up. This is an estimate, to be revisited after the first tasks.

**Taskfile tasks:**

- `task test`, `task test:unit` and `task test:file -- <FILE>`
- `task test:coverage` and `task test:coverage:check`, which fails if coverage is below 100 %
- `task test:mutation`: changed lines only, compared with `develop`
- `task test:mutation:full`: the whole code base, as the release-branch run does
- `task code-analysis`: PHPStan at max level
- `task coding-standards-check` and `task coding-standards-apply`, which run the
  `coding-standards-{type}-{check|apply}` tasks for each type
- `task prepare-code`: everything above, with mutation testing on changed lines

**CI** (GitHub Actions, next to the template workflows):

- **PR workflow:** coding standards, PHPStan, tests with the coverage gate, and mutation testing on changed lines.
- **Release workflow:** a full mutation run on push to `release/*` or `hotfix/*`, and on their PRs
  to `main` (a required check).

**Branching** (our usual flow):

- `develop` is the default branch, and all development starts from it.
- **Feature work:** feature branches (`feature/issue-<ISSUE_NUMBER>-<SHORT_DESCRIPTION>`) are cut
  from `develop` and merged back through a PR. The PR workflow and changed-lines mutation testing
  gate the merge.
- **Release:**
  1. Cut a release branch (`release/<VERSION>`) from `develop`.
  2. Every push to it deploys to staging (Woodpecker) and runs the full mutation run.
  3. Fixes found in testing go on the release branch.
  4. The release branch is merged to `main` through a PR. The full mutation run is a required check.
- **Production:** a GitHub release (tag `<VERSION>`) on `main` deploys to production (Woodpecker).
- **Back-merge:** after the release, `main` is merged back into `develop`, so release fixes are not lost.
- **Hotfixes:** `hotfix/<VERSION>` is cut from `main`, follows the same path as a release (full run,
  PR to `main`, release), and is merged back into `develop`.
- `CHANGELOG.md` gets an entry per feature PR under "Unreleased". The release branch turns
  "Unreleased" into the version.
- **To set up before step 4:** create `develop` from `main` and make it the default branch. Protect
  `develop` and `main` with the required checks.
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
  - upload of driving data (each date in the file replaces the stored data for that date)
  - clearing the driving data for a date range
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
- **Features:** login and roles, upload with replacement of whole dates, clearing dates, daily counts, filters
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

- **System context:** users by role, the application, the driving data extract, and the later
  sources (telephony system, mailbox, case management system, identity provider).
- **Containers:** Traefik → nginx → phpfpm (Symfony) → MariaDB.
- **Components:**
  - controllers and forms
  - `Security`: authenticators per mode, role guards
  - `Import/DrivingData`: mapping (YAML) → parser → replacer → route builder → daily stats aggregator
  - `Report`: `PeriodRangeFactory`, `KpiCalculator`, `Delta`/`Tone`
  - Twig components
- **Data model:** an ER diagram of `User`, `Supplier`, `Route`, `RouteSupplierAssignment`,
  `ImportBatch`, `StopRow`, `RouteRun`, `DailyStats` and `DailyCount`.
- **Sequence: upload.** Form → `ImportBatch` → parse → replace the stop rows for the dates in the
  file → rebuild `RouteRun`/`DailyStats` for those dates → result page.
- **Sequence: overview request.** Authenticator by mode → `IsGranted` → `PeriodFilter` →
  `KpiCalculator` (current period, comparison period, 12 months) → Twig.
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
  - `001-stack.md`
  - `002-auth-modes.md`
  - `003-driving-data-import-mapping-and-overwrite.md`
  - `004-personal-data-in-driving-data.md` (draft, see the PII open question)

## 3. Break the plan into tasks

Once the README and architecture are approved, break steps 4–10 into small, independently
deliverable tasks in `docs/tasks.md`. Each task becomes one GitHub issue, one branch and one PR.

**Issues, branches and pull requests:**

- Each task has a GitHub issue. Its title is the task ID and title.
- The branch is `feature/issue-<ISSUE_NUMBER>-<SHORT_DESCRIPTION>`, as in the ITK Dev
  conventions.
- The PR title follows Conventional Commits and ends with the task ID, for example
  `feat: replace stop rows for the dates in a file (M1-16)`. The description has
  `Closes #<ISSUE_NUMBER>`.
- `.github/pull_request_template.md` has the definition of done as a checklist.

**Each task has:**

- **ID and title**, e.g. `M1-16 Replace stop rows for the dates in a file`
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
3. Features as a vertical slice, so a user can upload a file and see one figure on the overview as early as possible.

**Result:** [`docs/tasks.md`](tasks.md) has 28 tasks in six groups: setup and quality gates,
security, layout and administration, data in, overview, and deployment. Each task lists its
dependencies, the tests to write first, and its acceptance criteria.

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
   - `twig-bundle`, `asset-mapper`, `form`, `validator`, `security-bundle`, `translation`,
     `rate-limiter`, `twig/intl-extra`
   - `openspout/openspout` (streams `.xlsx` rows with low memory use), `symfony/ux-twig-component`
   - `nelmio/security-bundle`, for the HTTP security headers
4. Design system:
   - Require `itk-dev/itkaarhus-design-system` from the vcs repository
     `https://github.com/itk-dev/itkaarhus-design-system`.
   - Map it in AssetMapper as `itkaarhus-ds`.
5. Dev tooling:
   - php-cs-fixer, twig-cs-fixer, PHPStan (symfony + doctrine, max level), PHPUnit
   - `rregeer/phpunit-coverage-check`
   - doctrine-fixtures, `zenstruck/foundry`, `dama/doctrine-test-bundle`
   - `infection/infection`, and pcov in the phpfpm image
   - `ergebnis/composer-normalize`, allowed in `config.allow-plugins`
   - Write the Taskfile with the ITK Dev header (`dotenv: [".env.local", ".env"]` and the vars
     `DOCKER_COMPOSE`, `PHP`, `COMPOSER` and `CONSOLE`) and the tasks `setup`, `compose`,
     `composer`, `console`, `test*`, `code-analysis`, `prepare-code`, `coding-standards-*`,
     `db:migrate`, `fixtures:load` and `docs:diagrams`.
   - The PR workflow also runs `composer audit`, and `.github/dependabot.yml` covers Composer and GitHub Actions.
   - **Smoke test first:** a failing functional test that `/` redirects to `/login`.
6. Docs:
   - Check `README.md` against the actual setup.
   - Add `CHANGELOG.md`.

## 5. Security: role guards and username/password login

**Permission roles** (one per part of the application; controllers check only these):

- `ROLE_VIEW_OVERVIEW`: view the overview
- `ROLE_UPLOAD_DRIVING_DATA`: upload driving data
- `ROLE_CLEAR_DRIVING_DATA`: clear the driving data for a date range
- `ROLE_EDIT_DAILY_COUNTS`: enter daily counts
- `ROLE_MANAGE_SUPPLIERS`: suppliers, routes and route → supplier assignments
- `ROLE_MANAGE_USERS`: users

**`ROLE_ADMIN`** (`role_hierarchy`):

- `ROLE_ADMIN` gives all permission roles.
- At first, users get `ROLE_ADMIN` only. Narrower access later needs only role assignments.
- Every signed-in user also has `ROLE_USER`. It grants nothing alone.

**Guarding:**

- Every controller has a class-level `#[IsGranted]` with a permission role, with stricter rules per action where needed.
- `access_control` denies by default (`^/` → `ROLE_USER`). Only `/login` and assets are public.
- A test walks the router. It fails if any application route lacks `IsGranted`, or if it checks
  `ROLE_ADMIN` or `ROLE_USER`.
- The user form and `app:user:set-roles` accept `ROLE_ADMIN` and the permission roles.

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

**HTTP security headers** (ASVS V14.4, `nelmio/security-bundle`):

- `Strict-Transport-Security` (in `prod` only), a Content Security Policy with `default-src 'self'`
  and `frame-ancestors 'none'`, `X-Content-Type-Options: nosniff` and `Referrer-Policy:
  same-origin`.
- See [Security](architecture.md#security) for the full values.
- No inline scripts, no inline event handlers and no `style` attributes in templates. SVG charts use
  classes and presentation attributes.
- A functional test checks the headers on `/login` and on `/`.

**`none` mode** (development convenience):

- `NoLoginAuthenticator` signs every request in as a local user with the roles in `APP_NO_LOGIN_ROLES`.
- `APP_NO_LOGIN_ROLES` defaults to `ROLE_VIEW_OVERVIEW`. A wrong server configuration then gives
  read access only. Local development sets `ROLE_ADMIN` explicitly.
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

- Overview (`ROLE_VIEW_OVERVIEW`)
- Upload driving data (`ROLE_UPLOAD_DRIVING_DATA`)
- Clear driving data (`ROLE_CLEAR_DRIVING_DATA`)
- Daily counts (`ROLE_EDIT_DAILY_COUNTS`)
- Administration: suppliers and routes (`ROLE_MANAGE_SUPPLIERS`), users (`ROLE_MANAGE_USERS`)

Menu items are shown with `is_granted()`.

**Language and formatting:**

- Danish locale, with texts in `translations/messages+intl-icu.da.yaml`.
- `FormatExtension` provides: percent, percentage points, signed delta, kilometres, period labels,
  and "–" for missing values.

## 7. Upload of driving data

**Upload flow** (`ROLE_UPLOAD_DRIVING_DATA`):

- An upload form creates an `ImportBatch`: file name, uploaded by, the replaced dates, row counts
  (deleted/inserted/rejected), errors.
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
  - **Row id (optional):** one or more columns. A row id that occurs more than once in one file is
    rejected after the first occurrence.
  - **Field mapping:** a column for each application field.
  - **Parsing per field:** type (`string`, `int`, `float`, `bool`, `datetime`), date format (Excel
    serial or a format string), value maps for codes (stop type, stop status, yes/no), and whether
    the field is required.
- **Implementation:**
  - `MappingLoader` reads the YAML and validates it with the Symfony Config component (`TreeBuilder`).
  - The app refuses to start if a mapping is invalid.
  - `StopRowParser` reads the workbook only through a `Mapping` value object. It never refers to column names directly.
- `app:driving-data:mapping:validate <MAPPING_NAME> <PATH_TO_FILE>` checks a sample file against a
  mapping. It reports missing columns and unmapped values, and previews the first parsed rows.
- **Tests:**
  - unit tests for the loader and validation, and for each field type and value map
  - parser tests run against a test mapping in `tests/fixtures/mappings/`

**Storage and replacement** (see [ADR 003](adr/003-driving-data-import-mapping-and-overwrite.md)):

- `StopRow` holds one row per stop from the extract.
- **Replace whole dates:** the import dates are the dates with at least one valid row.
  `StopRowReplacer` deletes all `StopRow` rows for these dates, from all mappings, and then inserts
  the rows of the file.
  - Rows that are not in a corrected file are removed.
  - Two uploads can never count the same date twice.
  - A file that holds only part of a day removes the other rows of that day. The result page lists the replaced dates.
- If the mapping has a row id, a unique index on `(mapping_name, row_key)` stops duplicate rows in one file.
- **No rollback:** an `ImportBatch` cannot be undone. Upload the correct file, or clear the dates.
- Only the fields the calculations need are kept:
  - route, date, stop type (pickup/drop-off), stop status, end-of-route flag
  - passengers after the stop
  - distance and duration to the next stop
  - cancellation fields, planned solo
  - planned time, actual time
- The person id is stored only **pseudonymised** (HMAC with `APP_PERSON_ID_SECRET`).

**Derived data:**

- After an import, `RouteRun` and `DailyStats` are rebuilt for the **import dates**:
  - **`RouteRun`** (one per route run): last drop-off planned and actual, delay, peak passengers, cancelled.
  - **`DailyStats`** (date × supplier): trips, completed, cancelled, route runs, last drop-offs, on
    time, solo runs, driven km, empty km, driven minutes, empty minutes.

**Clear driving data** (`ROLE_CLEAR_DRIVING_DATA`):

- A page where the user selects a date range. It shows the number of stop rows and route runs in the
  range, and asks for confirmation.
- `DrivingDataClearer` deletes `StopRow`, `RouteRun` and `DailyStats` for the range in one
  transaction. `DailyCount` does not change.
- The action is logged: who, when, the range and the deleted counts. No field values.
- CLI: `app:driving-data:clear <FROM_DATE> <TO_DATE>`, with the same service.

**Calculation rules:**

- **Punctuality:**
  - The last drop-off of a completed route run is on time if it is at most
    `APP_PUNCTUALITY_TOLERANCE_MINUTES` after the planned time.
  - The actual time is the `actual_time` field in the mapping.
  - Cancelled runs are excluded.
- **Empty running %:** the distance to the next stop where no passengers are on board, divided by the total distance.
- **Solo routes:** route runs whose peak number of simultaneous passengers is ≤ 1.
- **Trips:** one per pickup/drop-off pair.
- **Citizens:** a distinct count of the pseudonymised person id over the period.
- Dates are converted to local time (Europe/Copenhagen), using the format given in the mapping.

**Master data** (`ROLE_MANAGE_SUPPLIERS`):

- `Supplier`, `Route` and `RouteSupplierAssignment`, maintained with hand-built forms.
- The extract has no supplier, so admins maintain the assignments.
- **Assignments have dates:** each has `valid_from` and an optional `valid_to`. The assignments of
  one route must not overlap.
  - A route can change supplier, and the supplier comparison keeps the past figures of each supplier.
  - To change supplier, end the current assignment and add a new one from the change date.
  - `DailyStatsAggregator` finds the supplier for each route and date.
  - A change to an assignment rebuilds `DailyStats` only for the dates of the old and the new date range.
- A route with no assignment on a date counts as "Unknown" on that date. Routes with no current
  assignment are listed at the top of the routes page.

## 8. Manual entry of daily counts

**`DailyCount` entity**, unique per date:

- `mails`, `complaints`, `phoneCalls`
- `updatedBy`, `updatedAt`

**Form** (`ROLE_EDIT_DAILY_COUNTS`):

- A week grid: seven days with three number inputs each.
- Previous/next week navigation.
- Validation: integers ≥ 0, no future dates.
- Saving a day again updates the existing row.

## 9. Overview

**Page:** `/` (`ROLE_VIEW_OVERVIEW`), driven by GET filters:

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
  - SQL `SUM`s over `DailyStats` and `DailyCount`, plus the distinct count of citizens, returned as
    a `Kpis` value object.
  - Ratios come from summed counts and are never averaged.
- **`Delta`/`Tone`:** the change from the comparison period, and the ok/warn/danger thresholds from one config file.

**Eight tiles.** Each tile shows the value, the change from the comparison period, a 12-month
sparkline and a `?` help text.

| Tile | Value | Better when |
| --- | --- | --- |
| Empty running | % of km | lower |
| Trips | count | – |
| Citizens | distinct count | – |
| Solo routes | count, with the share of route runs | lower |
| Punctuality | % | higher |
| Complaints | count, with the number per 1,000 trips | lower |
| Mails | count | – |
| Phone calls | count | – |

- The supplier filter narrows the driving figures. Daily counts are office-wide, and their tiles say
  so when a supplier is selected.
- A 12-month punctuality line chart with the target line.
- A supplier table for the driving figures.
- Charts are server-rendered inline SVG Twig components (`Sparkline`, `LineChart`), each with a
  hidden data table for screen readers.

## 10. Deployment with Woodpecker

Deployment uses Woodpecker.

**Pipelines:**

| Pipeline | Trigger | Target |
| --- | --- | --- |
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

- `.env.local` on every server, staging included, holds `APP_ENV=prod`, `APP_SECRET`,
  `APP_PERSON_ID_SECRET`, `APP_AUTH_MODE=password` and `DATABASE_URL`.
- The guard against `none` mode depends on `APP_ENV=prod`.
- Do not include `.env.local` in the backups of the database host.
- Woodpecker secrets hold the host, path, user and keys.

**After the first deploy:** create the first admin with `app:user:create`.

---

# Milestone 2 (outline)

Milestone 2 will be broken into tasks once milestone 1 is done. The same development approach
applies: README first, architecture and ADRs updated, then tasks.

**Scope:**

- **Dashboard:** the overview grows into a dashboard
- **Trends:** whether things are getting better or worse
- **Nightly integrations:**
  - the telephony system, if possible
  - the mailboxes, through the mail API
  - complaints, from a source not yet chosen
- **Extra views:**
  - supplier comparisons
  - complaints: more than the count
  - phone enquiries: more than the count, depending on the data
  - estimated cost of empty running
- **OIDC login:** everyone who is assigned to the application has access to everything
- **Security and data protection (decision needed, Q-M2-01):** are these part of Sølv?
  - security and audit logging: sign-ins, denied requests, changes to users and to the import mapping
  - retention rules for the stored data
  - a DPIA. [ADR 004](adr/004-personal-data-in-driving-data.md) option 1 (pseudonymised rows)
    requires one, and milestone 2 adds complaint and mail data.

## M2.1 Dashboard and trends

**Contents (decision needed, Q-M2-02):**

- The concept's front page has six figures, with complaints per 1,000 trips as the figure itself.
- The milestone 1 overview has eight tiles, with complaints as a count.
- The PM decides which of the two Sølv delivers. Until then, the plan keeps the eight tiles and adds
  complaints per 1,000 trips.

**Verdict:**

- A generated sentence at the top: better, worse, mixed or unchanged, compared with the comparison period.
- It is derived from the data, never written by hand, so it changes when the figures change.

**Settings:**

- Which figures count towards the verdict: punctuality, complaints, empty running, solo routes.
- The minimum change before a change counts at all.
- The defaults are punctuality and complaints, with at least 1 %.
- The settings live in the URL, like the other filters, so a configured dashboard can be shared as a link.

**Cancellations (investigation, Q-M2-03):**

- Cancellations are not in the verdict, because no cancellation figure is defined.
- `StopRow` keeps only `stop_status`. It does not keep when a trip was cancelled, so late and early
  cancellations cannot be told apart.
- The concept's extract gives two different counts (28 and 33) for the same data.
- Find where cancellations and their time are recorded reliably. Then define the figure and add it
  to the verdict, or leave it out.

**Trend views:**

- 12-month trends for each figure, with the direction against the comparison period.
- A punctuality target line.
- Monthly charts for complaints per 1,000 trips, and for empty running.

**Code:**

- `Verdict` service in `src/Report`, using `Delta` and the threshold.
- 100 % covered MSI applies here too, like the rest of `src/Report`.

## M2.2 Nightly integrations

**Running:**

- Each integration is a console command, e.g. `app:integration:telephony:sync`.
- They run once a night, from cron on the server, configured in the Woodpecker deploy (the economics convention).
- No queue or worker is needed while each sync is a short batch job. Add Symfony Messenger with a
  supervisor-run worker only if a sync becomes long-running.

**Design:**

- A `DataProviderInterface` adapter per source.
- Each sync is idempotent: it fetches a date range, by default yesterday plus a few days back to
  pick up corrections, and overwrites the data for those dates. Running it twice never duplicates
  data.
- Integrations write to the same tables as manual entry, with a `source` column (`manual` or the
  integration name). An integration never overwrites a day that was entered by hand without saying
  so in the sync report.
- Credentials come from env vars or Symfony secrets.
- Each run is logged with its date range, counts and errors. Sync status ("last successful sync") is
  shown in the administration.

**Sources:**

| Source | Data | Status |
| --- | --- | --- |
| Telephony system | Calls offered, answered, abandoned in queue, average answer time, share answered within target | If possible. Depends on an API or export |
| Mailboxes via the mail API | Mails received, closed and unhandled per day | Needs a service account and permissions to the mailboxes |
| Complaints | Received date, supplier, route, category, outcome, closed date | Source not chosen (Q-M2-06): F2, the OS2forms complaint form, or collected from the shared mailbox through the mail API |

- **Testing:** tests use recorded responses (fixtures). There are no live calls in CI.

**Mail API permissions:**

- An application permission such as `Mail.Read` gives access to every mailbox in the tenant. Do not
  grant it without a limit.
- Use `Mail.ReadBasic` if the counts need only the mail headers. It does not give access to the body or the attachments.
- Limit the application to the shared mailbox with Exchange RBAC for Applications.
- IT operations grants the permissions (Q-M2-05).

**Complaint source:**

- The stakeholder comment on the concept prefers F2, or the OS2forms complaint form before the case
  gets to the ESDH system.
- The PM decides the source (Q-M2-06).
- If milestone 2 is ready before the source is, the complaint count stays a manual daily count, as
  in milestone 1. The views beyond the count then need manual entry of complaint cases.

## M2.3 Extra views

**Supplier comparison:**

- Every figure per supplier, side by side.
- Every supplier is always shown, whatever the supplier filter says.
- Normalised figures (per 1,000 trips, percentages) so suppliers of different size can be compared.

**Complaints beyond the count:**

- **Breakdowns:** category and subcategory, supplier, route.
- **Case handling:** outcome, processing time in working days, the share within the deadline, repeat complaints.
- **Depends on the source:** which fields it delivers. Manual entry of complaint cases is the fallback.
- **Working days:** calculated with a holiday calendar.
- **Not in the plan yet (decision needed, Q-M2-07):** the 10 and 20 working-day service targets, the
  share of complaints upheld, and a split by contract lot. The PM decides if Sølv leaves them out on
  purpose.
- **Personal data:** repeat complaints need an identifier for the complainant. Complaints from F2 or
  the mailbox can contain driver names. [ADR 004](adr/004-personal-data-in-driving-data.md) covers
  only driving data. Extend it, or add an ADR for complaint and mail data, before the complaint
  integration starts (Q-M2-08).

**Phone enquiries beyond the count:**

- **Figures:** answered, abandoned in the queue, average answer time, and the share answered within target.
- **Depends on the source:** these exist only if the telephony data provides them. Otherwise the
  view shows the count only.
- **Weighted averages:** answer time and the within-target share are stored weighted by answered
  calls, so averages over a period are exact.

**Estimated cost of empty running:**

- Empty minutes × a price per minute, or empty km × a price per km, depending on what the data contains.
- The view always says it is an estimate, and shows the price it used.
- **Where the price comes from (investigation, Q-M2-09):**
  - As env vars, only a developer can change the price.
  - The alternatives are to read the price from the upload, or to let an administrator set it in the application.
  - Find out what the extract contains reliably: minutes, km, a price. Then decide.

## M2.4 OIDC login

**Authentication:**

- `APP_AUTH_MODE=oidc` with `itk-dev/openid-connect-bundle` against the identity provider.
- Users are created or updated on first login.

**Access:**

- Everyone who can sign in has access to everything. This is on purpose in Sølv.
- So the assignment in the identity provider decides who is an administrator.
- Only a named group is assigned to the application, not the whole organisation. The product owner
  names the group (Q-M2-10).
- Signed-in users get the roles in `APP_OIDC_DEFAULT_ROLES`, default `ROLE_ADMIN`.
- The role guards from milestone 1 stay in place. Restricting access later means changing the role
  assignment (for example mapping group claims to roles), not the controllers.

**Other modes:** password and `none` modes stay available.

## Open questions for milestone 2

Each question has an owner, a date when the answer is needed, and the work it blocks. The
owners are roles. The PM puts names on them.

- **During milestone 1** means that the investigation runs in parallel with milestone 1. The answer
  is needed before milestone 2 is broken into tasks.
- **Before M2 tasks** means before milestone 2 is broken into tasks.

| Id | Question | Owner | Needed by | Blocks |
| --- | --- | --- | --- | --- |
| Q-M2-01 | **Security and data protection:** are security and audit logging, retention rules and a DPIA part of Sølv? | PM, with the data protection adviser | Before M2 tasks | M2 scope |
| Q-M2-02 | **Dashboard:** does Sølv deliver the concept's six figures or the eight milestone 1 tiles? | PM | Before M2 tasks | M2.1 |
| Q-M2-03 | **Cancellations:** where are cancellations and their time recorded reliably? | Developer, with the transport office | Before M2 tasks | M2.1 verdict |
| Q-M2-04 | **Telephony:** is there an API or a scheduled export? Which fields does it have? | IT operations, with the owner of the telephony system | During milestone 1 | M2.2 telephony, M2.3 phone enquiries |
| Q-M2-05 | **Mailboxes:** which mailboxes count? Should every mail count, or only mails about transport? Who creates the service account and grants its permissions? | Product owner (mailboxes), IT operations (service account) | During milestone 1 | M2.2 mailboxes |
| Q-M2-06 | **Complaints:** which source should be used? Do all complaints arrive through it, or also by mail and phone? Can the fields be extracted in a structured way? | PM | During milestone 1 | M2.2 complaints, M2.3 complaints |
| Q-M2-07 | **Complaint measures:** does Sølv leave out the 10 and 20 working-day service targets, the share upheld and the split by contract lot on purpose? | PM | Before M2 tasks | M2.3 complaints |
| Q-M2-08 | **Complaint and mail data:** extend ADR 004, or add a new ADR? | Product owner, with the data protection adviser | Before the complaint integration starts | M2.2 complaints, M2.3 repeat complaints |
| Q-M2-09 | **Empty running cost:** what does the extract contain reliably? What is the price, per minute or per km, and who sets it? | Developer (extract), product owner (price) | Before M2 tasks | M2.3 estimated cost |
| Q-M2-10 | **OIDC group:** which named group is assigned to the application? | Product owner, with IT operations | Before M2.4 is deployed | M2.4 |
| Q-M2-11 | **Working days:** which holiday calendar applies? | Product owner | Before M2 tasks | M2.3 processing time |
| Q-M2-12 | **Staff data:** what is stored about OIDC users (see ADR 004, staff accounts)? | Product owner, with the data protection adviser | Before M2.4 starts | M2.4 |

The PM confirms that milestone 1 can start without answers to these questions.

---

# Later milestones (outline)

1. **Theme pages:** activity, punctuality (delay bands, the worst routes), capacity (planned vs
   actual solo, trips that could not share a vehicle because they were in different contract lots).
2. **Management report:** any period, split by area, with a printable one-pager.
3. **Role-based access from OIDC claims:** if not everyone should see everything.
4. **Planning system API:** if the planning system can deliver data automatically, replacing uploads.
5. **Async processing:** Symfony Messenger with a supervisor-run worker, when upload sizes or integrations need it.
6. **Demo data:** a generator for presentations, ported from the prototype.

---

## Proposed structure (milestone 1)

```text
src/
  Controller/            OverviewController, ImportController, ClearDrivingDataController,
                         DailyCountController,
                         SecurityController, Admin/{User,Supplier,Route}Controller
  Entity/                User, Supplier, Route, RouteSupplierAssignment, ImportBatch, StopRow, RouteRun,
                         DailyStats, DailyCount
  Form/                  ImportType, DailyCountWeekType, SupplierType, RouteType,
                         RouteSupplierAssignmentType, UserType
  Report/                PeriodFilter, PeriodRangeFactory, KpiCalculator, Kpis, Delta, Tone
  Import/DrivingData/    Mapping, MappingLoader, StopRowParser, StopRowReplacer, RouteBuilder,
                         DailyStatsAggregator, DrivingDataClearer
  Security/              PasswordAuthenticator, NoLoginAuthenticator
  Twig/                  FormatExtension, Components/{Tile,Sparkline,LineChart,Panel}
  Command/               User{Create,SetPassword,SetRoles}Command, DrivingDataImportCommand,
                         DrivingDataClearCommand, DrivingDataMappingValidateCommand
config/import/driving_data/   default.yaml (import mapping for the current source)
templates/ assets/styles/ translations/ migrations/ tests/
docs/                    architecture.md, plan.md, tasks.md, adr/, diagrams/
```

## Open questions affecting milestone 1

The table gives the owner, the date when the answer is needed and the task each question
blocks. The owners are roles. The PM puts names on them. The details are in the list after
the table.

| Question | Owner | Needed by | Blocks |
| --- | --- | --- | --- |
| Whole days | Product owner (transport office) | Before M1-16 | M1-16 |
| Row id | Product owner, with a developer | Before M1-14 | M1-14 |
| Actual time | Product owner | Before M1-14 | M1-14 |
| Supplier mapping | Product owner | Before M1-28 (go-live) | Nothing. Administrators keep the assignments (M1-13) until a source is known. |
| Solo routes | Product owner | Before M1-23 | M1-23 |
| Test data | Product owner | Before M1-15 | M1-15, M1-27 |
| Self-service password change | PM accepts it as out of scope | Before M1-07 | M1-07 |
| Multi-factor authentication for administrators | PM accepts the risk | Before M1-07 | M1-07 |
| No PII in the database ([ADR 004](adr/004-personal-data-in-driving-data.md)) | Product owner, with the data protection adviser | Before M1-15 | M1-15 |

- **Whole days:** does each extract hold whole days? Re-upload replaces whole dates, so a partial
  day removes the other rows of that day.
- **Row id:** is there a stable row id column in the extract? It is optional. If it exists, the
  import uses it to reject duplicate rows in one file.
- **Actual time:** which column holds the actual time? It is set in the mapping until this is confirmed.
- **Supplier mapping:** where does route → supplier come from? It is admin-maintained until a source is known.
- **Solo routes:** do we count actual solo runs (as assumed) or trips planned as solo?
- **Test data:** we need an anonymised sample extract for the fixtures.
- **Self-service password change:** not in milestone 1. An administrator or a console command sets
  passwords (ASVS V2.1).
- **Multi-factor authentication for administrators:** not in milestone 1. OIDC in a later milestone
  can give it. Until then, it is a known risk (ASVS V4.3.1).
- **No PII in the database (not decided yet).** The goal is to store no personal data about citizens.
  - **Problem:** a hashed person id or row id is still personal data under GDPR (pseudonymised, not
    anonymised), because it can be linked back through the source system.
  - **Hard requirements:** re-imports must replace earlier data, and the same data must never be
    counted twice. Duplicates would make every figure wrong.
  - **Overwrite is the same for every option:** a re-upload replaces whole dates ([ADR
    003](adr/003-driving-data-import-mapping-and-overwrite.md)). No option needs the person id for
    that.
  - **Options:**
    1. **Pseudonymised rows** (the current plan): exact distinct counts, and the derived data can be
       rebuilt from the rows. But the database holds personal data. Needs a DPIA, retention rules
       and access control.
    2. **Aggregates only:** store counts per route run and per day, with a HyperLogLog sketch per
       day for distinct citizens (about 1 % error, no identifiers). A calculation fix needs a
       re-upload of the affected dates.
    3. **Rows for a correction window, then aggregates:** keep pseudonymised rows only for as long
       as corrections can arrive (for example 60 days). After that, a purge job deletes them and
       keeps the aggregates and sketches. Recent data can be rebuilt, and older data holds no
       identifiers.
  - **Applies to every option:**
    - No names, addresses or other personal details are imported.
    - Rejected-row messages and logs never contain field values.
    - Uploaded files are deleted after processing.
    - The HMAC key is not included in the backups of the database host.
  - **About the person id:**
    - It is an internal id of the planning system and means nothing outside it.
    - That lowers the risk, but for us it is still personal data: the organisation has access to the
      planning system and can look the id up.
    - Hashing it with a secret means the database alone doesn't reveal the planning-system id. The
      organisation can still re-identify through the planning system, so for us it remains
      pseudonymised personal data.
  - **What the person id is used for:**
    - **The Citizens figure** (distinct people in a period). This is the only use. Trips, solo
      routes, empty running and punctuality don't need it, and the import doesn't need it as a row
      key.
    - So the person id does not need to be stored. At most it is read during the import for the
      Citizens count. The options for Citizens without storing it:
      - drop the figure, or show it for single days only (an exact count per day, stored as a number at import)
      - a HyperLogLog sketch per day, built during the import, after which the id is discarded.
        Weeks, months and years are calculated by combining the sketches, with about 1 % error.
      - take distinct counts per period from the source system, if it can provide them
  - **Inputs to the decision (still undecided):**
    - How late can corrections to an extract arrive? This sets the correction window in option 3.
    - Is an approximate Citizens figure (about 1 % error) acceptable? Options 2 and 3 depend on it.
  - **Staff accounts** (username, name) are also personal data. Decide what to store when this is settled.
  - **Who decides:** the product owner in the transport office, with the data protection adviser.
    See [ADR 004](adr/004-personal-data-in-driving-data.md).
  - **Decide before task M1-15.** The parser and the `StopRow` design depend on it.

## Outside this plan (PM to place)

The concept's estimate includes work that this plan does not include:

- technical project management
- customer acceptance tests and feedback
- go-live assistance
- the operations and cybersecurity agreements

This plan also does not include:

- server provisioning
- backup and restore
- the initial history load for the 12-month trends
- alerts on failed syncs

The PM decides where each item belongs.

## Concept questions not in this plan (PM acceptance needed)

The concept's open questions 4–7, 11 and 13 are not part of milestone 1:

- daily vs annual figures
- the punctuality service target
- punctuality at pickup
- the source of penalties
- supplier access
- the upload cadence

Development can start without answers to them. The PM must accept this before step 4.

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
  - Every response has the HTTP security headers, and the browser console shows no Content Security Policy errors.
- **Import:**
  - The fixture extract produces its hand-computed totals for every figure.
  - `app:driving-data:mapping:validate` accepts the default mapping with the fixture, and rejects a
    mapping with a missing column or an unknown value.
  - Re-importing the same file deletes and inserts the same number of rows, and the totals do not change.
  - Re-importing a file with one row removed removes that row from the totals.
  - A file with a date that is not stored yet does not change the other dates.
  - Clearing a date range removes its driving figures and keeps its daily counts.
- **Daily counts:** saving a week, then editing a day, updates the existing rows.
- **Overview:**
  - Unit tests for periods (weeks, partial periods, clamping), the KPI maths, `Delta` and `Tone`.
  - Manual: upload, enter counts, and check the eight tiles, the deltas and the supplier filter.
