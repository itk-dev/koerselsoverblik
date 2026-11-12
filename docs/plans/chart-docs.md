# Explain each figure of the user interface

**Issue:** [#128](https://github.com/itk-dev/koerselsoverblik/issues/128)

See the [rules for plans](README.md#rules).

## Implementation

- The figures of the punctuality page are in `templates/punctuality/index.html.twig`. The values
  come from `PunctualityPageDto`, `PunctualityCalculator`, `StopRowRepository::countMeasuredStops()`
  and `RouteRunRepository::countRunFigures()`.
- Document these filters. Check each filter again in the code before you write it:
  - The measures for each citizen count only the stops with the status `completed` and a
    `delay_seconds`.
  - The measures for each route run count only the measured first pickups and last drop-offs.
    A cancelled stop is never the first pickup or the last drop-off (`RouteRunCalculator`).
  - All figures leave out the stops and the runs of an ignored sub-agreement
    (`IgnoredSubAgreementFilter::apply()`).
  - All figures leave out the dates outside the month. The page shows no month after the
    current month (`MonthDto`).
  - The worst routes leave out the routes with no measured last drop-off, and all routes after
    the first 10.
  - The routes of a sub-agreement with no supplier are not left out. They count as "Unknown".
- Move the explanation of the figures from "Punctuality" in `docs/architecture.md` to
  `docs/charts.md`, and keep a link. Keep the design rules (cost, queries, format) in the
  architecture.
- Write the rule in `AGENTS.md`, under "Rules for the code":

  > Each chart, tile and table in the user interface has a section in
  > [docs/charts.md](docs/charts.md). The section tells what the figure counts and which data it
  > leaves out. A pull request that adds or changes a figure, or a filter of its data, updates
  > the section.

- In `docs/architecture.md`, add the same rule as a paragraph "Figures" in "User interface".
- The overview issues #106, #109, #110 and #113 are in progress. If one of them merges after
  this issue, its pull request adds its own section, as the rule tells.

## Open points

None. No test checks `docs/charts.md`. The rule in `AGENTS.md` and the review of each pull
request keep the file correct.
