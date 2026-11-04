# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

- [#5](https://github.com/itk-dev/passenger-transport-performance/issues/5)
  M1-01 Project skeleton: Symfony 8 on the ITK Dev `symfony-8` template, Doctrine ORM with
  MariaDB, Taskfile tasks, coding standards and PHPStan.
- [#7](https://github.com/itk-dev/passenger-transport-performance/issues/7)
  M1-02 Test tooling: PHPUnit with unit, integration and functional suites, Foundry, a MariaDB
  test database, a 100 % line coverage gate and a tests workflow.
- [#9](https://github.com/itk-dev/passenger-transport-performance/issues/9)
  M1-03 Mutation testing: Infection with a covered MSI gate of 100 % in `src/Report` and
  `src/Import` and 80 % in the remaining code, a changed-lines job for PRs and a full run for
  releases.
- [#11](https://github.com/itk-dev/passenger-transport-performance/issues/11)
  M1-04 Diagram check in CI: a workflow that fails if a rendered diagram SVG is out of date.
- OIDC is the only login method in the plan, the tasks and the docs. The identity provider gives
  the roles in a claim. The password login and the `none` mode are removed from the plan.
- [#14](https://github.com/itk-dev/passenger-transport-performance/issues/14)
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
  Infection does not mutate the fixture code.
- [#36](https://github.com/itk-dev/koerselsoverblik/issues/36)
  Extract parser: `StopRowParser` reads the stop rows of an extract through an import mapping,
  one row at a time. It rejects a bad row with the row number and a `RejectionReason` code, and
  a row with a date outside the `ImportRangeDto` of the upload. `APP_DRIVING_DATA_MAX_DAYS`
  limits the range to 31 days. A mapping must now give each field an allowed type, and must make
  `route`, `service_date`, `run`, `stop_type`, `stop_status` and `planned_time` required.
- [#38](https://github.com/itk-dev/koerselsoverblik/issues/38)
  Extract limits: before the first row, `ExtractInspector` checks the file size, the uncompressed
  size, the row numbers and the columns of an extract against the `APP_DRIVING_DATA_MAX_*` limits.
  It rejects a file with a `DOCTYPE`. A file that OpenSpout cannot read gives an
  `InvalidExtractException`.
- [#40](https://github.com/itk-dev/koerselsoverblik/issues/40)
  Validate command: `app:driving-data:mapping:validate` reads a sample file through a mapping and
  shows the missing columns, the rejected rows by field and reason, the dates and a preview. The
  person id is hidden, and no value of a rejected row is shown. The parser names all missing
  columns in one `MissingColumnsException`, and it accepts all dates when it has no range.
