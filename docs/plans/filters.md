# Filter the pages by period, comparison and supplier

| Issue | Status | Needs |
| --- | --- | --- |
| [#82](https://github.com/itk-dev/koerselsoverblik/issues/82) | Ready | – |

See the [rules for plans](README.md#rules).

## Implementation

- Rename the panel title "Rettidighed pr. leverandør" to "Punktlighed pr. leverandør"
  (`punctuality.suppliers.title`), together with the page and the menu item. The issue does not
  name this panel.
- Do this feature before #85 and #87, if possible:
  - #85 calculates the 12 months up to the period, so it needs the final periods.
  - #87 must apply the supplier filter to the locations.
