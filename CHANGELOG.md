# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
