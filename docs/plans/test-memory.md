# Decrease the memory use of the test suite

**Issue:** [#131](https://github.com/itk-dev/koerselsoverblik/issues/131)

See the [rules for plans](README.md#rules).

## Implementation

<!-- Write the design and the steps here when the work starts. -->

## Open points

- Is the memory use on `develop` also near the limit, or does #75 add most of it? The first
  measurement answers this.
- #75 ([#130](https://github.com/itk-dev/koerselsoverblik/pull/130)) needs this issue: its full
  test run stops at the memory limit. Measure on the branch of #75 too.
