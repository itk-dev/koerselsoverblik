# Add the distances and the durations to the totals of each route and day

**Issue:** not created. Its row in [Driving data](README.md#driving-data) shows what the work
needs.

See the [rules for plans](README.md#rules).

## Issue text

Write the issue text when the transport office answers the two open decisions. The answers
decide how the trips, the peak passengers, the solo runs and the empty kilometres count.

## Implementation

The goal: the overview reads its remaining figures from the totals of each route and day
([route-day-totals.md](route-day-totals.md)). That table replaces the earlier design of
`DailyStats`, with one row for each date and supplier.

- Add the driven and the empty kilometres, and the driven and the empty minutes, to `RouteRun`.
  The route run feature (#57) left them out until the answer.
- Add the same counters as columns to `RouteDayTotals`, with a migration.
- `RouteDayTotalsBuilder` adds the counters of the route runs of each route and day. Follow the
  answers of the open decisions for the trips, the solo runs and the stops with no registration.
- The supplier and the ignored flag stay in the read queries (`CountedRouteFilter`). Thus a
  change of a sub-agreement needs no rebuild.
- Run `driving-data:rebuild-totals` after the deploy, so that the existing dates get the new
  columns.
- The `base` scenario must give its `expected` totals.
- The citizens figure is not in the table. The overview counts the distinct person ids from
  `StopRow`.

## Open points

- None.
