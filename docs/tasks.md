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
2. **Branch.** Create `feature/<TASK_ID>-<SHORT_DESCRIPTION>` from `develop`, for example
   `feature/M1-15-upsert-stop-rows`.
3. **Tests first.** Write the tests listed under **Tests first** and see them fail.
4. **Implement.** Write the minimal code to make them pass, then refactor.
5. **Pull request.** Open a PR to `develop`. Name the task in the title, for example
   `M1-15: Upsert stop rows by row key`.

**Commits:**

- Use Conventional Commits: `feat:`, `fix:`, `test:`, `docs:`, `chore:`, `ci:` and `refactor:`.
- Commits follow the TDD cycle, so the history shows each test before its code.

**Definition of done** (applies to every task from M1-02 onwards; not repeated below):

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
|---|---|---|
| **A** | **Setup and quality gates** | |
| M1-01 | Project skeleton | – |
| M1-02 | Test tooling, coverage gate and PR workflow | 01 |
| M1-03 | Mutation testing | 02 |
| M1-04 | Diagram check in CI | 02 |
| **B** | **Security** | |
| M1-05 | Role hierarchy and deny by default | 02 |
| M1-06 | User entity and user commands | 05 |
| M1-07 | Password login | 06 |
| M1-08 | Authentication modes and no-login mode | 07 |
| **C** | **Layout and administration** | |
| M1-09 | Base layout, design system and menu | 05 |
| M1-10 | Formatting extension | 02 |
| M1-11 | User administration | 07, 09 |
| M1-12 | Suppliers and routes | 09 |
| **D** | **Data in** | |
| M1-13 | Import mapping | 02 |
| M1-14 | Extract parser | 13, ADR 004 |
| M1-15 | Upsert stop rows by row key | 12, 14 |
| M1-16 | Build route runs | 15 |
| M1-17 | Aggregate daily stats | 16 |
| M1-18 | Upload page and import command | 17 |
| M1-19 | Daily counts | 09 |
| **E** | **Overview** | |
| M1-20 | Periods and filters | 02 |
| M1-21 | Figure calculation | 17, 19, 20 |
| M1-22 | Tile, sparkline and panel components | 09, 10 |
| M1-23 | Overview with the eight tiles | 21, 22 |
| M1-24 | Punctuality chart, supplier table and help texts | 23 |
| M1-25 | Development fixtures | 18, 19 |
| **F** | **Deployment** | |
| M1-26 | Woodpecker deployment | 23 |

**Before M1-01:** `develop` is the default branch, and `develop` and `main` are protected, with
the checks from M1-02 to M1-04 made required as they are added.

**Before M1-14:** [ADR 004](adr/004-personal-data-in-driving-data.md) (personal data) must be
decided. Everything else can continue while it is open.

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
  - Extend `Taskfile.yml` with `setup`, `compose`, `composer`, `phpfpm`, `db:migrate`, and the
    coding standards and code analysis tasks.
  - Add `CHANGELOG.md` (Keep a Changelog).
- **Tests first:** none. There is no application code yet; the test tooling comes in M1-02.
- **Acceptance criteria:**
  - On a fresh clone, `task setup` starts the stack. The site answers at the local URL.
  - `task coding-standards:check` and `task code-analysis` pass.
  - The template's GitHub Actions workflows pass.

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
  - A PR workflow running coding standards, PHPStan and tests with the coverage gate, with a
    MariaDB service container.
- **Tests first:** a functional smoke test: `GET /` returns 200. It changes to a redirect to
  `/login` in M1-05.
- **Acceptance criteria:**
  - `task test:coverage:check` passes at 100 % and fails if a line is uncovered. Prove it once
    with a throwaway uncovered method.
  - The PR workflow runs on pull requests to `develop` and `main`.

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

### M1-05 Role hierarchy and deny by default

- **Plan step:** 5. **Implements:** README "Roles", ADR 002, architecture "Security".
- **Depends on:** M1-02
- **Work:**
  - `security-bundle` with the role hierarchy `ROLE_ADMIN` > `ROLE_EDITOR` > `ROLE_USER`.
  - `access_control`: `^/login` and assets are public, and everything else requires `ROLE_USER`.
  - A placeholder `OverviewController` with `#[IsGranted('ROLE_USER')]`.
  - A temporary in-memory user for tests until M1-06.
- **Tests first:**
  - **Route guard (functional):** iterates every route in the router, excluding framework and
    profiler routes. It fails if a controller class or action has no `IsGranted`.
  - **Anonymous access (functional):** `GET /` redirects to `/login`. This replaces the M1-02
    smoke test.
  - **Signed-in access (functional):** a signed-in `ROLE_USER` gets 200 on `/`.
- **Acceptance criteria:** a new route without a role makes the route-guard test fail.

### M1-06 User entity and user commands

- **Plan step:** 5. **Implements:** README "Manage users" (commands), ADR 002.
- **Depends on:** M1-05
- **Work:**
  - `User` entity (`username`, `roles`, `password_hash`) and a migration.
  - Doctrine entity user provider.
  - `app:user:create <USERNAME> [--role=<ROLE>]`, which prompts for the password.
  - `app:user:set-password <USERNAME>`.
  - `app:user:set-roles <USERNAME> <ROLE>...`.
  - The display name waits for the staff data decision in ADR 004.
- **Tests first:**
  - **Unit:** role validation (only known roles), and username normalisation.
  - **Integration (`CommandTester`):**
    - Each command succeeds.
    - A duplicate username is rejected.
    - An unknown user gives an error.
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
  - `NoLoginAuthenticator` with `APP_NO_LOGIN_ROLES`.
  - A kernel or container check that refuses to start in `prod` with `none` unless
    `APP_ALLOW_NO_LOGIN_IN_PROD=1`.
  - Login routes return 404 when the mode is not `password`.
  - `oidc` fails with a clear "not implemented" error at boot.
- **Tests first:**
  - **Unit:** `supports()` per mode, and the prod guard for each combination of environment and
    flag.
  - **Functional, `none` mode:** with `APP_NO_LOGIN_ROLES=ROLE_USER`, an editor page gives 403.
    With `ROLE_ADMIN`, it gives 200.
  - **Functional, `/login`:** returns 404 in `none` mode.
- **Acceptance criteria:**
  - The three modes behave as described in the README.
  - The route-guard test still passes.

## C. Layout and administration

### M1-09 Base layout, design system and menu

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
  - The menu shows items by role: overview; upload and daily counts for editors; administration
    for admins.
- **Tests first (functional):**
  - A `ROLE_USER` sees only the overview in the menu.
  - An editor also sees "upload" and "daily counts".
  - An admin also sees "administration".
  - The active menu item is marked.
  - The page has `lang="da"`.
- **Acceptance criteria:**
  - The overview placeholder renders in the new layout.
  - The layout works at phone width.
  - Assets load without a Node build.

### M1-10 Formatting extension

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

### M1-11 User administration

- **Plan step:** 5–6. **Implements:** README "Manage users".
- **Depends on:** M1-07, M1-09
- **Work:**
  - `Admin\UserController` with `#[IsGranted('ROLE_ADMIN')]`: list, create, edit roles, set
    password and delete.
  - `UserType` form.
  - An admin cannot remove their own admin role, and the last admin cannot be deleted.
- **Tests first (functional):**
  - Each action works for an admin and returns 403 for an editor.
  - Validation errors are shown.
  - CSRF on delete.
  - The self-demotion and last-admin rules.
- **Acceptance criteria:** users can be managed in the UI, as in the README.

### M1-12 Suppliers and routes

- **Plan step:** 7. **Implements:** README "Map routes to suppliers", the data model.
- **Depends on:** M1-09
- **Work:**
  - `Supplier` and `Route` entities and migrations.
  - `Admin\SupplierController`: list, create, edit and delete. Deleting a supplier with routes is
    blocked.
  - `Admin\RouteController`: list with unmapped routes first, and assigning a supplier.
  - `RouteType` and `SupplierType` forms.
  - A `RouteSupplierChanged` event. It is dispatched now; the rebuild listener comes in M1-17.
- **Tests first:**
  - **Integration:** the repository lists unmapped routes first.
  - **Functional:**
    - CRUD per role.
    - A supplier with routes cannot be deleted.
    - Assigning a supplier dispatches the event.
- **Acceptance criteria:** an admin can create suppliers and assign routes. Unmapped routes are
  shown as "Unknown".

## D. Data in

### M1-13 Import mapping

- **Plan step:** 7. **Implements:** README "Import mappings", ADR 003.
- **Depends on:** M1-02
- **Work:**
  - The YAML format in `config/import/driving_data/<MAPPING_NAME>.yaml`.
  - `MappingLoader`, which validates with the Symfony Config `TreeBuilder` and builds an
    immutable `Mapping` value object:
    - the sheet and header row
    - the row key columns
    - for each field: the column, type, format, value map, true values and whether it is required
  - Validation runs when the container is built.
  - `APP_DRIVING_DATA_MAPPING`.
  - `app:driving-data:mapping:validate <MAPPING_NAME> <PATH_TO_FILE>`, which reports missing
    columns and unmapped values, and previews rows.
  - A `default.yaml` with placeholder columns until a real extract is available.
- **Tests first:**
  - **Unit:**
    - A valid mapping loads into `Mapping`.
    - The loader rejects each kind of invalid mapping: a missing required field, an unknown
      type, an empty row key, and a value map on a non-string field.
  - **Integration:** the validate command, using fixture files in `tests/fixtures/mappings/`.
- **Acceptance criteria:**
  - An invalid mapping stops the application from starting, with a clear message.
  - The validate command works as in the README.

### M1-14 Extract parser

- **Plan step:** 7. **Implements:** ADR 003, ADR 004 (as decided).
- **Depends on:** M1-13, and **ADR 004 decided**
- **Work:**
  - `StopRowParser` reads an `.xlsx` with `phpoffice/phpspreadsheet`, only through `Mapping`.
  - It converts each field by type: string, int, float, bool with true values, and datetime
    (Excel serial or a format string, in Europe/Copenhagen).
  - It applies value maps.
  - The output is `StopRowData` value objects, or a `RejectedRow` with the row number and a
    reason. The reason never includes field values.
  - Person id handling follows ADR 004, for example HMAC with `APP_PERSON_ID_SECRET`.
- **Tests first (unit):**
  - The anonymised one-day fixture and small hand-made workbooks give the expected rows.
  - Every type conversion, including bad values.
  - Required fields that are missing.
  - Unknown codes that aren't in a value map.
  - Rejected-row reasons contain no values.
  - The same person id with the same secret gives the same key. A different secret gives a
    different key.
- **Acceptance criteria:** the fixture parses with exactly the expected rows and rejections.

### M1-15 Upsert stop rows by row key

- **Plan step:** 7. **Implements:** README "Upload driving data", ADR 003.
- **Depends on:** M1-12, M1-14
- **Work:**
  - `StopRow` and `ImportBatch` entities and migrations, with a unique index on
    `(mapping_name, row_key)`.
  - `StopRowUpserter`, using a batched upsert (`INSERT … ON DUPLICATE KEY UPDATE`). It returns
    the added and updated counts and the affected dates.
  - Routes not seen before are created without a supplier ("Unknown").
- **Tests first (integration):**
  - Importing the fixture inserts every row.
  - Importing it again gives 0 added and only updated rows, with an unchanged row count.
  - Changing one row and re-importing updates only that row.
  - Two mappings with the same row key don't collide.
  - New routes are created as unmapped.
  - Affected dates are correct.
- **Acceptance criteria:** duplicates can't be created, even when the same file is imported
  twice in parallel. The unique index guarantees this.

### M1-16 Build route runs

- **Plan step:** 7. **Implements:** README "Key figures" (definitions), architecture "Pipeline".
- **Depends on:** M1-15
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

### M1-17 Aggregate daily stats

- **Plan step:** 7. **Implements:** architecture "Data model" (derived tables).
- **Depends on:** M1-16
- **Work:**
  - The `DailyStats` entity and migration, with a unique index on `(service_date, supplier_id)`.
  - `DailyStatsAggregator` replaces the rows for the given dates. Counters: trips, completed,
    cancelled, route runs, last drop-offs, on time, solo runs, and driven and empty km and
    minutes.
  - A listener on `RouteSupplierChanged` rebuilds every date that route appears on.
  - The whole import (upsert, runs, stats) runs in one transaction.
- **Tests first:**
  - **Unit:** the counters from the route runs.
  - **Integration:**
    - Rebuilding the same dates twice gives identical rows.
    - A supplier change moves the counts.
    - A failure part-way leaves the previous data untouched.
- **Acceptance criteria:** the fixture's daily stats match the totals worked out by hand.

### M1-18 Upload page and import command

- **Plan step:** 7. **Implements:** README "Upload driving data".
- **Depends on:** M1-17
- **Work:**
  - `ImportController` (`ROLE_EDITOR`) with `ImportType`: `.xlsx` only, with a size limit.
  - The import runs synchronously.
  - The result page shows rows added, updated and rejected, with the reasons.
  - The uploaded file is deleted after processing.
  - `app:driving-data:import <PATH_TO_FILE> [--mapping=<MAPPING_NAME>]` uses the same service.
- **Tests first:**
  - **Functional:**
    - An editor uploads the fixture and sees the counts.
    - A user gets 403.
    - A wrong file type and an oversized file are rejected.
    - The temporary file is removed.
  - **Integration:** the command imports, and prints the same counts.
- **Acceptance criteria:**
  - The upload and the command behave as in the README.
  - Uploading the same file twice changes nothing.

### M1-19 Daily counts

- **Plan step:** 8. **Implements:** README "Enter daily counts".
- **Depends on:** M1-09
- **Work:**
  - The `DailyCount` entity and migration, unique per date.
  - `DailyCountController` (`ROLE_EDITOR`) with `DailyCountWeekType`: seven days with mails,
    complaints and phone calls.
  - Previous and next week navigation.
  - Validation: integers ≥ 0, no future dates.
  - Saving updates the existing rows.
- **Tests first:**
  - **Unit:** the week calculation, including ISO weeks across years.
  - **Functional:**
    - Saving a week creates seven rows.
    - Editing a day updates it without creating a duplicate.
    - Negative numbers and future dates are rejected.
    - A user gets 403.
- **Acceptance criteria:** daily counts can be entered and corrected as in the README.

## E. Overview

### M1-20 Periods and filters

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

### M1-21 Figure calculation

- **Plan step:** 9. **Implements:** README "Key figures", architecture "Calculating the
  figures".
- **Depends on:** M1-17, M1-19, M1-20
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

### M1-22 Tile, sparkline and panel components

- **Plan step:** 9. **Implements:** architecture "User interface".
- **Depends on:** M1-09, M1-10
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

### M1-23 Overview with the eight tiles

- **Plan step:** 9. **Implements:** README "Features" (overview), "Key figures", "Filters".
- **Depends on:** M1-21, M1-22
- **Work:**
  - `OverviewController` (`ROLE_USER`).
  - The top-bar filters: a segmented control for the period, a previous/next stepper, the
    comparison select and the supplier select. All are GET links or forms.
  - The eight tiles, each with the current value, the delta and a 12-month sparkline.
  - Daily-count tiles say "whole office" when a supplier is selected.
- **Tests first (functional):**
  - With fixture data, each tile shows the expected value. Use `data-test` attributes, not
    markup.
  - The filters change the values, and invalid query values fall back to the defaults.
  - A user sees the page. Anonymous visitors are redirected.
- **Acceptance criteria:**
  - The overview works end to end: upload, enter counts, see the figures.
  - Views can be shared as links.

### M1-24 Punctuality chart, supplier table and help texts

- **Plan step:** 9. **Implements:** README "Features" (overview), "Key figures".
- **Depends on:** M1-23
- **Work:**
  - The `LineChart` component: inline SVG, a target line, gridlines and a hidden data table.
  - The 12-month punctuality chart with `APP_PUNCTUALITY_TARGET`.
  - The supplier table for the driving figures, always showing all suppliers.
  - Help texts for each figure, and the "about the figures" dialog content.
- **Tests first:**
  - **Unit:** the `LineChart` scales, the target line and missing months.
  - **Functional:**
    - The table lists every supplier, and "Unknown" if there are unmapped routes.
    - The values per supplier.
    - Every tile has a help text.
- **Acceptance criteria:** the overview matches the README's feature list.

### M1-25 Development fixtures

- **Plan step:** 4 and 9. **Implements:** README "Fixtures".
- **Depends on:** M1-18, M1-19
- **Work:**
  - `AppFixtures`: a user per role (with known development passwords), suppliers, routes, an
    imported sample day (run through the real import service) and daily counts.
  - `task fixtures:load`.
- **Tests first (integration):** loading the fixtures gives the expected counts, and the overview
  shows non-empty figures.
- **Acceptance criteria:** `task fixtures:load` gives a usable local site in one command.

## F. Deployment

### M1-26 Woodpecker deployment

- **Plan step:** 10. **Implements:** README "Deployment".
- **Depends on:** M1-23
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
