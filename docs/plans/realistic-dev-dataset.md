# Make the development dataset realistic

**Issue:** [#101](https://github.com/itk-dev/koerselsoverblik/issues/101)

See the [rules for plans](README.md#rules).

## Implementation

- **Profile:** `var/optiruns/profile.php` reads the exports in a local folder, and prints only
  counts and distributions. It is not committed. Run it again when more exports are downloaded.
- **Shape:** put invented shares in constants of `DevDatasetGenerator` as weighted lists, and
  select with `Randomizer`. Use values near the local profile, but never the real values: the
  repository is public. Do not read a profile file at run time, so that each developer gets the
  same data.
- **Citizens:** make the citizens of a route once for each seed. Then the same citizens ride on
  the same route each day, as in the real data.
- **Institutions:** invented names, for example "Skole 1" to "Skole 40" and "Center 1" to
  "Center 20". #87 needs them.
- **Import time:** 13 months are about 57 week files. The import of one week takes some seconds,
  so `task fixtures:import` takes some minutes. Run the import in `prod` with no debug, or the dev
  environment runs out of memory.

## Open points

- **Years after 2027/28.** The list must have a new school year each year, or the generator must
  use a rule (weeks 7 and 42, and the Easter dates) for a year that is not in the list.
- **`APP_DRIVING_DATA_MAX_DAYS=7`.** The command `app:driving-data:clear` (#81) and the page of
  #96 use the same limit. Decide whether they need a longer range.
- **Memory of the import.** The memory grows with the number of rows. A day needs less than
  48 MB, and a week needs 64 MB to 96 MB. See [import-memory.md](import-memory.md).
