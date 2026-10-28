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
- [#17](https://github.com/itk-dev/passenger-transport-performance/issues/17)
  OIDC plan: the routes and the `oidc-provider-mock` service follow `itk-dev/devops_itksites`, the
  user is identified by email (the `upn` claim), and an authentication log records each login
  with the roles. The user list and `ROLE_MANAGE_USERS` are removed.
