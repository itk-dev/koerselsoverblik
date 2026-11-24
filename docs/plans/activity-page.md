# Show the driving activity page

**Issue:** [#163](https://github.com/itk-dev/koerselsoverblik/issues/163)

See the [rules for plans](README.md#rules).

## Implementation

- `ActivityController`: copy `PunctualityController`. It reads `p`, `d`, `c` and `s`, and
  redirects a query that is not canonical. It gets the ignored sub-agreements from
  `SubAgreementRepository::findIgnoredReferences()`.
- `ActivityPageBuilder`: copy `OverviewPageBuilder`.
  - `RouteDayTotalsRepository::sumByMonth()` gives the 12 months in one query.
  - A period or a comparison period that has the dates of a month of the history uses that
    month (`PeriodSelectionDto::monthIndexOf()`). Other periods use `sum()`.
  - `ActivityPageDto` gives a `CountTrendDto` for each count and a `ShareTrendDto` for the
    cancelled share.
- The two tables: one `sumOfEachRoute()` and one `RouteRepository::findWithSuppliers()` give the
  rows of each sub-agreement and each supplier. Make a new `ActivityTableCalculator`, or extend
  `SupplierTableCalculator`, which already sums the trips and the cancelled trips of each
  supplier.
- The new counter touches the entity, `DayTotalsDto::FIELDS`, the constructor, `getTotals()`,
  `RouteRunRepository::countDayTotals()` and a migration.
- The table of the sub-agreements reads `sumOfEachRoute()`, which has no supplier filter. Leave
  out the routes of the other suppliers and of the ignored sub-agreements in PHP, or add the
  filter to the query.
- There is no ranked-bar component. The prototype shows the trips of each sub-agreement as
  ranked bars. Use a table, as the worst routes of the punctuality page.
- Templates: `activity/index.html.twig` with `Tile`, `Panel`, `DistributionBar`, `ColumnChart`
  and `LineChart`. Translation keys under `activity.` and `menu.activity`.
- Add the new counter to the `expected` totals of `tests/fixtures/driving_data/base.yaml`.
- Tests:
  - unit: `RouteDayTotalsTest`
  - integration: `RouteRunRepository`, `ActivityPageBuilderTest`, the table calculator,
    `MainMenuTest`
  - functional: `ActivityPageTest`, as `PunctualityPageTest`

## Open points

- Does the transport office have a target for the share of the cancelled trips? Until it has
  one, the tile "Aflyste ture" has no tone. The prototype uses a tone, but its limits are not
  from the transport office.
