# Show the ten locations with the lowest punctuality on the punctuality page

**Issue:** [#87](https://github.com/itk-dev/koerselsoverblik/issues/87)

See the [rules for plans](README.md#rules).

## Implementation

Since #136, the page reads only derived tables that the import builds. Thus the import also counts
the stops of each location, route and date in a new derived table `LocationDayTotals`. The page
does not read the stop rows.

1. **Entity.** `LocationDayTotals` has the route, the service date, the location name, the
   tolerance of the build, and the measured stops and the stops on time. A unique constraint is on
   the route, the date and the location. The table has all routes, because the queries apply the
   filters. It has no person ids. Make the migration with `doctrine:migrations:diff`.
2. **Count at import time.** `StopRowRepository::countLocationDayTotals()` counts the completed
   pickups and drop-offs with a delay and a location name ("pr. borger" rule). It groups them by
   route, date and location.
3. **Repository.** `LocationDayTotalsRepository` deletes a range or all rows.
   `sumOfEachLocation()` adds the rows of each location in a range. It applies
   `CountedRouteFilter`, so that an ignored sub-agreement adds no locations, and the supplier filter
   applies. It throws `OtherToleranceException` for a row with another tolerance.
4. **Builder.** `LocationDayTotalsBuilder` builds the rows of a range again, as
   `RouteDayTotalsBuilder` does. `DrivingDataImporter` and `app:driving-data:rebuild-totals` call
   it.
5. **Report.** `LocationPunctualityDto` has the location name and a `PunctualityCountDto`.
   `PunctualityCalculator::calculate()` gives the 10 worst locations in `PunctualityReportDto`.
   The order is the punctuality, then more measured stops first, then the name. Only the selected
   period calls `calculate()`.
6. **Page.** A panel after the supplier table and before the worst routes. The columns are the
   location, the measured stops, the late stops and the punctuality with its tone. The rows have
   `data-test="worst-location-row"`.

The development dataset already has invented institution names (#75).
