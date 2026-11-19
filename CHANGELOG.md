# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

- The login accepts the app role values of Entra ID. Each value becomes `ROLE_` and the value in
  upper case. `admin` (Superadministrator) becomes `ROLE_ADMIN`, and `user` (Slutbruger) becomes
  `ROLE_USER`. The role hierarchy gives both roles all permission roles. A user no longer gets
  `ROLE_USER` automatically. The mock identity provider has the same app roles.
- All logs go to stderr in the prod environment, also the authentication log and the driving data
  log. In prod, the application writes no log files.
- [#153](https://github.com/itk-dev/koerselsoverblik/issues/153)
  Upload many extracts with drag and drop: the page `/admin/import/bulk` takes many files in a UX
  Dropzone field. A Stimulus controller sends each file in its own request, one at a time. The
  date range of each file is the first to the last date with stop rows. Each file is checked, and
  then imported with its own batch, in the order of the list. A failed file does not stop the
  others. The page shows the limit of `APP_DRIVING_DATA_MAX_DAYS`. StimulusBundle and UX Dropzone
  are added. `UploadFailure` gives the Danish message of a failed upload for both upload pages.
  The upload of one file has the same layout: a drop area, then the info box. Tabs switch between
  the two pages. The menu marks its item on each page of a section, also on a tab or a sub-page.

## [0.1.0] - 2026-11-18

- [#5](https://github.com/itk-dev/koerselsoverblik/issues/5)
  M1-01 Project skeleton: Symfony 8 on the ITK Dev `symfony-8` template, Doctrine ORM with
  MariaDB, Taskfile tasks, coding standards and PHPStan.
- [#7](https://github.com/itk-dev/koerselsoverblik/issues/7)
  M1-02 Test tooling: PHPUnit with unit, integration and functional suites, Foundry, a MariaDB
  test database, a 100 % line coverage gate and a tests workflow.
- [#9](https://github.com/itk-dev/koerselsoverblik/issues/9)
  M1-03 Mutation testing: Infection with a covered MSI gate of 100 % in `src/Report` and
  `src/Import` and 80 % in the remaining code, a changed-lines job for PRs and a full run for
  releases.
- [#11](https://github.com/itk-dev/koerselsoverblik/issues/11)
  M1-04 Diagram check in CI: a workflow that fails if a rendered diagram SVG is out of date.
- OIDC is the only login method in the plan, the tasks and the docs. The identity provider gives
  the roles in a claim. The password login and the `none` mode are removed from the plan.
- [#14](https://github.com/itk-dev/koerselsoverblik/issues/14)
  M1-05 Permission roles and deny by default: the security bundle with the permission roles and
  a role hierarchy, a placeholder login, and a test that fails if a route has no permission role.
  The monolog bundle writes the test logs to a file, so that Infection can run. `task prepare-code`
  fixes the coding standards before it runs the checks.
- [#17](https://github.com/itk-dev/koerselsoverblik/issues/17)
  OIDC plan: fixed OIDC routes, the `oidc-provider-mock` service for local development, the user
  is identified by email (the `upn` claim), and an authentication log records each login with the
  roles. The user list and `ROLE_MANAGE_USERS` are removed.
- [#18](https://github.com/itk-dev/koerselsoverblik/issues/18)
  The architecture diagrams are Mermaid code blocks in `docs/architecture.md`. The SVG render
  tasks, the diagram sources in `docs/diagrams/` and the Diagrams CI job are removed.
- [#29](https://github.com/itk-dev/koerselsoverblik/issues/29)
  The plan (`docs/plan.md`) and the task list (`docs/tasks.md`) are no longer in the repository.
  The GitHub issues hold the task specifications. The development rules are in the README.
- [#24](https://github.com/itk-dev/koerselsoverblik/issues/24)
  Base layout, design system and menu: the prototype's shell in Twig with the itkaarhus
  design system, a menu that shows an item only if its route exists and the user has its role,
  and Danish translations. No fonts load from external hosts. The menu has no "Administration"
  group, because all users have the same role. `nelmio/security-bundle` sends the Content
  Security Policy with a nonce for `importmap()`, `X-Content-Type-Options` and
  `Referrer-Policy`. Symfony UX Icons gives the Lucide icons, from SVG files in `assets/icons/`.
- [#26](https://github.com/itk-dev/koerselsoverblik/issues/26)
  Revised the data-in tasks: the upload page and "Suppliers and routes" are in an
  "Administration" section. An upload replaces the date range that the user enters, after a
  preview and a confirmation. `APP_DRIVING_DATA_MAX_DAYS` limits the range. The upload page lists
  the uploaded dates, and clears one date. The separate page that clears a date range is removed.
  ADR 004 is proposed: the stop rows keep the person id from the extract, with no HMAC, and
  `APP_PERSON_ID_SECRET` is removed. The `default` mapping uses the columns of a reference
  extract, with the new `date` and `time` types, a `run` field from the block number, and
  rejection of unknown values. A generator makes the test extracts and the development dataset
  from invented data.
  After the review: ADR 003 is proposed. A mapping changes only column names, code values and
  formats. The parser rejects rows outside the range, so the preview shows them. The
  confirmation token works one time only. `app:driving-data:purge-uploads` deletes unconfirmed
  upload files after one hour. Times are wall-clock times with no time zone. Clearing a date
  also needs `ROLE_UPLOAD_DRIVING_DATA`. The reference extract, the test data and the open
  questions of the import are in `docs/architecture.md`.
- The GitHub links use the new repository name, `itk-dev/koerselsoverblik`.
- [#44](https://github.com/itk-dev/koerselsoverblik/issues/44)
  Suppliers and sub-agreements: the first entities and migration. A route belongs to a
  sub-agreement: the part of its name before the last `-nn` or `_nn`. An administrator creates
  suppliers and sub-agreements, and assigns a sub-agreement to a supplier for a date range without
  overlap. Each change dispatches `SubAgreementSupplierChanged` with the old and the new period.
  Forms keep their CSRF tokens in the session.
- [#31](https://github.com/itk-dev/koerselsoverblik/issues/31)
  Import mapping: the YAML format for import mappings in `config/import/driving_data/`, a loader
  that validates a mapping and builds a `MappingDto` value object, and the `default` mapping for the
  extract of the planning system. The map values of `stop_type` and `stop_status` must be
  application values. `APP_DRIVING_DATA_MAPPING` selects the mapping for uploads. An
  invalid mapping stops the application when the container is built. A mutant that times out
  now fails the mutation run, and each mutant has 30 seconds.
- [#33](https://github.com/itk-dev/koerselsoverblik/issues/33)
  Fixture extracts: a writer that makes driving data extracts in the layout of an import mapping,
  test scenarios with totals worked out by hand, and a generator for 13 months of invented
  development data. `task fixtures:extracts` writes the files to `var/fixtures/driving_data/`.
  Infection does not mutate the generator and the fixtures command.
- [#42](https://github.com/itk-dev/koerselsoverblik/issues/42)
  Infection runs with 4 threads on all computers, so the mutation tests give the same result on a
  computer with many cores. `TASK_INFECTION_THREADS` sets a different number.
- [#23](https://github.com/itk-dev/koerselsoverblik/issues/23)
  The project name is Kørselsoverblik. The compose project, the local domain, the Composer package
  and the GitHub links use `koerselsoverblik`.
- [#20](https://github.com/itk-dev/koerselsoverblik/issues/20)
  M1-06 User entity and role mapping: the `User` entity (email, roles, last login) with a
  migration, and `OidcRoleMapper`.
- [#21](https://github.com/itk-dev/koerselsoverblik/issues/21)
  M1-07 OIDC login and mock service: `itk-dev/openid-connect-bundle` with `OidcAuthenticator`,
  the authentication log, trusted proxies, a page for a failed login, and the
  `oidc-provider-mock` service in `docker-compose.override.yaml`. The placeholder login is
  removed.
- [#36](https://github.com/itk-dev/koerselsoverblik/issues/36)
  Extract parser: `StopRowParser` reads the stop rows of an extract through an import mapping,
  one row at a time. It rejects a bad row with the row number and a `RejectionReason` code, and
  a row with a date outside the `ImportRangeDto` of the upload. `APP_DRIVING_DATA_MAX_DAYS`
  limits the range to 31 days. A mapping must now give each field an allowed type, and must make
  `route`, `service_date`, `run`, `stop_type`, `stop_status` and `planned_time` required.
  The parser reads the times in `APP_TIMEZONE` (default `Europe/Copenhagen`) and gives them in
  UTC. A date cell is accepted also for a field with a format string.
- [#38](https://github.com/itk-dev/koerselsoverblik/issues/38)
  Extract limits: before the first row, `ExtractInspector` checks the file size, the uncompressed
  size, the row numbers and the columns of an extract against the `APP_DRIVING_DATA_MAX_*` limits.
  It counts the uncompressed bytes that it reads, and does not use the sizes in the archive. It
  rejects an archive with more than 1000 files, a file in the archive that it cannot read, and a
  file with a `DOCTYPE`. A file that OpenSpout cannot read gives an `InvalidExtractException`.
- [#40](https://github.com/itk-dev/koerselsoverblik/issues/40)
  Validate command: `app:driving-data:mapping:validate` reads a sample file through a mapping and
  shows the missing columns, the rejected rows by field and reason, the dates and a preview. The
  person id is hidden, and no value of a rejected row is shown. The preview shows the times in
  `APP_TIMEZONE`, and hides the row key when a row id column is the person id column. The parser
  names all missing columns in one `MissingColumnsException`, and it accepts all dates when it has
  no range. The mutation tasks fill the test cache before Infection starts, so that cold caches
  give no timeouts.
- [#47](https://github.com/itk-dev/koerselsoverblik/issues/47)
  Dates and times in one time zone (ADR 005): the application supports one time zone only. The
  driving data, PHP and MariaDB must use the same time zone. The parser gives the times and the
  dates in the PHP default time zone, with no conversion to UTC. `APP_TIMEZONE` is removed.
  The fixtures command gets the current month from `LocalCalendar`. The `mariadb` service uses
  `Europe/Copenhagen`, as on the servers.
- [#49](https://github.com/itk-dev/koerselsoverblik/issues/49)
  One role for the driving data: `ROLE_MANAGE_DRIVING_DATA` replaces `ROLE_UPLOAD_DRIVING_DATA` and
  `ROLE_CLEAR_DRIVING_DATA`. The application ignores the old roles. Configure the identity
  provider to send the new role to users who do not have `ROLE_ADMIN`. One mock user,
  `driving-data-manager`, replaces the `uploader` and `clearer` mock users.
- [#53](https://github.com/itk-dev/koerselsoverblik/issues/53)
  The `default` mapping reads the planned time of a stop from `PlannedDeparture`, not
  `PlannedArrival`. The transport office measures punctuality against the planned departure.
- [#55](https://github.com/itk-dev/koerselsoverblik/issues/55)
  A copy of each imported row: the parser keeps the field columns, the row id columns and the new
  `keep_columns` of a mapping, with the type of each cell, so that a later task can read the data
  again with no new upload. A rejected row also has a copy, except a row outside the range. The
  `default` mapping keeps all the columns of the reference extract except the personal data. A
  bad value in an optional field gives an empty value and a warning, not a rejected row, and
  `planned_solo` is optional. A formula cell gives its computed value, not the formula. A mapping
  with a column name that has a space at the start or the end is rejected. The new
  `exclude_columns` of a mapping names the columns that it leaves out on purpose; the `default`
  mapping names the six personal columns. The validate command shows the warnings, the kept and
  the excluded columns that are not in the file, and the columns that it does not read. ADR 006
  is proposed.
- [#63](https://github.com/itk-dev/koerselsoverblik/issues/63)
  OIDC review follow-up: a stale login callback gives 400, not 500. A refusal from the identity
  provider with a 4xx status, and a stale callback, are logged as warnings. The authentication
  log now records a failed login, because the bundle does not. The failure page has a neutral
  text. The dead `password_hashers` and CSRF token ids are removed. `.env.test` sets
  `OIDC_CLIENT_SECRET_EXPIRES_AT`. The compose override is `docker-compose.override.yml`.
  ADR 002 tells that guest accounts are not expected.
- [#50](https://github.com/itk-dev/koerselsoverblik/issues/50)
  M1-16 Replace the stop rows for the date range of an upload: the new `StopRow`,
  `RejectedStopRow` and `ImportBatch` entities store the rows, the rejected rows in the range and
  the import, each row with its copy. `StopRowReplacer` deletes the rows of the range from all
  mappings and inserts the rows of the file. It rejects a row id that occurs again in the file,
  and creates a new route with a sub-agreement that has no assignment. `DrivingDataImporter`
  gives a preview with no changes, and imports in one transaction. The batch keeps the counts,
  the rejection reasons and the warnings, with no values.
- [#46](https://github.com/itk-dev/koerselsoverblik/issues/46)
  Strict types: PHP CS Fixer adds `declare(strict_types=1);` to our PHP files and checks it. It
  excludes the files from Symfony and Flex. Infection no longer ignores the `CastString` mutants
  that depended on type coercion.
- [#57](https://github.com/itk-dev/koerselsoverblik/issues/57)
  M1-17 Build the route runs: each stop row stores its delay in seconds. The new `RouteRun`
  entity holds the first pickup and the last drop-off of each route, date and block number, with
  the planned time, the actual time and the delay of a measured stop. `RouteBuilder` builds the
  runs of the range again in the import transaction. The peak passengers, the distances and the
  durations wait for an answer from the transport office.
- Q4 update, templates: the ITK Dev `symfony-8` template is updated. PHP is 8.5 and the nginx
  image is `nginx/nginx-unprivileged`. The MariaDB service keeps `TZ=Europe/Copenhagen`. The
  Prettier tasks ignore `.editorconfig`, as the template workflows do, so the JavaScript and the
  CSS have 2 spaces.
- CI: the tests workflow runs on all pull requests, not only on pull requests to `develop` and
  `main`. The mutation tests compare with the base branch of the pull request.
- Q4 update, security: Symfony is updated to 8.1.11 for 15 security advisories.
- Q4 update, direct dependencies: `composer.json` lists the 24 packages and PHP extensions that
  the code uses directly, with the installed versions.
- Q4 update, dependencies: all packages are updated to the latest minor or patch version, for
  example Doctrine ORM 3.7.6, PHPStan 2.3.5, PHPUnit 13.4.6 and Foundry 2.15.
- Q4 update, Rector: Rector 2.8 uses the PHP level from `composer.json` and the sets of the
  installed packages. `task prepare-code` applies it, and CI checks it. The first run changes 28
  files, for example `new` without parentheses and the PHPUnit 13 method names.
- Q4 update, recipes: the Flex recipes of DoctrineBundle, FrameworkBundle and Foundry are updated.
  The project keeps its own `AGENTS.md`.
- Q4 update, deprecations: PHPStan uses `phpstan/phpstan-deprecation-rules`. The validator test
  calls `validate()` of the Symfony test case, not the deprecated `validate()` of the validator.
- The itkaarhus design system is updated to 0.6.0. It adds a purple colour scale for guidance.
- [#58](https://github.com/itk-dev/koerselsoverblik/issues/58)
  M1-19a Import driving data from the console: `app:driving-data:import` shows the preview of the
  upload page, asks for a confirmation and imports with `DrivingDataImporter`. The output has no
  person id and no cell value. `task fixtures:import` imports the 13 months of the dev dataset.
  The dev dataset now has a unique row id for each row, and late runs in all delay bands.
- [#59](https://github.com/itk-dev/koerselsoverblik/issues/59)
  Calculate the punctuality figures: `PunctualityCalculator` gives the four punctuality measures,
  the delay bands of the last drop-off and the 10 worst routes for a period, in total and for
  each supplier on each date. `PunctualityTone` gives the tone of a measure.
  `APP_PUNCTUALITY_TOLERANCE_MINUTES` and `APP_PUNCTUALITY_TARGET` are new settings.
- [#60](https://github.com/itk-dev/koerselsoverblik/issues/60)
  Show the punctuality page: "Rettidighed" at `/punctuality` shows the four measures of a month,
  the late last drop-offs in delay bands, the figures for each supplier and the ten worst routes,
  with 12-month charts. The tile, panel, help, sparkline, line chart, column chart and
  distribution bar of the prototype are Twig components (`symfony/ux-twig-component`), with the
  geometry in `src/Chart/`. The `number_format` filter of Twig now has Danish defaults in all
  templates: no decimals, a decimal comma and a point between the thousands. The months of the
  dev dataset have different punctuality, so that the page shows all tones.
- [#79](https://github.com/itk-dev/koerselsoverblik/issues/79)
  M1-19b Upload driving data with a preview: "Upload kørselsdata" at `/admin/import` uploads an
  extract for a date range, shows the preview, and imports after the confirmation. The result
  page shows the saved import. `app:driving-data:purge-uploads` deletes the upload files that are
  older than one hour. Each problem of an extract has an `ExtractProblem` code, so that the page
  shows it in Danish. The upload limits of PHP and nginx are 25 MB, in
  `docker-compose.override.yml` and the new `docker-compose.server.override.yml`. The import
  refuses a file with no stop rows in the range (`NoStopRowsException`), so that a wrong range
  cannot only delete data.
- [#83](https://github.com/itk-dev/koerselsoverblik/issues/83)
  One test database for each Infection thread: the mutation tasks create and migrate `db_test1`
  to `db_test4`. Before, a database test failed in a mutant process, and Infection counted the
  mutant as killed. So the 100 % gate did not check the code that only database tests cover.
- [#88](https://github.com/itk-dev/koerselsoverblik/issues/88)
  Plans: `docs/plans/README.md` lists the features that remain, with the status, the issue and
  the plan file, and the merged features. A plan file holds only the text that is not in the
  issue. The issue stays the task specification. The pull request that closes a feature deletes
  its plan file. "Open decisions" in `docs/architecture.md` has the open questions that were
  missing, and "Actual time" is answered. The docs can give counts of rows from the reference
  extract, but no cell values.
- Plans for [#91](https://github.com/itk-dev/koerselsoverblik/issues/91) (one supplier for each
  sub-agreement, with no dated assignments) and
  [#90](https://github.com/itk-dev/koerselsoverblik/issues/90) (ignore a sub-agreement, for
  example "Turbestilling", in the figures).
- [#91](https://github.com/itk-dev/koerselsoverblik/issues/91)
  One supplier for each sub-agreement: `SubAgreement` has a `supplier`, and the dated supplier
  assignments are removed. The supplier applies to all dates. The sub-agreement page has an edit
  page for the supplier, and the list shows the sub-agreements with no supplier first. The
  punctuality calculator adds the figures for each route, not for each route and date.
- Plans for [#101](https://github.com/itk-dev/koerselsoverblik/issues/101) (a development
  dataset with the volume, the shares and the Aarhus school holidays of the real exports) and
  [#100](https://github.com/itk-dev/koerselsoverblik/issues/100) (memory of the import that does
  not grow with the number of rows).
- [#90](https://github.com/itk-dev/koerselsoverblik/issues/90)
  Ignore a sub-agreement in the figures: `SubAgreement` has an `ignored` flag, and the edit page
  has a checkbox for it. The punctuality queries skip the stops and the runs of an ignored
  sub-agreement through `IgnoredSubAgreementFilter`. The import still stores them.
- Plan for [#99](https://github.com/itk-dev/koerselsoverblik/issues/99) (a note on the
  punctuality page that names the ignored sub-agreements). The list does not show an ignored sub-agreement
  with no supplier at the top. The open decision "Booked trips" is answered: an administrator
  ignores "Turbestilling".
- [#101](https://github.com/itk-dev/koerselsoverblik/issues/101)
  A realistic development dataset: one file for each ISO week, with the volume and the patterns
  of the school transport, the Aarhus school holidays, and route names that are not routes. The
  punctuality is a setting (`APP_FIXTURES_PUNCTUALITY_START` and `APP_FIXTURES_PUNCTUALITY_TREND`),
  not a value from the real data. `app:fixtures:suppliers` gives the sub-agreements invented
  suppliers. `APP_DRIVING_DATA_MAX_DAYS` is now 7, and `APP_DRIVING_DATA_MAX_UNCOMPRESSED_SIZE`
  is now 100 MiB.
- [#95](https://github.com/itk-dev/koerselsoverblik/issues/95)
  Keep the copy of a row in its own entity: `SourceRow` holds the copy, and `StopRow` and
  `RejectedStopRow` refer to it. Doctrine does not read the copy with the row. The replacer
  deletes the copies with no row after it deletes the rows of a range. The migration does not
  keep the stored copies. It stops with no change if the database has driving data, so delete the
  driving data of a developer database before you migrate it.
- Mutation testing runs only on the release and hotfix branches. The changed-lines job of the
  pull requests is removed, and `task prepare-code` does not run Infection.
- [#126](https://github.com/itk-dev/koerselsoverblik/issues/126)
  Delete the rows of a range in the repositories, and only their copies: `StopRowRangeDeleter`
  deletes the rejected rows, the stop rows and their copies of a range. The queries are in
  `StopRowRepository` and the new `RejectedStopRowRepository`. The delete of the copies uses their
  ids, so it no longer reads the copies of other dates. Two imports of the same dates that overlap
  can leave copies with no row. The new command `app:driving-data:delete-orphan-copies` deletes
  them, and a cron job runs it each night. The two plans for the deletes are one plan, and the
  driving data plans are in the order of the work.
- [#106](https://github.com/itk-dev/koerselsoverblik/issues/106)
  The overview shows the trips and the distinct citizens who were driven in a month, with the
  change from the previous month, a 12-month sparkline and a help text. The month chooser of the
  punctuality page is now a shared template.
- [#109](https://github.com/itk-dev/koerselsoverblik/issues/109)
  The overview shows the punctuality: the tile "Rettidighed" (the drop-off per route run), the
  four measures against the service target, and the 12-month chart. The punctuality page and the
  overview share the templates of the goal panel and the chart.
- [#110](https://github.com/itk-dev/koerselsoverblik/issues/110)
  The overview shows the share of the real solo runs ("Reelt solo"). `RouteRun` has the peak
  passengers: the most citizens in the car after a stop that is not cancelled. The migration adds
  the column. Import the driving data again to fill it for the dates that were imported before.
- [#113](https://github.com/itk-dev/koerselsoverblik/issues/113)
  The overview shows the suppliers side by side: the trips, the punctuality, the real solo runs
  and the cancelled trips of each supplier, from `SupplierTableCalculator`.
- [#118](https://github.com/itk-dev/koerselsoverblik/issues/118)
  No source text names the planning system. The page keeps the name "Rettidighed".
- [#132](https://github.com/itk-dev/koerselsoverblik/issues/132)
  The coverage gate runs only on the release and hotfix branches, together with the full mutation
  testing, in the new workflow `release-checks.yaml`. The pull requests and `develop` run the tests
  with no coverage.
- [#115](https://github.com/itk-dev/koerselsoverblik/issues/115)
  The period logic of the filters: `PeriodFilter` reads the period, the date, the comparison and
  the supplier from the query string. `PeriodRangeFactory` gives the selected period, the
  comparison period (cut when the period has not ended) and the 12 months. `PeriodLabeler`
  gives the Danish labels of the prototype. No page uses them yet.
- [#116](https://github.com/itk-dev/koerselsoverblik/issues/116)
  The overview and the punctuality page have the filter bar of the prototype: the period (week,
  month, quarter or half year), the previous and the next period, and the comparison (the
  previous period or the same period last year). It replaces `?month=` and the month chooser.
  The tiles compare the selected period with the comparison period, and a period that has not
  ended is compared with the same number of days. `APP_DATA_START_DATE` gives the start of the
  data: a comparison before it is a partial period or has no data. The period types day and
  year of the prototype are left out. After the review: the period switch is a `nav` with
  `aria-current`, only the "Vis" button sends the form, a query that is not canonical
  redirects, an invalid `APP_DATA_START_DATE` gives an error, the cut of the comparison counts
  from the first date of the period, the counts of a comparison that starts before the data
  have no change, and a period that is not a month has a note that the sparklines show months.
- [#117](https://github.com/itk-dev/koerselsoverblik/issues/117)
  The filter bar has the supplier select box ("Alle leverandører" or one supplier). With a
  supplier, the tiles, the charts and the worst routes are of the routes of that supplier. The
  supplier tables always show all suppliers. An unknown supplier id gives all suppliers.
- [#134](https://github.com/itk-dev/koerselsoverblik/issues/134)
  The overview and the punctuality page are fast with more than one year of driving data.
  `CountedRouteFilter` replaces `IgnoredSubAgreementFilter` and `SupplierFilter`. It limits the
  figure queries with a list of route ids, not a join, so that MariaDB reads only the dates of
  the range. One query for each figure, grouped by month, gives the totals of the 12 months. A
  period that is a month of the history uses the figures of that month. A new index on
  `stop_row (route_id, service_date)` helps the queries for a few routes. Closes #85.
- [#136](https://github.com/itk-dev/koerselsoverblik/issues/136)
  The figures come from `RouteDayTotals`, the counters of each route and day, which each import
  builds for its range. The pages add these rows, not the stop rows and the route runs. The
  ignored flag and the supplier stay in the queries, so a change of a sub-agreement needs no
  rebuild. The citizens stay a query on `stop_row`, with a new index that holds all its columns.
  `app:driving-data:rebuild-totals` builds all dates again, after the first deploy and after a
  change of `APP_PUNCTUALITY_TOLERANCE_MINUTES`. Before the admin runs the command, the pages
  show a page that names the command. The index on `stop_row (service_date)` is removed, because
  the new index starts with the date. The table replaces the planned `DailyStats`.
- [#138](https://github.com/itk-dev/koerselsoverblik/issues/138)
  `MonthCitizens` holds the citizens of each calendar month, for all suppliers and for each
  supplier, with no person ids. The citizens sparkline of the overview reads it, so the overview
  no longer counts 12 months of person ids. An import builds the months of its range, an edit of
  a sub-agreement builds the scopes that it changes, and `app:driving-data:rebuild-totals`
  builds all months. `app:fixtures:suppliers` also builds all months, because the import of the
  development dataset runs before the suppliers exist.
- The plans agree with the issues and the pull requests again. #117, #132 and #136 are in
  "Done", #122 and #105 are ready, and the plans list #128 and #131. #75 needs #131.
- [#131](https://github.com/itk-dev/koerselsoverblik/issues/131)
  The tests focus on the production code. The tests of the development dataset
  (`DevDatasetGenerator`, `DatasetSettings`, `SchoolCalendar` and the fixture commands) are in the
  PHPUnit group "fixtures". `task test`, the coverage gate and Infection do not include this code.
  `task test:fixtures` and a separate CI job run the group. `ExtractWriter` and `ScenarioLoader`
  stay in the default run, because the production tests trust their files. `task test` takes
  about 25 seconds, not 4.5 minutes. The test environment does not collect the Doctrine queries,
  the test log keeps at most 50 records in memory, and the dataset tests keep less data in
  memory. The peak memory of `task test` is 91 MB, not 226 MB. New tests cover the production
  lines that only the fixture tests covered.
- [#75](https://github.com/itk-dev/koerselsoverblik/issues/75)
  Read the location name from the extract: the optional field `location_name`. `StopRow` stores
  it in a new column. The `default` mapping reads `Placename` as the field, and no longer has it
  in `keep_columns`. The validate command shows the location name as `(hidden)`. No feature uses
  `passengers`, `prisprminut`, `aflysningsvarsel` and `RettidigAflysningsvarsel` yet, so they
  stay kept columns.
- [#87](https://github.com/itk-dev/koerselsoverblik/issues/87)
  The punctuality page shows the ten locations with the lowest punctuality, after the supplier
  table and before the worst routes. A location counts the completed pickups and drop-offs with
  a delay, as the measures "pr. borger". The import builds the new derived table
  `LocationDayTotals`: the measured stops of each location, route and day. The page does not read
  the stop rows. `app:driving-data:rebuild-totals` also builds the locations. Run it after the
  deploy. The supplier filter and the ignored sub-agreements apply. A test scenario stop can have
  a `location`.
  After the review: a location needs at least `APP_PUNCTUALITY_LOCATION_MIN_STOPS` (default 10)
  measured stops in the period to be ranked. The "?" help of the panel gives the rule and the
  minimum, and the panel foot gives the number of left-out locations. A panel can have a "?"
  help next to its title.
- [#86](https://github.com/itk-dev/koerselsoverblik/issues/86)
  Make the late drop-offs clear on the punctuality page. The supplier table has three delay
  columns with the labels of the split bar. The new column is the band from the tolerance to
  15 minutes. A note under the table tells what the delay columns count. In the worst routes,
  "Rettidighed" is the last column. The tiles "Forsinkede afsætninger" and "Over 30 minutter"
  have a "?" help.
- [#103](https://github.com/itk-dev/koerselsoverblik/issues/103)
  Rank the worst routes by the number of late runs at the last drop-off, not by the punctuality.
  On the same number, the lower punctuality is first, and then the route name. A route with one
  late run (0 %) is no longer above a route with many late runs. The panel has the title "De ti
  vognløb med flest forsinkede sidste afsætninger". `PunctualityCountDto::late()` gives the late
  count.
  After the review: a route with no late run is not in the list. If no run is late, the panel
  tells this, and not "no data".
- [#104](https://github.com/itk-dev/koerselsoverblik/issues/104)
  Compare the delays of the months as shares of the measured runs, so that the volume of a month
  does not decide the figures. The tiles "Forsinkede afsætninger" and "Over 30 minutter" keep the
  count. Their change, sparkline and note are of the share. The change is in percentage points.
  The chart "Forsinkelser fordelt på intervaller" shows the shares on a percent axis, and the
  title of each bar also gives the count. The new filter `share_percent` shows one decimal below
  10 %. The column chart has the props `unit` and `countLabel`.
  After the review: the percent axis shows up to two decimals (`axis_percent`), so that 1,25 %
  does not show as "1,3 %". A share above 0 that rounds to 0 shows as "< 0,1 %". The hidden data
  table gives the count next to the share. A bar title with no `countLabel` shows the bare count.
- [#105](https://github.com/itk-dev/koerselsoverblik/issues/105)
  Fix the chart labels and the number format on the punctuality page. The chart of the delays is
  in a panel of half the width, so its viewBox has half the width (`Scale::HALF_WIDTH`). Its axis
  labels now have about the same size on screen as those of the trend chart. `ColumnChart` has the
  prop `width`. The total of the split bar has a thousands separator. The month chooser needs no
  fix, because the filter bar of #116 replaced it, and the filter bar is not in the page head.
  After the review: when the panels are in one column, the CSS keeps the chart of the delays at
  half the width (`chart--half`, `max-width: 50cqi`), so its labels do not become twice as large.
  A chart width other than `Scale::WIDTH` or `Scale::HALF_WIDTH` gives an error. The line chart
  has no width parameter, because no line chart has half the width.
- [#99](https://github.com/itk-dev/koerselsoverblik/issues/99)
  Show the ignored sub-agreements on the punctuality page. The figures do not include their
  routes, so a note below the lede gives their number and references, for example "2 delaftaler
  er ignoreret i tallene: BOOKED, TEST". `SubAgreementRepository::findIgnoredReferences()` adds
  one query to the page. The overview does not run this query. After the review: with a supplier
  filter, the note names only the ignored sub-agreements of that supplier, because the figures of
  the supplier have no others.
- [#122](https://github.com/itk-dev/koerselsoverblik/issues/122)
  Show the conclusion on the overview. A callout under the heading gives a headline ("Servicen er
  blevet bedre.", "Servicen er blevet dårligere.", "Blandet billede.", "Stort set uændret." or
  "Ingen sammenligning for denne periode.") and one sentence with the values of each measure. The
  measures are the punctuality, the real solo runs and the cancelled trips. A measure counts as
  better or worse only if its change is at least the threshold. The threshold is in percentage
  points, not relative to the value as in the issue, because the tiles show the changes in points
  too. "Tilpas konklusion" is a
  `<details>` form with no script. The settings are in the query string (`m`, `t`), and the filter
  bar keeps them. `ConclusionCalculator` uses the figures of the page, so it adds no query.
  After the review: the change is rounded to one decimal before the comparison, as on the tiles,
  so that a change of exactly the threshold from counts is not unchanged. `PeriodFilter` checks the
  type of `m`, not the controller. The CSS classes are `conclusion-settings__*`.
- [#81](https://github.com/itk-dev/koerselsoverblik/issues/81)
  Show the uploaded dates and clear the driving data of a date. The upload page lists the dates
  of one month (`?month=YYYY-MM`) with the stop rows, the route runs and the last upload. "Ryd"
  opens a native popover with the counts and a POST form with a CSRF token for the date.
  `DrivingDataClearer` deletes the stop rows, the rejected rows, their copies, the route runs and
  the totals of the range, and builds the citizens of the months again, in one transaction. The
  batches stay. The command `app:driving-data:clear` uses the same service. Each clear is written
  to the new `driving_data_audit` log, which has its own file. `IsoDate` reads a date for the
  commands and the route. The plans list #82 as done, because all its sub-issues are done.
  After the review: the list also has the dates with only rejected rows, and a column with the
  rejected rows. The counts of a clear come from the deletes. The clear needs a signed-in user, so
  that the log never shows a web clear as a console clear. The focus goes to "Annullér" in the
  popover. The month links stop at the month of the first batch. The components diagram keeps the
  `DailyCount` controller and the `DailyCountWeek` form, because the docs describe the daily counts
  as a planned feature.
- Deployment: Woodpecker pipelines for staging and production, a workflow that makes the release
  archive from a tag, and a `crontab` for Supercronic. One migration replaces all migrations.

[Unreleased]: https://github.com/itk-dev/koerselsoverblik/compare/0.1.0...HEAD
[0.1.0]: https://github.com/itk-dev/koerselsoverblik/releases/tag/0.1.0
