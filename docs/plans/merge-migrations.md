# Merge the migrations into one before the first release

**Issue:** [#97](https://github.com/itk-dev/koerselsoverblik/issues/97)

See the [rules for plans](README.md#rules).

## Implementation

1. Wait until the last schema change before the first release is in `develop`. Make this the
   last pull request before the `release/<VERSION>` branch.
2. Delete `migrations/Version*.php`.
3. Run `task console -- doctrine:migrations:diff --from-empty-schema`. The command compares the
   mapping with an empty schema, so the database does not need to be empty.
4. Read the generated SQL. Compare it with the SQL of the old migrations: the tables, the
   indexes, the foreign keys and their `ON DELETE`, for example `ON DELETE SET NULL` on
   `import_batch.uploaded_by_id`. The old migrations have no data SQL, so the diff loses nothing.
5. Set `getDescription()` to "Create the schema of the first release".
6. Drop the developer database and run `task setup`. A database with the old rows in
   `doctrine_migration_versions` does not run the new migration.
7. Create the test database again with `task test:db:setup`. Run the full test suite and
   `task prepare-code`. The mutation tasks create the thread databases again themselves.
8. Run `task console -- doctrine:schema:validate` and `task console -- doctrine:migrations:diff`.
9. Add the rule to "Rules for the code" in `AGENTS.md`, next to the rule about migrations.

## Open points

- Staging gets a deploy only from a `release/*` branch, and no release branch exists yet. If a
  staging database exists, drop it before the first deploy of the release branch.
