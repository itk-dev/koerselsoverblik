# Show the ignored sub-agreements on the punctuality page

**Issue:** [#99](https://github.com/itk-dev/koerselsoverblik/issues/99)

See the [rules for plans](README.md#rules).

## Implementation

Start after #94 is merged. This plan uses the `ignored` flag of #90.

Write each test first.

- `SubAgreementRepository::findIgnoredReferences(): list<string>`. Select only
  `subAgreement.reference`, with `subAgreement.ignored = true`, sorted by the reference.
  - Test: `tests/Integration/Repository/SubAgreementRepositoriesTest.php`. Use two ignored
    sub-agreements and one that is not ignored. Assert the references and the order.
- `PunctualityPageDto`: add `public array $ignoredSubAgreements` (`list<string>`) to the
  constructor. The flag has no dates, so one list applies to all months of the page.
- `PunctualityPageBuilder`: inject `SubAgreementRepository`, and give
  `findIgnoredReferences()` to the DTO. One more query for each page.
  - Test: `tests/Integration/Report/PunctualityPageBuilderTest.php`.
  - Check the tests that create `PunctualityPageDto` directly, for example
    `tests/Unit/Report/PunctualityPageDtoTest.php`. Give them an empty list.
- `templates/punctuality/index.html.twig`: add the note after `page-head__lede`, with
  `data-test="ignored-note"`. Join the references with ", ".
- Translation `punctuality.ignored_note`, with an ICU plural:

  ```yaml
  ignored_note: "{count, plural, one {# delaftale er ignoreret i tallene} other {# delaftaler er
    ignoreret i tallene}}: {references}"
  ```

- Test: `tests/Functional/PunctualityPageTest.php`. No note with no ignored sub-agreement. The
  note with one and with two ignored sub-agreements. Use generic references, for example
  "BOOKED" and "TEST".
- Documentation:
  - `README.md`: the punctuality page shows the ignored sub-agreements.
  - `docs/architecture.md`: the ignored sub-agreements in the description of the punctuality
    page, and the extra query.
  - `CHANGELOG.md`: add an entry under "Unreleased".

Add the extra query to the number of queries in "Cost" in
[docs/architecture.md](../architecture.md#punctuality).

## Open points

- Must the note link to the sub-agreement pages for a user with `ROLE_MANAGE_SUPPLIERS`? The
  issue says no link, so that the page is the same for all readers.
- Does the transport office want a log of who changed the flag, and when? That is a separate
  feature.
