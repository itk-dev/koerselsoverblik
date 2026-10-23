# Tasks: milestone 1

This breaks milestone 1 of the [plan](plan.md) into small tasks. Each task is one branch and one
pull request to `develop`. The [README](../README.md) is the spec; the
[architecture](architecture.md) describes the design.

## Contents

- [How to work a task](#how-to-work-a-task)
- [Overview](#overview)
- [A. Setup and quality gates](#a-setup-and-quality-gates)
- [B. Security](#b-security)
- [C. Layout and administration](#c-layout-and-administration)
- [D. Data in](#d-data-in)
- [E. Overview](#e-overview)
- [F. Deployment](#f-deployment)

## How to work a task

1. **Check dependencies.** Every task under **Depends on** is merged to `develop`.
2. **Issue.** Each task has a GitHub issue. Its title is the task ID and title, for example
   `M1-16 Replace stop rows for the dates in a file`. Its body links to the task in this file.
3. **Branch.** Create `feature/issue-<ISSUE_NUMBER>-<SHORT_DESCRIPTION>` from `develop`, for
   example `feature/issue-42-replace-stop-rows`.
4. **Tests first.** Write the tests listed under **Tests first** and see them fail.
5. **Implement.** Write the minimal code to make them pass, then refactor.
6. **Pull request.** Open a PR to `develop`:
   - The title follows Conventional Commits and ends with the task ID, for example
     `feat: replace stop rows for the dates in a file (M1-16)`.
   - The description has `Closes #<ISSUE_NUMBER>`.
   - Fill in the checklist from the pull request template (the definition of done).

**Commits:**

- Use Conventional Commits: `feat:`, `fix:`, `test:`, `docs:`, `chore:`, `ci:` and `refactor:`.
- Commits follow the TDD cycle, so the history shows each test before its code.

**Definition of done** (applies to every task from M1-02 onwards; not repeated below; it is
also the checklist in `.github/pull_request_template.md`):

- [ ] All tests pass, with 100 % line coverage.
- [ ] The mutation thresholds are met on changed lines: 100 % covered MSI in `src/Report` and
      `src/Import`, and 80 % in the rest of the mutated code.
- [ ] PHPStan (max level) and coding standards pass.
- [ ] The README, architecture and diagrams are updated if behaviour or design changed, and
      `task docs:diagrams:check` passes.
- [ ] `CHANGELOG.md` has an entry under "Unreleased".

**Sizing:** a task should be reviewable in one sitting, roughly one day of work. If a task grows,
split it and update this file.

## Overview

| ID | Task | Depends on |
| --- | --- | --- |
| **A** | **Setup and quality gates** | |
| M1-01 | Project skeleton | – |
| M1-02 | Test tooling, coverage gate and PR workflow | 01 |
| M1-03 | Mutation testing | 02 |
| M1-04 | Diagram check in CI | 02 |
| **B** | **Security** | |
| M1-05 | Permission roles and deny by default | 02 |
| M1-06 | User entity and user commands | 05 |
| M1-07 | Password login | 06 |
| M1-08 | Authentication modes and no-login mode | 07 |
| M1-09 | HTTP security headers | 05 |
| **C** | **Layout and administration** | |
| M1-10 | Base layout, design system and menu | 05 |
| M1-11 | Formatting extension | 02 |
| M1-12 | User administration | 07, 10 |
| M1-13 | Suppliers, routes and dated assignments | 10 |
| **D** | **Data in** | |
| M1-14 | Import mapping | 02 |
| M1-15 | Extract parser | 14, ADR 004 |
| M1-16 | Replace stop rows for the dates in a file | 13, 15 |
| M1-17 | Build route runs | 16 |
| M1-18 | Aggregate daily stats | 17 |
| M1-19 | Upload page and import command | 18 |
| M1-20 | Clear driving data for a date range | 10, 18 |
| M1-21 | Daily counts | 10 |
| **E** | **Overview** | |
| M1-22 | Periods and filters | 02 |
| M1-23 | Figure calculation | 18, 21, 22 |
| M1-24 | Tile, sparkline and panel components | 10, 11 |
| M1-25 | Overview with the eight tiles | 23, 24 |
| M1-26 | Punctuality chart, supplier table and help texts | 25 |
| M1-27 | Development fixtures | 19, 21 |
| **F** | **Deployment** | |
| M1-28 | Woodpecker deployment | 25 |

**Before M1-01:**

- `develop` is the default branch.
- `develop` and `main` are protected. The checks from M1-02 to M1-04 become required as they
  are added.
- There is a GitHub issue for each task.

**Before M1-15:** [ADR 004](adr/004-personal-data-in-driving-data.md) (personal data) must be
decided. Everything else can continue while it is open.

**Before M1-16:** confirm with the transport office that each extract holds whole days (see
[ADR 003](adr/003-driving-data-import-mapping-and-overwrite.md)).

**Parallel work:** after M1-05, groups C, D and E can run in parallel.

## A. Setup and quality gates

### M1-01 Project skeleton

- **Plan step:** 4. **Implements:** README "Getting started", ADR 001.
- **Depends on:** –
- **Work:**
  - Install the `symfony-8` template, with project name `passenger-transport-performance` and
    domain `passenger-transport-performance.local.itkdev.dk`.
  - Create the Symfony 8 skeleton, restore the compose variables in `.env`, and run
    `composer install`.
  - Add `symfony/orm-pack` with the MariaDB `DATABASE_URL`.
  - Add `ergebnis/composer-normalize` as a dev dependency, and allow it in
    `config.allow-plugins` in `composer.json`.
  - Extend `Taskfile.yml` with the ITK Dev conventions:
    - `dotenv: [".env.local", ".env"]`
    - the vars `DOCKER_COMPOSE`, `PHP`, `COMPOSER` and `CONSOLE`, so that each task uses the
      vars and not a fixed command
    - the tasks `setup`, `compose`, `composer`, `console`, `db:migrate` and `code-analysis`
    - the coding standards tasks `coding-standards-{type}-{check|apply}` for each type in the
      template (for example `php`, `twig`, `markdown` and `yaml`), and
      `coding-standards-check` and `coding-standards-apply`, which run all types
  - Add `.github/pull_request_template.md` with the definition of done as a checklist and a
    `Closes #` line.
  - Add `CHANGELOG.md` (Keep a Changelog).
- **Tests first:** none. There is no application code yet; the test tooling comes in M1-02.
- **Acceptance criteria:**
  - On a fresh clone, `task setup` starts the stack. The site answers at the local URL.
  - `task console -- about` runs the Symfony console.
  - `task coding-standards-check` and `task code-analysis` pass.
  - `task composer -- normalize --dry-run` passes.
  - The template's GitHub Actions workflows pass.
  - A new pull request shows the template.

### M1-02 Test tooling, coverage gate and PR workflow

- **Plan step:** 4. **Implements:** README "Tests", plan "Development approach".
- **Depends on:** M1-01
- **Work:**
  - PHPUnit with `tests/Unit`, `tests/Integration` and `tests/Functional`.
  - pcov in the phpfpm image, and a MariaDB test database with `dama/doctrine-test-bundle`.
  - `zenstruck/foundry` and `doctrine/doctrine-fixtures-bundle`.
  - `rregeer/phpunit-coverage-check` at 100 %. The only exclusions are `src/Kernel.php` and
    `migrations/`.
  - Tasks: `test`, `test:unit`, `test:file`, `test:coverage`, `test:coverage:check` and
    `prepare-code`.
  - A PR workflow running coding standards, `composer normalize --dry-run`, PHPStan and tests
    with the coverage gate, with a MariaDB service container.
  - Dependency checks (ASVS V14.2.1):
    - The PR workflow runs `composer audit`. It fails if a dependency has a known
      vulnerability.
    - `.github/dependabot.yml` opens update PRs to `develop` for `composer` and
      `github-actions`, once a week.
- **Tests first:** a functional smoke test: `GET /` returns 200. It changes to a redirect to
  `/login` in M1-05.
- **Acceptance criteria:**
  - `task test:coverage:check` passes at 100 % and fails if a line is uncovered. Prove it once
    with a throwaway uncovered method.
  - The PR workflow runs on pull requests to `develop` and `main`.
  - The `composer audit` step fails on a dependency with a known vulnerability. Prove it once
    in a scratch branch.
  - Dependabot shows the configuration as valid on GitHub.

### M1-03 Mutation testing

- **Plan step:** 4. **Implements:** plan "Mutation testing".
- **Depends on:** M1-02
- **Work:**
  - `infection/infection` with `infection.json5`.
  - `src/Controller`, `src/Form`, `src/Twig/Components` and `src/Kernel.php` are not mutated.
  - Thresholds: covered MSI 100 % for `src/Report` and `src/Import`, 80 % for the rest.
  - `task test:mutation` (changed lines against `develop`) and `task test:mutation:full`.
  - The PR workflow gets a changed-lines job.
  - `.github/workflows/mutation-full.yml` runs on push to `release/*` and `hotfix/*`, and on
    their PRs to `main`.
- **Tests first:** none of its own. Prove the gate with a deliberately weak test in a scratch
  commit, so that a mutant survives and the job fails.
- **Acceptance criteria:**
  - Both tasks run locally.
  - The PR job fails when a mutant survives in changed lines.
  - The full run triggers only on release and hotfix branches.

### M1-04 Diagram check in CI

- **Plan step:** 4. **Implements:** architecture "Diagrams".
- **Depends on:** M1-02
- **Work:** a CI job that runs `task docs:diagrams:check`. Install go-task in the job; Docker is
  available on GitHub runners.
- **Tests first:** none (CI configuration).
- **Acceptance criteria:**
  - The job fails when a `.mmd` is changed without re-rendering its SVG.
  - It passes when the SVG is up to date.

## B. Security

### M1-05 Permission roles and deny by default

- **Plan step:** 5. **Implements:** README "Roles", ADR 002, architecture "Security".
- **Depends on:** M1-02
- **Work:**
  - `security-bundle` with the permission roles from the architecture: `ROLE_VIEW_OVERVIEW`,
    `ROLE_UPLOAD_DRIVING_DATA`, `ROLE_CLEAR_DRIVING_DATA`, `ROLE_EDIT_DAILY_COUNTS`,
    `ROLE_MANAGE_SUPPLIERS` and `ROLE_MANAGE_USERS`.
  - `role_hierarchy`: `ROLE_ADMIN` gives all permission roles.
  - `access_control`: `^/login` and assets are public, and everything else requires `ROLE_USER`.
  - A placeholder `OverviewController` with `#[IsGranted('ROLE_VIEW_OVERVIEW')]`.
  - A temporary in-memory user for tests until M1-06.
- **Tests first:**
  - **Route guard (functional):** iterates every route in the router, excluding framework and
    profiler routes. It fails if a controller class or action has no `IsGranted`, or if it
    checks `ROLE_ADMIN` or `ROLE_USER`.
  - **Anonymous access (functional):** `GET /` redirects to `/login`. This replaces the M1-02
    smoke test.
  - **Signed-in access (functional):**
    - A user with `ROLE_VIEW_OVERVIEW` gets 200 on `/`.
    - A user with only `ROLE_USER` gets 403.
    - A user with `ROLE_ADMIN` gets 200.
- **Acceptance criteria:** a new route without a permission role makes the route-guard test
  fail.

### M1-06 User entity and user commands

- **Plan step:** 5. **Implements:** README "Manage users" (commands), ADR 002.
- **Depends on:** M1-05
- **Work:**
  - `User` entity (`username`, `roles`, `password_hash`) and a migration.
  - Doctrine entity user provider.
  - `app:user:create <USERNAME> [--role=<ROLE>]`, which prompts for the password.
  - `app:user:set-password <USERNAME>`.
  - `app:user:set-roles <USERNAME> <ROLE>...`.
  - A password policy (ASVS V2.1), as a constraint on a plain password. All places that set a
    password use it:
    - at least 12 characters
    - `NotCompromisedPassword`, which checks the password against known breaches
  - The display name waits for the staff data decision in ADR 004.
- **Tests first:**
  - **Unit:** role validation (only `ROLE_ADMIN` and the permission roles), and username
    normalisation.
  - **Integration (`CommandTester`):**
    - Each command succeeds.
    - A duplicate username is rejected.
    - An unknown user gives an error.
    - An unknown role is rejected.
    - A password with 11 characters is rejected. A password with 12 characters is accepted.
    - A breached password is rejected. The test replaces the breach check with a stub, so it
      makes no network call.
    - The password is hashed, never stored in clear text.
- **Acceptance criteria:** the commands work as documented in the README.

### M1-07 Password login

- **Plan step:** 5. **Implements:** README "Getting started" step 4, ADR 002.
- **Depends on:** M1-06
- **Work:**
  - `PasswordAuthenticator` and `SecurityController` (`/login`, `/logout`).
  - The login form with CSRF.
  - Login throttling (`symfony/rate-limiter`) and remember-me.
  - Remove the temporary in-memory user.
- **Tests first (functional):**
  - Valid credentials sign in and redirect to `/`.
  - A wrong password shows an error.
  - Repeated failures are throttled.
  - A missing or invalid CSRF token is rejected.
  - Logout ends the session.
  - Remember-me survives a new session.
- **Acceptance criteria:** a user created with `app:user:create` can sign in, and can see `/`
  only after signing in.

### M1-08 Authentication modes and no-login mode

- **Plan step:** 5. **Implements:** README "Configuration", ADR 002, the auth-modes diagram.
- **Depends on:** M1-07
- **Work:**
  - `APP_AUTH_MODE` (`password` by default). Each authenticator's `supports()` checks the mode.
  - `NoLoginAuthenticator` with `APP_NO_LOGIN_ROLES` (default `ROLE_VIEW_OVERVIEW`).
  - A kernel or container check that refuses to start in `prod` with `none` unless
    `APP_ALLOW_NO_LOGIN_IN_PROD=1`.
  - Login routes return 404 when the mode is not `password`.
  - `oidc` fails with a clear "not implemented" error at boot.
- **Tests first:**
  - **Unit:** `supports()` per mode, and the prod guard for each combination of environment and
    flag.
  - **Functional, `none` mode:** with `APP_NO_LOGIN_ROLES=ROLE_VIEW_OVERVIEW`, a page that
    requires another permission role gives 403. With `ROLE_ADMIN`, it gives 200.
  - **Functional, `/login`:** returns 404 in `none` mode.
- **Acceptance criteria:**
  - The three modes behave as described in the README.
  - The route-guard test still passes.

### M1-09 HTTP security headers

- **Plan step:** 5. **Implements:** architecture "Security" (HTTP security headers, ASVS V14.4).
- **Depends on:** M1-05
- **Work:**
  - `nelmio/security-bundle`, configured with the headers in the architecture:
    `Strict-Transport-Security` (in `prod` only), `Content-Security-Policy`,
    `X-Content-Type-Options` and `Referrer-Policy`.
  - The configuration is in the repository.
- **Tests first (functional):**
  - A response has each header with the value from the architecture.
  - `Strict-Transport-Security` is not sent outside `prod`.
  - The headers are also on the login page and on error pages.
- **Acceptance criteria:**
  - Every response has the headers.
  - Templates have no inline scripts, no inline event handlers and no `style` attributes. Later
    tasks keep to this.

## C. Layout and administration

### M1-10 Base layout, design system and menu

- **Plan step:** 6. **Implements:** README "Features", architecture "User interface".
- **Depends on:** M1-05
- **Work:**
  - `itk-dev/itkaarhus-design-system` (vcs repository), mapped in AssetMapper as `itkaarhus-ds`.
  - Port the prototype's shell to `templates/base.html.twig`:
    - side navigation and a top bar with a user menu
    - a mobile navigation drawer
    - an "about the figures" `<dialog>` with placeholder text
  - Port the prototype's stylesheet to `assets/styles/app.css`.
  - Danish translations in `translations/messages+intl-icu.da.yaml`.
  - The menu shows each item only to users with its permission role: overview, upload, clear
    driving data, daily counts and administration.
- **Tests first (functional):**
  - A user with only `ROLE_VIEW_OVERVIEW` sees only the overview in the menu.
  - A user with `ROLE_UPLOAD_DRIVING_DATA` also sees "upload".
  - A user with `ROLE_ADMIN` sees all items.
  - The active menu item is marked.
  - The page has `lang="da"`.
- **Acceptance criteria:**
  - The overview placeholder renders in the new layout.
  - The layout works at phone width.
  - Assets load without a Node build.

### M1-11 Formatting extension

- **Plan step:** 6. **Implements:** architecture "User interface".
- **Depends on:** M1-02
- **Work:** `FormatExtension` with Twig filters for:
  - numbers, percent, percentage points, and signed deltas (with a real minus sign)
  - kilometres
  - Danish period labels for day, week (ISO week), month and year, including partial periods
  - "–" for missing values (null, NaN, division by zero)
- **Tests first (unit):** a data-provider test per filter, covering boundaries:
  - the switch between whole numbers and one decimal
  - negative values, zero and NaN
  - ISO week 53, and periods across years
- **Acceptance criteria:** every format matches the prototype's formatting.

### M1-12 User administration

- **Plan step:** 5–6. **Implements:** README "Manage users".
- **Depends on:** M1-07, M1-10
- **Work:**
  - `Admin\UserController` with `#[IsGranted('ROLE_MANAGE_USERS')]`: list, create, edit roles,
    set password and delete.
  - `UserType` form. The roles are `ROLE_ADMIN` and the permission roles. A password that is
    set in the form uses the password policy from M1-06.
  - A user cannot remove their own `ROLE_MANAGE_USERS` (directly or through `ROLE_ADMIN`), and
    the last user with `ROLE_MANAGE_USERS` cannot be deleted.
- **Tests first (functional):**
  - Each action works for a user with `ROLE_MANAGE_USERS` and returns 403 for a user without it.
  - Validation errors are shown.
  - A password that does not agree with the password policy is rejected.
  - CSRF on delete.
  - The self-demotion and last-user rules.
- **Acceptance criteria:** users can be managed in the UI, as in the README.

### M1-13 Suppliers, routes and dated assignments

- **Plan step:** 7. **Implements:** README "Map routes to suppliers", the data model.
- **Depends on:** M1-10
- **Work:**
  - `Supplier`, `Route` and `RouteSupplierAssignment` entities and migrations. An assignment has
    a `valid_from` date and an optional `valid_to` date.
  - All controllers have `#[IsGranted('ROLE_MANAGE_SUPPLIERS')]`.
  - `Admin\SupplierController`: list, create, edit and delete. Deleting a supplier with
    assignments is blocked.
  - `Admin\RouteController`: list with routes that have no current assignment first, and the
    assignments of each route.
  - `SupplierType`, `RouteType` and `RouteSupplierAssignmentType` forms. The assignments of one
    route must not overlap.
  - A `RouteSupplierChanged` event with the route and the old and new date ranges. It is
    dispatched now; the rebuild listener comes in M1-18.
- **Tests first:**
  - **Unit:** the overlap check: touching ranges, an open end, and the same start date.
  - **Integration:** the repository finds the supplier for a route and a date, and lists routes
    without a current assignment first.
  - **Functional:**
    - CRUD with and without `ROLE_MANAGE_SUPPLIERS`.
    - An overlapping assignment is rejected.
    - A supplier with assignments cannot be deleted.
    - Adding, changing and deleting an assignment dispatches the event with the correct ranges.
- **Acceptance criteria:** an administrator can create suppliers and assign routes for a date
  range. A route on a date with no assignment is shown as "Unknown".

## D. Data in

### M1-14 Import mapping

- **Plan step:** 7. **Implements:** README "Import mappings", ADR 003.
- **Depends on:** M1-02
- **Work:**
  - The YAML format in `config/import/driving_data/<MAPPING_NAME>.yaml`.
  - `MappingLoader`, which validates with the Symfony Config `TreeBuilder` and builds an
    immutable `Mapping` value object:
    - the sheet and header row
    - an optional row key (one or more columns)
    - for each field: the column, type, format, value map, true values and whether it is required
  - Validation runs when the container is built.
  - `APP_DRIVING_DATA_MAPPING`.
  - `app:driving-data:mapping:validate <MAPPING_NAME> <PATH_TO_FILE>`, which reports missing
    columns and unmapped values, and previews rows.
  - A `default.yaml` with placeholder columns until a real extract is available.
- **Tests first:**
  - **Unit:**
    - A valid mapping loads into `Mapping`, with and without a row key.
    - The loader rejects each kind of invalid mapping: a missing required field, an unknown
      type, a row key column that is not a field, and a value map on a non-string field.
  - **Integration:** the validate command, using fixture files in `tests/fixtures/mappings/`.
- **Acceptance criteria:**
  - An invalid mapping stops the application from starting, with a clear message.
  - The validate command works as in the README.

### M1-15 Extract parser

- **Plan step:** 7. **Implements:** ADR 003, ADR 004 (as decided).
- **Depends on:** M1-14, and **ADR 004 decided**
- **Work:**
  - `StopRowParser` reads an `.xlsx` with `openspout/openspout`, only through `Mapping`.
    OpenSpout streams the sheet one row at a time, so the memory use does not grow with the
    file size.
  - It converts each field by type: string, int, float, bool with true values, and datetime
    (a date cell, an Excel serial or a format string, in Europe/Copenhagen).
  - It applies value maps.
  - The output is `StopRowData` value objects, or a `RejectedRow` with the row number and a
    reason. The reason never includes field values.
  - Person id handling follows ADR 004, for example HMAC with `APP_PERSON_ID_SECRET`.
  - Limits on untrusted files (ASVS V12.1.2, V5.5.2):
    - A maximum file size, number of rows and number of columns. They are configurable, with
      defaults well above the size of a normal extract.
    - The parser counts the columns in the header row and the rows while it streams. It stops
      at the first row or column above a limit, and rejects the whole file. It reads cell
      values only, not formatting.
    - The parser rejects XML with a `DOCTYPE`, so there are no external entities (XXE). First
      find out if OpenSpout rejects a `DOCTYPE` itself. If it does not, the parser checks the
      sheet XML in the file before OpenSpout reads it. A test keeps this so after upgrades.
- **Tests first (unit):**
  - The anonymised one-day fixture and small hand-made workbooks give the expected rows.
  - Every type conversion, including bad values.
  - Required fields that are missing.
  - Unknown codes that aren't in a value map.
  - Rejected-row reasons contain no values.
  - The same person id with the same secret gives the same key. A different secret gives a
    different key.
  - A file with one row or one column more than the limit is rejected, and no rows are
    returned.
  - A workbook with a `DOCTYPE` and an external entity in a sheet is rejected.
  - A file that is not a valid `.xlsx` (for example a renamed text file) is rejected.
- **Acceptance criteria:**
  - The fixture parses with exactly the expected rows and rejections.
  - Files above the limits and files with a `DOCTYPE` are rejected with a clear reason.

### M1-16 Replace stop rows for the dates in a file

- **Plan step:** 7. **Implements:** README "Upload driving data", ADR 003.
- **Depends on:** M1-13, M1-15
- **Work:**
  - `StopRow` and `ImportBatch` entities and migrations. `StopRow` has an optional row key, with
    a unique index on `(mapping_name, row_key)`.
  - `StopRowReplacer`:
    - The import dates are the dates with at least one valid row in the file.
    - For each import date, it deletes all `StopRow` rows, from all mappings, and then inserts
      the rows of the file.
    - If the mapping has a row key, a key that occurs more than once in the file is rejected
      after the first occurrence.
    - It returns the import dates and the deleted, inserted and rejected counts.
  - `ImportBatch` keeps the mapping, the file name, who and when, the import dates, the counts
    and the rejection reasons.
  - Routes not seen before are created without an assignment ("Unknown").
- **Tests first (integration):**
  - Importing the fixture inserts every row.
  - Importing it again gives the same rows, with the same row count.
  - A corrected file with one row less removes that row.
  - Rows on dates that are not in the file do not change.
  - Rows from another mapping on an import date are deleted.
  - A duplicate row key in one file is rejected after the first occurrence.
  - New routes are created without an assignment.
  - The import dates and the counts are correct.
- **Acceptance criteria:** after an import, each import date holds exactly the rows of the file.
  The same data can never be counted twice.

### M1-17 Build route runs

- **Plan step:** 7. **Implements:** README "Key figures" (definitions), architecture "Pipeline".
- **Depends on:** M1-16
- **Work:** `RouteBuilder` replaces the `RouteRun` rows for the given dates. For each route and
  date, it orders stops by planned time and splits them into runs at the end-of-route flag. Per
  run it calculates:
  - the last drop-off, planned and actual
  - the delay in minutes
  - the peak passengers
  - whether the run was cancelled
  - driven and empty km and minutes (empty means passengers after the stop = 0)
- **Tests first (unit, with small in-memory stop lists):**
  - Several runs per route and day.
  - A run with no end flag at the end of the day.
  - A fully cancelled run.
  - Delay: on time at exactly the tolerance, late at one minute more.
  - Peak passengers 0, 1 and 2.
  - The empty distance sum.
  - A missing actual time.
- **Acceptance criteria:** the route runs from the fixture match the totals worked out by hand.

### M1-18 Aggregate daily stats

- **Plan step:** 7. **Implements:** architecture "Data model" (derived tables).
- **Depends on:** M1-17
- **Work:**
  - The `DailyStats` entity and migration, with a unique index on `(service_date, supplier_id)`.
  - `DailyStatsAggregator` replaces the rows for the given dates. It finds the supplier for each
    route and date from the assignments. Counters: trips, completed, cancelled, route runs,
    last drop-offs, on time, solo runs, and driven and empty km and minutes.
  - A listener on `RouteSupplierChanged` rebuilds only the dates in the old and the new range.
  - The whole import (replacement, runs, stats) runs in one transaction.
- **Tests first:**
  - **Unit:** the counters from the route runs.
  - **Integration:**
    - Rebuilding the same dates twice gives identical rows.
    - A route on a date with no assignment counts as "Unknown".
    - An assignment change moves the counts for the dates in its ranges only. Other dates do
      not change.
    - A failure part-way leaves the previous data untouched.
- **Acceptance criteria:** the fixture's daily stats match the totals worked out by hand.

### M1-19 Upload page and import command

- **Plan step:** 7. **Implements:** README "Upload driving data".
- **Depends on:** M1-18
- **Work:**
  - `ImportController` (`ROLE_UPLOAD_DRIVING_DATA`) with `ImportType`: `.xlsx` only, with the
    size limit from M1-15. The form checks the MIME type and the extension.
  - The import runs synchronously in the request. This is accepted in milestone 1, because the
    limits from M1-15 keep the time and memory low, and only users with
    `ROLE_UPLOAD_DRIVING_DATA` can upload. If real extracts are too slow, a later task moves
    the import to Symfony Messenger.
  - The result page shows the replaced dates and the rows deleted, inserted and rejected, with
    the reasons.
  - The uploaded file is deleted after processing.
  - `app:driving-data:import <PATH_TO_FILE> [--mapping=<MAPPING_NAME>]` uses the same service.
- **Tests first:**
  - **Functional:**
    - A user with `ROLE_UPLOAD_DRIVING_DATA` uploads the fixture and sees the dates and counts.
    - A user without it gets 403.
    - A wrong file type and an oversized file are rejected.
    - A file above the row limit is rejected, and no data changes.
    - The temporary file is removed, also after a rejected file.
  - **Integration:** the command imports, and prints the same dates and counts.
- **Acceptance criteria:**
  - The upload and the command behave as in the README.
  - Uploading the same file twice gives the same data.

### M1-20 Clear driving data for a date range

- **Plan step:** 7. **Implements:** README "Clear driving data", ADR 003, architecture "Clearing
  driving data".
- **Depends on:** M1-10, M1-18
- **Work:**
  - `DrivingDataClearer` deletes `StopRow`, `RouteRun` and `DailyStats` for a date range, in one
    transaction. `DailyCount` does not change.
  - It logs who cleared which range, and the deleted counts. The log has no field values.
  - `ClearDrivingDataController` (`ROLE_CLEAR_DRIVING_DATA`):
    - The user selects the first and the last date.
    - The page shows the number of stop rows and route runs in the range.
    - The user confirms. The form has CSRF.
  - `app:driving-data:clear <FROM_DATE> <TO_DATE>` uses the same service, and asks for
    confirmation unless `--no-interaction` is given.
- **Tests first:**
  - **Integration:**
    - The rows in the range are deleted. Rows before and after the range do not change.
    - `DailyCount` rows in the range do not change.
    - A failure part-way leaves the data untouched.
    - The command deletes the range and prints the counts.
  - **Functional:**
    - The page shows the correct counts before confirmation.
    - A user without `ROLE_CLEAR_DRIVING_DATA` gets 403.
    - A missing CSRF token is rejected.
    - A range with the last date before the first date is rejected.
- **Acceptance criteria:** clearing behaves as in the README.

### M1-21 Daily counts

- **Plan step:** 8. **Implements:** README "Enter daily counts".
- **Depends on:** M1-10
- **Work:**
  - The `DailyCount` entity and migration, unique per date.
  - `DailyCountController` (`ROLE_EDIT_DAILY_COUNTS`) with `DailyCountWeekType`: seven days with
    mails, complaints and phone calls.
  - Previous and next week navigation.
  - Validation: integers ≥ 0, no future dates.
  - Saving updates the existing rows.
- **Tests first:**
  - **Unit:** the week calculation, including ISO weeks across years.
  - **Functional:**
    - Saving a week creates seven rows.
    - Editing a day updates it without creating a duplicate.
    - Negative numbers and future dates are rejected.
    - A user without `ROLE_EDIT_DAILY_COUNTS` gets 403.
- **Acceptance criteria:** daily counts can be entered and corrected as in the README.

## E. Overview

### M1-22 Periods and filters

- **Plan step:** 9. **Implements:** README "Filters", architecture "Calculating the figures".
- **Depends on:** M1-02
- **Work:**
  - `PeriodFilter`, a `#[MapQueryString]` DTO with `p`, `d`, `c` and `s`. Invalid values fall
    back to the defaults.
  - `PeriodRangeFactory`:
    - the range for day, week (ISO, Monday to Sunday), month and year
    - previous and next navigation
    - the comparison range (the previous period, or the same period last year)
    - partial periods, where the comparison is cut to the same number of days
    - the 12 trailing months
    - clamping to the range of available data
- **Tests first (unit):**
  - Each period type.
  - ISO week 1 and week 53.
  - Leap years.
  - Comparisons against 29 February.
  - A partial current month against a full previous month.
  - The same period last year.
  - Navigation at the edges of the data.
  - The trailing months across a year boundary.
  - Invalid query values.
- **Acceptance criteria:** the date ranges match the definitions in the README.

### M1-23 Figure calculation

- **Plan step:** 9. **Implements:** README "Key figures", architecture "Calculating the
  figures".
- **Depends on:** M1-18, M1-21, M1-22
- **Work:**
  - `KpiCalculator` runs one aggregate query over `DailyStats` and `DailyCount` per range, with
    an optional supplier.
  - It counts distinct citizens as decided in ADR 004.
  - The result is a `Kpis` value object with the eight figures and their secondary values: solo
    share, and complaints per 1,000 trips.
  - `Delta` gives the value, the percentage and the tone (better, worse or neutral, by
    direction).
  - `Tone` gives ok, warn or danger from `config/packages/app_thresholds.yaml`.
- **Tests first:**
  - **Unit:**
    - Ratios are calculated from sums, not averaged.
    - A zero denominator gives no value.
    - The `Delta` direction for each figure.
    - The `Tone` boundaries.
  - **Integration:**
    - Known `DailyStats` and `DailyCount` rows give the expected `Kpis`.
    - The supplier filter applies to driving figures only.
    - Distinct citizens are counted over several days.
- **Acceptance criteria:** the figures for the fixture day match the totals worked out by hand.

### M1-24 Tile, sparkline and panel components

- **Plan step:** 9. **Implements:** architecture "User interface".
- **Depends on:** M1-10, M1-11
- **Work:** Twig components (`symfony/ux-twig-component`), ported from the prototype:
  - `Tile`: label, value, tone, delta chip, sparkline, link and a `?` help popover
  - `Sparkline`: inline SVG, with gaps for missing months
  - `Panel`: title, body and a source footer
- **Tests first:**
  - **Unit:** the sparkline geometry (points, gaps, a flat series).
  - **Functional, rendering components in a test template:**
    - The tone and delta classes.
    - The help popover markup.
    - The hidden data table for screen readers.
    - The "–" value for missing data.
- **Acceptance criteria:** the components render like the prototype's tiles and panels.

### M1-25 Overview with the eight tiles

- **Plan step:** 9. **Implements:** README "Features" (overview), "Key figures", "Filters".
- **Depends on:** M1-23, M1-24
- **Work:**
  - `OverviewController` (`ROLE_VIEW_OVERVIEW`).
  - The top-bar filters: a segmented control for the period, a previous/next stepper, the
    comparison select and the supplier select. All are GET links or forms.
  - The eight tiles, each with the current value, the delta and a 12-month sparkline.
  - Daily-count tiles say "whole office" when a supplier is selected.
- **Tests first (functional):**
  - With fixture data, each tile shows the expected value. Use `data-test` attributes, not
    markup.
  - The filters change the values, and invalid query values fall back to the defaults.
  - A user with `ROLE_VIEW_OVERVIEW` sees the page. Anonymous visitors are redirected.
- **Acceptance criteria:**
  - The overview works end to end: upload, enter counts, see the figures.
  - Views can be shared as links.

### M1-26 Punctuality chart, supplier table and help texts

- **Plan step:** 9. **Implements:** README "Features" (overview), "Key figures".
- **Depends on:** M1-25
- **Work:**
  - The `LineChart` component: inline SVG, a target line, gridlines and a hidden data table.
    It uses classes and SVG presentation attributes, not `style` attributes.
  - The 12-month punctuality chart with `APP_PUNCTUALITY_TARGET`.
  - The supplier table for the driving figures, always showing all suppliers.
  - Help texts for each figure, and the "about the figures" dialog content.
- **Tests first:**
  - **Unit:** the `LineChart` scales, the target line and missing months.
  - **Functional:**
    - The table lists every supplier, and "Unknown" if there are routes without an assignment
      on dates in the period.
    - The values per supplier.
    - Every tile has a help text.
- **Acceptance criteria:** the overview matches the README's feature list.

### M1-27 Development fixtures

- **Plan step:** 4 and 9. **Implements:** README "Fixtures".
- **Depends on:** M1-19, M1-21
- **Work:**
  - `AppFixtures`:
    - a user with `ROLE_ADMIN`, and a user for each permission role (with known development
      passwords)
    - suppliers, routes and assignments, with one supplier change during the period
    - an imported sample day (run through the real import service)
    - daily counts
  - `task fixtures:load`.
- **Tests first (integration):** loading the fixtures gives the expected counts, and the overview
  shows non-empty figures.
- **Acceptance criteria:** `task fixtures:load` gives a usable local site in one command.

## F. Deployment

### M1-28 Woodpecker deployment

- **Plan step:** 10. **Implements:** README "Deployment".
- **Depends on:** M1-25
- **Work:**
  - `.woodpecker/stg.yml`: push to `release/*` or `hotfix/*`.
  - `.woodpecker/prod.yml`: the `release` event.
  - Both use `itkdev/ansible-plugin` with the steps in the README.
  - The server compose files from the template.
  - Document the server's `.env.local` variables.
- **Tests first:** none (deployment configuration).
- **Acceptance criteria:**
  - A push to a `release/*` branch deploys to staging.
  - The first admin can be created on the server and can sign in.
  - The release checklist in the README works end to end.
