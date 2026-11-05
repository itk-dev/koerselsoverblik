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
  It rejects a file with a `DOCTYPE`. A file that OpenSpout cannot read gives an
  `InvalidExtractException`.
