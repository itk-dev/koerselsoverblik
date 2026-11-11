# Show the ten locations with the lowest punctuality on the punctuality page

**Issue:** [#87](https://github.com/itk-dev/koerselsoverblik/issues/87)

See the [rules for plans](README.md#rules).

## Implementation

- Add `StopRowRepository::countMeasuredStopsByLocation()` next to `countMeasuredStops()`. Use
  the same filters, plus `locationName IS NOT NULL`.
- Add `LocationPunctualityDto` with the location name and a `PunctualityCountDto`.
- Add `PunctualityCalculator::worstLocations()`. Sum the rows for each location, and sort as
  `worstRoutes()` does.
- Call it from `PunctualityPageBuilder`. Add the result to `PunctualityPageDto`.
- Copy the panel of the worst routes in `templates/punctuality/index.html.twig`. The rows have
  `data-test="worst-location-row"`.
- Use the column order of the worst routes after #86: the counts first, the punctuality last.
