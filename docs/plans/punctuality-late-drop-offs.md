# Make the late drop-offs clear on the punctuality page

**Issue:** [#86](https://github.com/itk-dev/koerselsoverblik/issues/86)

See the [rules for plans](README.md#rules).

## Implementation

- The functional test compares each table row as a list of cells in column order
  (`tests/Functional/PunctualityPageTest.php`). Change the expected rows first, then the
  template.
- The labels of the three delay columns exist already in `punctuality.band.*`. The split bar
  uses them.
- `SupplierPunctualityDto::$figures->bands` has `upTo15` already. No change to the calculation
  is necessary.
