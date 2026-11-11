# Give each sub-agreement one supplier

**Issue:** [#91](https://github.com/itk-dev/koerselsoverblik/issues/91)

See the [rules for plans](README.md#rules).

## Implementation

Write each test first. The application is not live, so the migration does not copy the
assignments to the new column.

### Entity and migration

- `src/Entity/SubAgreement.php`: add `#[ORM\ManyToOne] #[ORM\JoinColumn(nullable: true)]
  private ?Supplier $supplier = null;`, with `getSupplier()` and `setSupplier()`. Remove
  `$assignments`, `getAssignments()` and `getAssignmentOn()`.
- The foreign key has no `ON DELETE`. Thus the database blocks the delete of a supplier with
  sub-agreements, as the assignments do now. `SupplierController::delete()` does not change.
  Update its docblock.
- Generate the migration with `task console -- doctrine:migrations:diff`. Description: "Give each
  sub-agreement one supplier, and remove the dated assignments".
- Tests: `tests/Unit/Entity/SubAgreementTest.php`. Replace the tests of `getAssignmentOn()`.

### Remove

- `src/Entity/SubAgreementSupplierAssignment.php`
- `src/Repository/SubAgreementSupplierAssignmentRepository.php`
- `src/Controller/Admin/AssignmentController.php`, `src/Form/AssignmentType.php`
- All of `src/Supplier/`: `AssignmentDto`, `AssignmentManager`, `NoOverlappingAssignment`,
  `NoOverlappingAssignmentValidator`, `SubAgreementSupplierChanged`, `ValidityPeriod`
- `templates/admin/assignment/`
- The `assignment` keys in `translations/messages+intl-icu.da.yaml`, and the overlap message in
  `translations/validators+intl-icu.da.yaml`
- `tests/Integration/Supplier/AssignmentManagerTest.php`,
  `tests/Unit/Supplier/NoOverlappingAssignmentValidatorTest.php`,
  `tests/Unit/Supplier/ValidityPeriodTest.php`

### Figures

- `RouteRepository::findWithAssignments()`: rename it to `findWithSuppliers()`. Join
  `route.subAgreement` and left join `subAgreement.supplier`.
- `PunctualityCalculator::calculate()`: get the supplier with
  `$route->getSubAgreement()->getSupplier()?->getName()`. The date is still necessary for the
  key of each route and date.
- `tests/Integration/Report/PunctualityCalculatorTest.php`: remove
  `testSupplierChangeSplitsTheFiguresOnTheDate()`. Change the fixtures to set the supplier on the
  sub-agreement. Keep a test for a sub-agreement with no supplier ("Unknown").

### Administration

- `SubAgreementType`: add `supplier` as an `EntityType` of `Supplier`, sorted by the name, not
  required, with the placeholder "Ingen leverandør". Add the option `edit_reference` (default
  true). The edit page sets it to false and does not add the reference field.
- `SubAgreementController`: add `admin_sub_agreement_edit` on
  `GET|POST /admin/sub-agreements/{id}/edit`. Flush, add a flash, and go to the show page.
  `index()` does not need `LocalCalendar` any more.
- `SubAgreementRepository::findAllWithoutCurrentAssignmentFirst()`: rename it to
  `findAllWithoutSupplierFirst()`, with no argument. Sort by
  `CASE WHEN subAgreement.supplier IS NULL THEN 0 ELSE 1 END`, and then by the reference.
- Templates:
  - `templates/admin/sub_agreement/index.html.twig`: the column "Leverandør" shows
    `sub_agreement.supplier.name`, or the warning tag.
  - `templates/admin/sub_agreement/show.html.twig`: replace the assignment table with the
    supplier (`data-test="supplier"`) and an edit link (`data-test="sub-agreement-edit"`).
  - Add `templates/admin/sub_agreement/edit.html.twig`.
- Translations under `sub_agreement`: `supplier` ("Leverandør"), `no_supplier`
  ("Ingen leverandør – vises som \"Ukendt\""), `edit_title`, `saved`. Remove
  `current_supplier`, `assignments` and `no_assignments`. Change `intro`: the sub-agreements
  with no supplier are at the top. Change `supplier.delete_blocked`: "Fjern først leverandøren
  fra delaftalerne."
- Tests:
  - `tests/Functional/Admin/SubAgreementAdminTest.php`: remove the assignment tests. Add: create
    with a supplier, change the supplier, remove the supplier, the reference is not on the edit
    page, a user without the role gets 403 on the edit page.
  - `tests/Functional/Admin/SupplierAdminTest.php`: a supplier with sub-agreements cannot be
    deleted.
  - `tests/Integration/Repository/SubAgreementRepositoriesTest.php`: the sort order.
  - `tests/Integration/Import/StopRowReplacerTest.php`: a new sub-agreement has no supplier.

### Documentation

- `README.md`: the role table, and "Map sub-agreements to suppliers". Remove the procedure to
  change supplier on a date.
- `docs/architecture.md`:
  - the components diagram (no `Admin/Assignment`, no `Assignment` form) and the `Supplier`
    module row
  - the ER diagram: `SUPPLIER ||--o{ SUB_AGREEMENT`, and `int supplier_id FK` in
    `SUB_AGREEMENT`
  - the terms, the entity table, and the security table
  - remove "Supplier assignments have dates"
  - "Derived tables are always rebuildable": a change of the supplier of a sub-agreement
    rebuilds `DailyStats` for all dates of its routes
  - the import steps and "Calculating the figures"
  - the open decision "Supplier mapping (answered)"
- `docs/plans/daily-stats.md`: the aggregator gets the supplier from the sub-agreement. Replace
  the listener on `SubAgreementSupplierChanged` with a rebuild of all dates when the supplier
  of a sub-agreement changes.
- `CHANGELOG.md`: add an entry under "Unreleased".

### Verification

- Run `task db:migrate`, and then `task console -- doctrine:schema:validate`.
- Run `task console -- lint:container` and `task console -- debug:router`. The assignment routes
  must not be in the list.
- Run `task prepare-code`. It runs the checks, the coverage gate and Infection.
- Import a fixture. Give a sub-agreement a supplier, and make sure that the punctuality page
  shows its routes for that supplier on all dates.

## Open points

- The database still accepts a sub-agreement with no supplier, because the import creates the
  sub-agreements. Is "Ukendt" still correct for such a sub-agreement?
