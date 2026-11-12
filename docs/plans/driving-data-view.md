# Show the imported stop rows and rejected rows of a date range

**Issue:** [#96](https://github.com/itk-dev/koerselsoverblik/issues/96)

See the [rules for plans](README.md#rules).

## Implementation

### Needs

- The detail pages read the copy with `getSourceRow()->getValues()`. #95 moved the copy to
  `SourceRow`. A list query must not read the copies: the relation is lazy, so do not join it.
- #81 shows the uploaded dates. When both features are merged, link each date of #81 to this page
  with the date as the first and the last date.

### Query and form

- `App\Form\DrivingDataQueryType`, with `method: GET` and `csrf_protection: false`. It binds to
  `App\Admin\DrivingDataQueryDto`: `firstDate`, `lastDate`, `route` (optional) and `page`
  (default 1). `ImportType` uses the Form component for the same date fields, so the errors and
  the Danish labels come from the same place.
- Make the range with `ImportRangeDto::create()` and the value of `app.driving_data_max_days`.
  Show `InvalidImportRangeException` as a form error, as the upload form does.
- With no query, the form has the date of yesterday as the first and the last date, from
  `ClockInterface` (ADR 005).

### Repositories

- `StopRowRepository::findPageInRange()`, with the range, the optional route, the page and the
  page size: a DQL query with a join to `Route` and `ImportBatch`. Order by date, route name, run, planned
  time and id. The route filter compares the route name. MariaDB compares text with no regard to
  case, as the replacer expects.
- `StopRowRepository::countInRange()` exists. Add the optional route to it, or add a second count
  method.
- `RejectedStopRowRepository` (from [range-deletes.md](range-deletes.md)): add
  `findPageInRange()` and `countInRange()`. Use its private condition method of the range, also
  for the rows with no date. Then the delete and the list cannot differ. Order by date, upload,
  row number and id.
- The list queries do not read the copy. Doctrine does not load it (see the needed feature).

### Pagination

- No pagination package is installed. Add a `readonly` `App\Admin\PageDto` with the items, the
  page number, the page size and the total. It calculates the number of pages, and if a previous
  and a next page exist. A unit test covers the edge cases: 0 rows, exactly one full page, and a
  page number after the last page.
- The page size is 100. A page number after the last page gives the last page.
- The previous and next links keep the query, with `path()` and the current query values.

### Values of the copy

- `App\Admin\SourceValueFormatter`: a plain PHP class that gives the display text of one
  `SourceValue`. A duration shows as hours and minutes (`25:10`), a date and time as `d-m-Y H:i`
  in the PHP default time zone, an Excel error as its code, and a boolean as "Ja" or "Nej".
- `App\Admin\SourceRowView`: makes the list of columns and display texts for one copy. It gets the
  mapping of the row from `MappingProvider` (`StopRow::getMappingName()`, or the mapping name of
  the upload of a rejected row). It replaces the value of the person id column with "(skjult)".
  If the mapping no longer exists, the view cannot find the person id column. Then it shows no
  copy, and the page tells that the mapping is missing.

### Controller and templates

- `App\Controller\Admin\DrivingDataController`, with `#[IsGranted('ROLE_MANAGE_DRIVING_DATA')]`
  and `#[Route('/admin/driving-data')]`:
  - `index` (`admin_driving_data`): the form and the two lists.
  - `stopRow` (`admin_driving_data_stop_row`, `/stop-row/{id}`): one stop row.
  - `rejectedRow` (`admin_driving_data_rejected_row`, `/rejected-row/{id}`): one rejected row.
- Templates in `templates/admin/driving_data/`. A tabs partial `_tabs.html.twig` for "Upload" and
  "Kørselsdata", used also by `templates/admin/import/index.html.twig`. The existing
  `admin/_tabs.html.twig` belongs to `ROLE_MANAGE_SUPPLIERS`, so do not add the tab there.
- Translate the reasons and the fields with the keys of the result page, `upload.reason.*` and
  `upload.field.*`.
- Use `data-test` attributes on the lists, the rows, the pagination and the hidden values.

### Tests

Write each test first.

- **Unit:** `PageDto`, `SourceValueFormatter`, `SourceRowView` (the person id and the row key are
  hidden, a missing mapping), and `DrivingDataQueryDto`.
- **Integration:** the four repository methods, with rows inside and outside the range, a rejected
  row with no date of a smaller and of a larger upload, the route filter in another case, and the
  order.
- **Functional:** 403 without the role; the lists of an imported fixture; a range that is too long;
  page 2; the detail page of a stop row and of a rejected row; no person id in the response.

### Documentation

- README: a section "Driving data" under the upload, with the page, the filter and the hidden
  person id.
- `docs/architecture.md`: the new controller and the rule for the person id in the security
  section.
- `CHANGELOG.md`: an entry under "Unreleased".

## Open points

- The detail page of a rejected row shows the cell values of the extract on the screen. ADR 004
  and ADR 006 allow these values in the database for the same users. The logs and the validate
  command must still not show them. Ask the data protection adviser if the screen needs a
  separate role.
- The route filter does not apply to the rejected rows, because a rejected row has no route. A
  filter on the route name in the copy needs a JSON query. Add it only if the transport office
  asks for it.
