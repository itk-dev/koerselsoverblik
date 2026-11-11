# Read a stop row from its stored copy

**Issue:** [#61](https://github.com/itk-dev/koerselsoverblik/issues/61)

See the [rules for plans](README.md#rules).

## Open points

- [ADR 006](../adr/006-keep-a-copy-of-each-imported-row.md) lists a later command that reads all
  stored copies again through a changed mapping. A copy leaves out the empty cells, so it has no
  header. That command needs a rule for a column that is in no copy. This feature does not add
  the command.
