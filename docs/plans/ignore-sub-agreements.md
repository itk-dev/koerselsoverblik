# Ignore a sub-agreement in the figures

**Issue:** [#90](https://github.com/itk-dev/koerselsoverblik/issues/90)

See the [rules for plans](README.md#rules).

## Implementation

Start after #91 is merged. This plan uses the edit page of a sub-agreement and
`findAllWithoutSupplierFirst()` from #91.

Write each test first.

### Entity and migration

- `src/Entity/SubAgreement.php`: add `#[ORM\Column(options: ['default' => false])] private bool
  $ignored = false;`, with `isIgnored()` and `setIgnored(bool)`.
- Generate the migration with `task console -- doctrine:migrations:diff`. Description: "Add the
  flag that excludes a sub-agreement from the figures".
- Test: `tests/Unit/Entity/SubAgreementTest.php`. The default is false, and the setter sets and
  clears the flag.

### Report queries

The filter is in the queries, not in the import.

- `StopRowRepository::countMeasuredStops()`: join `row.route` and `route.subAgreement`, and add
  `subAgreement.ignored = false`. Update the docblock.
- `RouteRunRepository::countRunFigures()`: the same filter on `run.route`.
- `PunctualityCalculator` does not change. `PunctualityPageBuilder` calls it for each month, so
  the trend also excludes the rows.
- Test: `testIgnoredSubAgreementAddsNothing()` in
  `tests/Integration/Report/PunctualityCalculatorTest.php`. Use one normal route and one route of
  an ignored sub-agreement, with late stops and late runs. Assert the total, the suppliers and the
  worst routes. Clear the flag, and assert that the figures come back.

### Administration

- `SubAgreementType`: add `ignored` as a `CheckboxType`, not required, with a help text.
- `templates/admin/sub_agreement/show.html.twig`: show the status (`data-test="ignored"`).
- `templates/admin/sub_agreement/index.html.twig`: for an ignored sub-agreement, show the tag
  `data-test="ignored"` in place of the supplier warning.
- `SubAgreementRepository::findAllWithoutSupplierFirst()`: rename it to
  `findAllNeedingSupplierFirst()`. Sort by
  `CASE WHEN subAgreement.supplier IS NULL AND subAgreement.ignored = false THEN 0 ELSE 1 END`,
  and then by the reference.
- Translations under `sub_agreement`:

  | Key | Danish text |
  | --- | --- |
  | `ignored` | Ignorér i beregningerne |
  | `ignored_help` | Vognløbene i delaftalen indgår ikke i tallene. Importen gemmer stadig kørselsdata. |
  | `ignored_tag` | Ignoreret |

- Tests:
  - `tests/Functional/Admin/SubAgreementAdminTest.php`: the administrator ignores a
    sub-agreement on the edit page, and includes it again. Create an ignored sub-agreement. The
    list shows the tag, and no `no-current-supplier`, for an ignored sub-agreement.
  - `tests/Integration/Repository/SubAgreementRepositoriesTest.php`: an ignored sub-agreement
    with no supplier sorts with the sub-agreements that have a supplier.

### Documentation

- `README.md`, "Map sub-agreements to suppliers": add a procedure to ignore a sub-agreement, with
  "Turbestilling" as the example.
- `docs/architecture.md`:
  - `bool ignored` in the `SUB_AGREEMENT` block of the ER diagram
  - the sub-agreement term, the `SubAgreement` row and "Calculating the figures"
  - the open decision "Booked trips": answered. The booked trips have the route name
    "Turbestilling", and an administrator ignores this sub-agreement.
- `docs/plans/daily-stats.md`: the aggregator skips ignored sub-agreements. A change of the flag
  rebuilds `DailyStats` for all dates of the routes of the sub-agreement.
- `docs/plans/punctuality-worst-locations.md` and `docs/plans/punctuality-months-query.md`: the
  new queries use the same filter.
- `CHANGELOG.md`: add an entry under "Unreleased".

### Verification

- Run `task db:migrate`, and then `task console -- doctrine:schema:validate`.
- Run `task prepare-code`. It runs the checks, the coverage gate and Infection.
- Import a fixture with a "Turbestilling" route. Open the punctuality page, and note the figures.
- Ignore the sub-agreement on its edit page. Make sure that the figures on the punctuality page
  change.
- Include the sub-agreement again. Make sure that the figures come back.

## Open points

- Does the transport office know other route names than "Turbestilling" that are not route runs?
