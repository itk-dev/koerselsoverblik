# Filter the pages by period, comparison and supplier

**Issue:** [#82](https://github.com/itk-dev/koerselsoverblik/issues/82), with the sub-issues
[#115](https://github.com/itk-dev/koerselsoverblik/issues/115),
[#116](https://github.com/itk-dev/koerselsoverblik/issues/116),
[#117](https://github.com/itk-dev/koerselsoverblik/issues/117),
[#118](https://github.com/itk-dev/koerselsoverblik/issues/118) and
[#122](https://github.com/itk-dev/koerselsoverblik/issues/122)

See the [rules for plans](README.md#rules).

## Implementation

- The page keeps the name "Rettidighed". Do not use the name "Punktlighed" of the prototype.
- The filters apply to the punctuality page and to the overview.
- Decide how to compare a period that is not complete, for example the current month. See
  "Partial month" in the open decisions of [docs/architecture.md](../architecture.md#open-decisions).
- Do this feature before #85 and #87, if possible:
  - #85 calculates the 12 months up to the period, so it needs the periods of #115.
  - #87 must apply the supplier filter of #117 to the locations.
