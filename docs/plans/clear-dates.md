# Show the uploaded dates and clear the driving data of a date

| Issue | Status | Needs |
| --- | --- | --- |
| [#81](https://github.com/itk-dev/koerselsoverblik/issues/81) | Ready | – |

See the [rules for plans](README.md#rules).

## Implementation

- The upload page lists the dates of one month (`?month=YYYY-MM`), with the stop rows, the route
  runs and the last upload. An invalid month gives the current month.
- `DrivingDataClearer` deletes `StopRow` and `RouteRun` for a range, in one transaction.
  `DailyStats` does not exist yet. The daily stats feature adds it to the clearer.
- The clearer logs who cleared which range, and the deleted counts. The log has no field values.
- `Admin\ClearDrivingDataController` (`ROLE_MANAGE_DRIVING_DATA`) has one POST action for one
  date, with CSRF. A native `popover` asks for the confirmation, so no script is necessary.
- `app:driving-data:clear <FROM_DATE> <TO_DATE>` uses the same service.
- "Clearing driving data" in `docs/architecture.md` and "Clear driving data" in the README
  describe the result already. Make the code agree with them.
