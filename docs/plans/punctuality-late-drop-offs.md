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
- Give the tiles "Forsinkede afsætninger" (`late-runs`) and "Over 30 minutter" (`over-30`) a
  help popover, as in the prototype. Use the `help` block of `twig:Tile` and `twig:HelpPopover`,
  as in `templates/punctuality/_goal.html.twig`.
  - Add the texts as `punctuality.late_runs_help.title|body` and
    `punctuality.over_30_help.title|body` in `translations/messages+intl-icu.da.yaml`. Use
    `{minutes}` for the tolerance.
  - Tell that a late run is a run with its last drop-off more than `{minutes}` minutes after the
    planned time. This is the same measure as "Afsætning pr. vognløb". Cancelled runs are not
    included.
  - The functional test asserts the title of each popover by its text or a `data-test`
    attribute, not by the markup.
