# Show the imported stop rows and rejected rows of a date range

**Issue:** [#96](https://github.com/itk-dev/koerselsoverblik/issues/96)

See the [rules for plans](README.md#rules).

## Implementation

### Changes to the issue text

- "Kørselsdata" is a third tab in `templates/admin/import/_tabs.html.twig`: "Én fil", "Mange filer" and
  "Kørselsdata". The issue was written before the upload got two tabs
  ([#153](https://github.com/itk-dev/koerselsoverblik/issues/153)).
- The list of uploaded dates ([#81](https://github.com/itk-dev/koerselsoverblik/issues/81)) moves from the
  "Én fil" tab to the "Kørselsdata" tab, with the month stepper and the clear action. A date and its counts
  link to the rows of that date. The upload tabs only upload.

### Routes

`App\Controller\Admin\DrivingDataController`, with `#[IsGranted('ROLE_MANAGE_DRIVING_DATA')]` and
`#[Route('/admin/driving-data')]`:

| Action | Route | Path | Content |
| --- | --- | --- | --- |
| `index` | `admin_driving_data` | – | The month stepper and the uploaded dates |
| `rows` | `admin_driving_data_rows` | `/rows` | The range form, the stop rows and the rejected rows |
| `stopRow` | `admin_driving_data_stop_row` | `/stop-row/{id}` | One stop row and its copy |
| `rejectedRow` | `admin_driving_data_rejected_row` | `/rejected-row/{id}` | One rejected row and its copy |

- `index` gets the code of the dates list from `ImportController::index`: `MonthDto::fromQuery()`,
  `LocalCalendar::today()`, `ImportBatchRepository::firstDate()` and `UploadedDates::forMonth()`. Remove
  these dependencies and the `month` parameter from `ImportController`.
- `ClearDrivingDataController` gets the path `/admin/driving-data/clear/{date}` and the name
  `admin_driving_data_clear`. It redirects to `admin_driving_data` with the month.
- `MainMenu`: the item `menu.upload` gets the active prefixes `admin_import` and `admin_driving_data`.

### Range form

- `App\Admin\RowsQueryDto` binds to the form: `firstDate`, `lastDate` and `route` (optional).
  `range(int $maxDays)` gives the `ImportRangeDto`, as `UploadDto::range()` does.
- `App\Form\RowsQueryType`, with `method: GET`, `csrf_protection: false` and the block prefix `''`. Then the
  query is `?firstDate=…&lastDate=…&route=…`. The date fields are as in `ImportType`.
- The controller reads the page numbers `stopPage` and `rejectedPage` with `MapQueryParameter`.
- `ValidImportRangeValidator` accepts the interface `App\Import\DateRangeInput`, with the interface
  properties `firstDate` and `lastDate`. `UploadDto` and `RowsQueryDto` implement it.
- With no query, the first and the last date are yesterday (`LocalCalendar::today()`). With a range that is
  not valid, the page shows the form errors and no lists.

### Repositories

- `StopRowRepository::listInRange()` gives the query of the stop rows, with the range and the optional
  route. Use the private `inRange()`, then compare `route.sourceKey`. MariaDB compares text with no regard
  to case. Join `Route` and `ImportBatch`. Do not join the copy: the relation is lazy. Order by date,
  route, run, planned time and id.
- `RejectedStopRowRepository::listInRange()`: use the private `inRange()`, so that the list and the
  delete cannot differ. Order by date (no date last), upload, row number and id.
- The route filter does not apply to the rejected rows. The page tells this.

### Pagination

- KnpPaginatorBundle pages the queries (`App\Admin\DrivingDataRows`). The page size is 100.
- Each list has its own page parameter: `stopPage` and `rejectedPage`. The links keep the other query
  values.
- A page number after the last page gives the last page (`page_out_of_range: fix`).
- A URL cannot sort or filter the lists. A filter on the person id would tell if a row has a value.
- `templates/_pagination.html.twig` shows the pages with the stepper of the design system.

### Values of the copy

- `App\Admin\SourceValueFormatter` gives the display text of one value of the type `SourceValue`
  (`SourceCells`): a duration as hours and minutes (`25:10`), a date and time as `d-m-Y H:i` with no time
  zone conversion, an Excel error as its code, and a boolean as "Ja" or "Nej".
- `App\Admin\SourceRowView` makes the columns and the display texts of one copy, with the mapping from
  `MappingProvider`:
  - The person id column gets "(skjult)", also when it is a row id column.
  - On a rejected row, it marks the column of the field that caused the rejection. With no field, it marks
    the row id columns.
  - If the mapping no longer exists, the view cannot find the person id column. Then it shows no copy, and
    the page tells that the mapping is missing.
- The mapping name of a stop row is `StopRow::getMappingName()`. The mapping name of a rejected row is the
  mapping name of its upload.

### Templates

- `templates/admin/driving_data/`: `index`, `_dates` (moved from `admin/import/`), `rows`, `_pagination`,
  `stop_row`, `rejected_row` and `_source_row`.
- Translate the reasons and the fields with `upload.reason.*` and `upload.field.*`. Add the missing
  `upload.field.location_name`.
- Use `data-test` attributes on the lists, the rows, the pagination, the hidden values and the cause.
- The cause row gets a CSS class, not a `style` attribute.

### Tests

Write each test first.

- **Unit:** `PageDto`, `SourceValueFormatter`, `SourceRowView` and the validator with both DTOs.
- **Integration:** the repository methods, with rows inside and outside the range, a rejected row with no
  date of a smaller and of a larger upload, the route filter in another case, the order, and the offset.
- **Functional:** 403 without the role; the dates list on the new tab; the clear action on the new route;
  the default range; the lists of an imported fixture; a range that is too long; page 2; the detail pages;
  no person id and no `style` attribute in the responses.

### Documentation

- README: a section "Driving data" with the tab, the dates list, the filter, the detail pages and the
  hidden person id. Change "Clear driving data" to the new place of the dates list.
- `docs/architecture.md`: the new controller, and the rule for the person id on the screen.
- `CHANGELOG.md`: an entry under "Unreleased".

## Open points

- The detail page of a rejected row shows the cell values of the extract on the screen. ADR 004 and ADR 006
  allow these values in the database for the same users. The logs and the validate command must still not
  show them. Ask the data protection adviser if the screen needs a separate role.
- The route filter does not apply to the rejected rows, because a rejected row has no route. A filter on
  the route name in the copy needs a JSON query. Add it only if the transport office asks for it.
- The menu item "Upload" now also opens "Kørselsdata". A new label is a separate change.
