# Show the conclusion on the overview

**Issue:** [#122](https://github.com/itk-dev/koerselsoverblik/issues/122)

See the [rules for plans](README.md#rules).

## Implementation

- Calculate the measures from the figures that the overview already has. Do not add queries for
  them:
  - "Rettidighed" comes from the punctuality figures of #109.
  - "Reelt solo" comes from `SoloCountDto` of #110.
  - "Aflyste ture" comes from `TripCountDto::$cancelled` and `TripCountDto::$trips` of #106.
- Use the selected period and the comparison period of `PeriodRangeFactory` (#115).
- Put the rules for the threshold and the headline in a plain class in `src/Report`. Unit tests
  cover each combination of better, worse and unchanged.
- Read `m` and `t` together with the other query parameters in `PeriodFilter` (#115). Fall back
  to the defaults for values that are not valid.
- Keep `m` and `t` in the links of the filter bar of #116.

## Open points

- Find whether the share of cancelled trips uses all trips or only the trips with a registration.
  Use the same rule as the overview of #106.
