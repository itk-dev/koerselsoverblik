# Add the cancellation, driver and vehicle columns to the extract

**Issue:** [#177](https://github.com/itk-dev/koerselsoverblik/issues/177)

See the [rules for plans](README.md#rules).

## Implementation

- Start when the published report has the six columns. Use an extract of one day from the new
  report to check the column names and the cell types.
- `aflysningstidspunkt` is a date and a time. Check that the copy keeps it as a date and a time
  (`{"datetime": ...}`), in Europe/Copenhagen.

## Open points

- Which values do `aflysningstype`, `afbestillingskanal` and `aflysningskategori` have in real
  data? The values of the type are from the documentation. Nobody has seen them in an extract.
- Do the staff choose a category when they cancel a trip? If not, "why" is empty for most trips.
- How does `afbestillingskanal` map to the groups of the prototype (citizen, institution,
  transport office, supplier)? This is a question for the "Show who cancels and why" issue.
- Which rule finds the fixed driver of a route? This is a question for the "Show the driver
  changes" issue.
