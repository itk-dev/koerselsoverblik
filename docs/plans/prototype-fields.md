# Read the remaining prototype fields from the extract

**Issue:** [#75](https://github.com/itk-dev/koerselsoverblik/issues/75)

See the [rules for plans](README.md#rules).

## Implementation

- #87 (the ten locations) needs the field `location_name`. Do this feature first.
- The application is not live yet, so no stored data needs a fill. A new upload fills the new
  fields.
- Add five cases to `FieldName`: `passengers` (int), `price_per_minute` (float),
  `cancellation_notice_min` (float), `cancellation_in_time` (bool) and `location_name`
  (string). No case must be required.
- Each mapping must name each field, so add the five fields to `default.yaml` and to the test
  mappings in `tests/fixtures/mappings/`.
- The `default` mapping keeps the five columns in `keep_columns` now. A kept column cannot also be
  a field column, so remove them from that list. The copy of a row still holds them, because the
  copy holds all field columns.
- In the reference extract, `RettidigAflysningsvarsel` has the codes `yes` and `no`.
- Add the five values to `StopRowDto`, and five nullable columns to `StopRow`, with one migration.
- `DevDatasetGenerator` writes the five columns as kept columns. Write them as field values.
- The preview of the validate command shows `location_name` as `(hidden)`, as the person id.
  The warnings already have no values.
- Update the field table and the example in the README, and "The reference extract" and the
  diagram in `docs/architecture.md`.

## Open points

- The meaning of `RettidigAflysningsvarsel` and the unit of `aflysningsvarsel` are not confirmed.
  The issue lists them as open questions. The fields store the values as they are.
