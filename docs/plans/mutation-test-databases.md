# Give each Infection thread its own test database

| Issue | Status | Needs |
| --- | --- | --- |
| [#83](https://github.com/itk-dev/koerselsoverblik/issues/83) | In progress | – |

See the [rules for plans](README.md#rules).

## Implementation

The work is on the branch `feature/issue-83-mutation-test-databases`. The full run passes. The
pull request is not open yet.

- The threads have numbers from 1, so the databases are `db_test1` to `db_test4`.
- Infection runs only the tests that cover a mutant, with a timeout of 60 seconds.
- The gate fails on escaped, timed-out and skipped mutants. Outside the gate, a timeout counts as
  detected.
