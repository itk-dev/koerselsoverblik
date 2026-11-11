# Filter the pages by period, comparison and supplier

**Issue:** [#82](https://github.com/itk-dev/koerselsoverblik/issues/82)

See the [rules for plans](README.md#rules).

## Implementation

- Use the terms of the prototype:
  - "Punktlighed" is the name of the page, the menu item and the supplier panel. Rename the
    panel title `punctuality.suppliers.title` and its caption `punctuality.suppliers.caption`
    to "Punktlighed pr. leverandør". The issue does not name this panel.
  - "Rettidighed" is the name of the measure. Keep it in the other panels, for example
    "Rettidighed måned for måned" and "De ti vognløb med lavest rettidighed".
- Do this feature before #85 and #87, if possible:
  - #85 calculates the 12 months up to the period, so it needs the final periods.
  - #87 must apply the supplier filter to the locations.
