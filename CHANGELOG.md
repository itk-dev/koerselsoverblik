# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

- [#5](https://github.com/itk-dev/passenger-transport-performance/issues/5)
  M1-01 Project skeleton: Symfony 8 on the ITK Dev `symfony-8` template, Doctrine ORM with
  MariaDB, Taskfile tasks, coding standards and PHPStan.
- Replace password login with OIDC login in the spec, plan, tasks, architecture and ADR 002.
  `APP_AUTH_MODE` is `oidc` or `none`, the admin role comes from the Azure B2C `roles` claim, local
  development uses a mock identity provider, and user administration is dropped.
