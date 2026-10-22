# AGENTS.md

Instructions for coding agents in this project. Read them before you change code.

## The project

Passenger Transport Performance shows key figures for citizen transport. It is a Symfony 8
application on PHP 8.4, with Doctrine ORM and MariaDB. It runs in Docker with the ITK Dev
`symfony-8` template.

Read these documents before you start a task:

- [README.md](README.md): the specification. The application must do what the README says.
- [docs/plan.md](docs/plan.md): the plan and the development approach.
- [docs/tasks.md](docs/tasks.md): the tasks. Each task is one issue, one branch and one pull
  request.
- [docs/architecture.md](docs/architecture.md): the design, the diagrams and the security rules.
- [docs/adr/](docs/adr/): the architecture decisions.

## Commands

Run all commands with `task`. The tasks run PHP and Composer in the `phpfpm` container. Do not
run `php`, `composer` or `bin/console` on the host. Run `task` to see all tasks.

```shell
task setup                     # Start the stack, install dependencies, run migrations
task console -- <COMMAND>      # Symfony console, for example `task console -- about`
task composer -- <COMMAND>     # Composer, for example `task composer -- require <PACKAGE>`
task db:migrate                # Run the database migrations
task coding-standards-check    # Check all coding standards
task coding-standards-apply    # Fix the coding standards
task code-analysis             # PHPStan at max level
task docs:diagrams             # Render the Mermaid diagrams to SVG
```

`.env` holds defaults only. Put secrets and local values in `.env.local`. Do not commit
`.env.local`.

## Development approach

The project uses test-driven development. See "Development approach" in
[docs/plan.md](docs/plan.md).

- Write a failing test first. Then write the minimal code that makes it pass. Then refactor.
- Do not write production code without a test that requires it.
- Line coverage must be 100 %. Do not use `@codeCoverageIgnore`.
- Infection requires a covered MSI of 100 % in `src/Report` and `src/Import`, and 80 % in the
  remaining mutated code.
- Put logic in plain PHP classes. Keep controllers, form types, commands and authenticators
  thin, so that most tests are fast unit tests.
- Functional tests assert behaviour, not markup. Use text and `data-test` attributes.
- Tests are in `tests/Unit`, `tests/Integration` and `tests/Functional`. The test tasks come in
  task M1-02.

## Rules for the code

- Every controller has a class-level `#[IsGranted]` with a permission role. Do not check
  `ROLE_ADMIN` or `ROLE_USER` in a controller. See "Security" in
  [docs/architecture.md](docs/architecture.md).
- Templates have no inline scripts, no inline event handlers and no `style` attributes. The
  Content Security Policy blocks them.
- Logs and error messages never contain field values from the driving data.
- Change the database schema only with Doctrine migrations. Do not use
  `doctrine:schema:update` or hand-written SQL.
- The user interface is in Danish. Code, comments and documentation are in English.

## Symfony conventions

Follow the [Symfony best practices](https://symfony.com/doc/current/best_practices.html):

- Add packages with `task composer -- require <PACKAGE>`, and let the Flex recipe configure
  them. Do not edit `config/bundles.php` by hand. Flex does not add Docker services
  (`extra.symfony.docker` is `false`), because the template has them.
- Use PHP attributes for framework metadata, for example `#[Route]`, `#[IsGranted]`,
  `#[Assert\...]`, `#[AsCommand]` and `#[AsEventListener]`. Do not use YAML or XML routing.
- Use autowiring and autoconfiguration. Use `#[Autowire]` or `#[Target]` when a type is not
  sufficient. Use a YAML service definition only as the last solution.
- Controllers extend `AbstractController` and send the work to services.
- Use the framework components: Form for forms, Validator for validation, Security for access
  control, and `path()` or `url()` in Twig.
- Bind request data with `#[MapRequestPayload]` or `#[MapQueryString]`.
- Use constructor property promotion. Use `readonly` for DTOs and value objects.

To find how the project is configured, use these commands. Do not guess:

- `task console -- debug:router`, `debug:container` and `debug:config <BUNDLE>`
- `task console -- lint:container`, and `lint:yaml config/`
- The source code and docblocks in `vendor/`

## Coding standards

- PHP uses the `@Symfony` ruleset of PHP CS Fixer.
- YAML in `config/` has 4 spaces. Other YAML files have 2 spaces. Prettier checks them.
- Markdown lines have a maximum of 120 characters. markdownlint checks them.
- Run `task coding-standards-check` and `task code-analysis` before you commit.

## Git and pull requests

- Create a branch `feature/issue-<ISSUE_NUMBER>-<SHORT_DESCRIPTION>` from `develop`.
- Use Conventional Commits, for example `feat:`, `fix:`, `test:`, `docs:` and `chore:`.
- Add the task ID to the pull request title, for example
  `feat: replace stop rows for the dates in a file (M1-16)`.
- Add `Closes #<ISSUE_NUMBER>` to the pull request description.
- Add an entry to `CHANGELOG.md` under "Unreleased".

## Writing

Write comments, docblocks, commit messages and documentation in ASD-STE100 Simplified
Technical English:

- Use short sentences: no more than 20 words in procedures and 25 words in descriptions.
- Use the active voice and the simple tenses.
- Write instructions in the imperative.
- Do not use phrasal verbs.
