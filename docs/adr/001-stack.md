# 001: Stack

| Field | Value |
| --- | --- |
| **Created By** | Troels Ugilt Jensen |
| **Date** | 2026-10-10 |
| **Decision Maker** | Troels Ugilt Jensen |
| **Stakeholders** | ITK Dev developers; the transport office (users) |
| **Status** | Draft |

## Context

A static HTML/JS prototype shows what the application should do. It now needs a real backend
with storage, login, uploads and forms, built and run by the ITK Dev team.

### Drivers

- **Functional:** file upload and parsing, forms for data entry, role-based access, server-side
  aggregation over date ranges, and pages that can be printed and shared as links.
- **Non-functional:** maintainable by the team, hosted on the existing ITK Dev servers, few moving
  parts, strong test tooling.

### Options Considered

1. **Symfony + MariaDB on the ITK Dev template**: the team's standard stack, with existing Docker,
   Taskfile, CI and Woodpecker conventions. Forms, security and validation are built in.
2. **Keep the prototype as a JS frontend with a JSON API**: reuses the prototype's views, but
   needs two codebases, a build step and an API layer for little gain.
3. **An off-the-shelf BI tool**: quick charts, but no upload with overwrite, no data entry, and
   the calculations would live outside version control and tests.

## Decision

Option 1:

- **Framework and database:** Symfony 8 on PHP 8.4, with MariaDB, on the ITK Dev `symfony-8`
  template.
- **UI:** server-rendered Twig with AssetMapper (no Node build), hand-built Symfony forms (no
  EasyAdmin), and the itkaarhus design system. Charts are inline SVG rendered by Twig components.
- **CI and deployment:** GitHub Actions for CI, Woodpecker for deployment.
- **Testing:** PHPUnit, Infection and PHPStan at max level.

## Consequences

### Positive

- The stack, conventions and deployment are already known to the team.
- One codebase. All calculations are in PHP, covered by unit and mutation tests.
- Pages work without JavaScript and are easy to print and link to.

### Negative / Trade-offs

- The prototype's JS views must be ported to Twig.
- Hand-built forms take more work than an admin generator.
- Server-rendered charts are less interactive than a JS chart library.

### Follow-up Actions

- [ ] Install the template and the Symfony skeleton (task M1-01).
