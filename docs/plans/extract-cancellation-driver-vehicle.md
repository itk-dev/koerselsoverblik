# Add the cancellation, driver and vehicle columns to the extract

**Issue:** [#177](https://github.com/itk-dev/koerselsoverblik/issues/177)

See the [rules for plans](README.md#rules).

## Implementation

- The published report has the six columns. Two extracts of one day each agree with the `default`
  mapping: the validate command gives no rejected rows and no warnings. It shows
  `afbestillingskanal`, `aflysningstype`, `aflysningskategori`, `vogn` and `chauffør` as columns
  that are not read.
- Add `afbestillingskanal`, `aflysningstype`, `aflysningskategori` and `chauffør` to
  `keep_columns`. `aflysningstidspunkt` is in the list already.
- Do not keep `vogn` yet. Wait until the extract has complete vehicle data (see
  [Open points](#open-points)). Until then, the validate command shows `vogn` as a column that is
  not read.
- `chauffør` is the internal id of the driver in the planning system, not a name. Add it to the
  ids of the planning system that the copy keeps in
  [ADR 006](../adr/006-keep-a-copy-of-each-imported-row.md). Never show the driver id, or write
  it to a log or to the output of a command.
- `aflysningstidspunkt` has the same number format as `StatusUpdateTime`, a date and a time.
  Check that the copy keeps it as a date and a time (`{"datetime": ...}`), in Europe/Copenhagen.
- A field that reads the category must accept a new value with no warning. Do not use a `map`
  with a fixed list of categories.
- Give the new columns realistic values in the development dataset (`DevDatasetGenerator`).
- Update the open decisions "Driver changes", "No-shows" and "Cancellation cause" in
  `docs/architecture.md`. The extract has the columns now. Add the question about the shared
  vehicle records (see [Open points](#open-points)).

## Data in the new extracts

The cancellation columns:

- The channel (`afbestillingskanal`), the type (`aflysningstype`) and the category
  (`aflysningskategori`) have a value only on a cancelled row. A cancelled row always has a
  channel and a type.
- The pickup and the drop-off of a cancelled trip have the same values. Count the trips, not the
  rows.
- The type follows from the channel:

  | Channel | Type |
  | --- | --- |
  | `Passenger app` | `By the passenger` |
  | `Driver app` | `Passenger did not show up` |
  | `Office portal` | `Other reasons` |
  | `Institution portal` | `Other reasons` |
  | `System` | `By trip request` |

- The categories of each channel:

  | Channel | Categories |
  | --- | --- |
  | `Passenger app` | `Cancelled by a passenger or a relative` |
  | `Driver app` | `Afbud ved dør`, `NoShow` |
  | `Office portal` | `Afbestilt tidligt`, `Afbestilt sent`, `Afbud ved dør`, `NoShow` |
  | `Institution portal` | empty, `Afbestilt tidligt`, `Afbestilt sent`, `NoShow` |
  | `System` | `Trip request has expired`, `Trip is paused`, `Wrong day of the route group`, `Replaced by another trip request` |

- The list of categories is not fixed. The two days have different categories from `System`.
  Some categories are in Danish, and some are in English.
- The office portal always sets a category. The institution portal often sets no category.
- `NoShow` comes from the driver app, the office portal and the institution portal. The type
  `Passenger did not show up` also has the category `Afbud ved dør`.
- Some cancellations are on an earlier date than the service date.

The vehicle column:

- `vogn` is the internal id of the vehicle, not the vehicle number. The report reads the vehicle
  number as `vogn-nr` from `vogn`, but it does not select it. Each `vogn` has one `vogn-nr`, so
  `vogn-nr` cannot split a shared id into more vehicles.
- A route has one vehicle on a day. The exception is the booked trips ("Turbestilling"), an
  ignored sub-agreement.
- Some vehicle ids are on many routes at the same time. Such an id is not one vehicle. A count of
  the different ids gives too few vehicles.

The other columns:

- The report does not have the columns of `exclude_columns` any more.
- The report does not have 29 of the columns in `keep_columns` any more, for example `RouteID`,
  `TripRequestID`, `PlannedArrival` and `IsLocked`.

## Open points

- Which rule finds the fixed driver of a route? This is a question for the "Show the driver
  changes" issue. See "Driver changes" in [Open decisions](../architecture.md#open-decisions).
- What are the vehicle records that are on many routes at the same time? Are they placeholders,
  for example for a taxi contract? Does the planning system record the real vehicle of these
  routes in a different place? If not, "Show the number of vehicles" can count only the ids that
  are on one route at a time, and must show the shared ids as a separate group. The product owner
  in the transport office answers before "Show the number of vehicles". When the extract has
  complete vehicle data, add `vogn` and the new vehicle columns to `keep_columns`.
- Which rule finds a no-show: the category `NoShow`, the type `Passenger did not show up`, or
  both? "Show the no-shows" needs the answer. See "No-shows" in
  [Open decisions](../architecture.md#open-decisions).
- How does `afbestillingskanal` map to the groups of the prototype (citizen, institution,
  transport office, supplier)? This is a question for the "Show who cancels and why" issue.
- Must `keep_columns` keep the 29 columns that the report does not have any more? A missing kept
  column is not an error. The validate command shows it.
