# Serialise the changes of the driving data

**Issue:** not created

See the [rules for plans](README.md#rules).

## Issue text

```markdown
# Serialise the changes of the driving data

## Background

`StopRowRangeDeleter` reads the ids of the copies of a range from the snapshot of its
transaction. The deletes that follow see the latest committed rows. When two imports of the same
dates overlap, import A can delete the new rows of import B, but delete only the old copies.
Then the copies of B stay with no row. The copies hold personal data (ADR 004).

#126 added the command `app:driving-data:delete-orphan-copies`. A cron job runs it each night,
so such copies stay at most until the next night. This issue closes the race at the source: two
changes of the driving data never overlap.

## Tasks

- Add the Symfony Lock component with its Flex recipe.
- Choose the store of the lock: the database or Redis. All containers and hosts must share it.
- Add `App\Import\DrivingDataLock`. It runs a change of the driving data under the lock
  `driving-data`.
- Let `DrivingDataImporter::import()` take the lock before the transaction starts. The preview
  changes no data, so it takes no lock.
- When the lock is taken, stop at once with a clear message. Do not wait. The upload page shows
  the message in Danish, and the console command shows it in English.
- Let the clear of a date (#81) take the same lock, if #81 is merged first.
- Update "Pipeline" in `docs/architecture.md`, the deployment section of the README, and
  `CHANGELOG.md`.

**Out of scope:** the import in the background with Messenger.

## Acceptance criteria

- Two imports never overlap. The second import stops with the message, and changes no data.
- The lock is released after an import, also after a failed import.
- An import that stops with no release (for example a killed process) does not block the next
  imports for more than the TTL of the lock.
- 100 % line coverage.
```

## Implementation

A first version was on the branch of #126 and was rolled back. Its findings:

- `task composer -- require symfony/lock` adds `config/packages/lock.yaml` with
  `framework.lock: '%env(LOCK_DSN)%'`, and `LOCK_DSN=flock` in `.env`.
- `flock` and `semaphore` do not work here. `task console` runs in a new container
  (`docker compose run`), so the web container and the console do not share `/tmp`.

### Store options

| | A: the database | B: Redis |
| --- | --- | --- |
| New service | No. MariaDB is there | Yes. A Redis service in dev, CI and on the server |
| Schema | A migration for `lock_keys` | No table |
| Load on MariaDB | 4 small queries for each import, and polling while an import waits | None |
| Expiry | `key_expiration` in the table, and a delete of the expired rows in 1 % of the takes | Redis deletes the key at the end of the TTL |
| PHP | DBAL is there | The `redis` extension is in the image already |
| Restart | The locks stay | The locks are lost when Redis restarts with no persistence |

**Recommendation: A, the database, also when Redis is available.**

- The import stops at once when the lock is taken, so nothing polls. Then the load on MariaDB is
  4 small queries for each import, on a table with one row.
- The lock fails only when the import itself cannot run. With Redis, the import also fails when
  Redis is down and MariaDB is up.
- The lock stays when the database restarts. A lock in Redis with no persistence is lost, and two
  imports can then overlap.
- CI and the development setup need no new service. The cost is one migration.

Choose B, Redis, only in one of these cases:

- The site uses Redis also for other work, for example the cache or the sessions.
- The operations team already runs and monitors Redis for the site.
- The imports must wait for each other, and must not stop. Then each waiting import polls each
  100 ms. This costs little in Redis, but it gives about 30 queries each second on MariaDB.

#### A: the database

- `framework.lock:
  'doctrine.dbal.default_connection'` gives a `DoctrineDbalStore` on the default connection. A
  resource with no `:` is a service id (see `registerLockConfiguration()` in
  `FrameworkExtension`). A value from an environment variable is always a DSN, so this value
  cannot come from `LOCK_DSN`.
- The store needs the table `lock_keys`. `LockStoreSchemaListener` of the Doctrine bridge adds it
  to the schema, so `doctrine:migrations:diff` makes the migration. The store makes the table
  itself only when it is missing.

#### B: Redis

- Set `LOCK_DSN=redis://redis:6379` in `.env`, and keep `framework.lock: '%env(LOCK_DSN)%'` from
  the recipe. `StoreFactory` gives a `RedisStore` for a DSN that starts with `redis:`.
- Add a `redis` service to `docker-compose.override.yml` and `docker-compose.server.override.yml`.
  Do not change `docker-compose.yml`, because it is a template file.
- CI must also start the service, or the tests must use `InMemoryStore` in the `test`
  environment (`when@test` in `lock.yaml`).
- A take or a release is one command in the memory of Redis. If `RedisStore` does not implement
  `BlockingStoreInterface`, a waiting lock also polls each 100 ms, but each poll is cheap. Check
  this in `vendor/symfony/lock/Store/RedisStore.php`. Do not guess.
- A Redis service can later also hold the cache or the sessions (`config/packages/cache.yaml`
  has a Redis example in a comment).

### For both stores

- Take the lock before `wrapInTransaction()`. Then the lock row is committed, and the snapshot of
  the import comes after the commit of the other import.
- Use a TTL that is longer than the longest import, for example 600 s.

### The load on the database (option A)

- With no other import: 4 small queries for each import. An `INSERT` takes the lock, an `UPDATE`
  sets the TTL, and a `DELETE` and a `SELECT` release it. 1 % of the takes also delete the expired
  locks. The table has one row at most, and each query uses the primary key.
- `DoctrineDbalStore` cannot wait on MariaDB. `Lock::acquire(true)` tries again each 100 ms, with
  3 queries for each try. This is about 30 queries each second while the other import runs.
- A waiting upload is a web request with the PHP limit of 30 seconds. It can fail with an error
  500 before the other import ends. Thus the issue asks for `acquire(false)` and a message.

### Tests

- A unit test of `DrivingDataLock` with `LockFactory(new InMemoryStore())`: the result of the
  work, the lock during the work, the release after the work and after an exception, and the
  exception when the lock is taken.
- An integration test of the importer with a spy store (`PersistingStoreInterface`) in the test.
  The import saves and deletes the key `driving-data`. The preview does not.
- A functional test of the upload page: the message when the lock is taken.

## Open points

- **The store:** the plan recommends A. Ask the operations team if the site uses Redis for other
  work, or will soon. If it does, choose B.
- **Keep the sweep?** When the lock exists, decide whether the nightly
  `app:driving-data:delete-orphan-copies` stays as a safety net, or is removed.
- **Other changes of the data:** check whether the change of a sub-agreement (the daily stats,
  [daily-stats.md](daily-stats.md)) must also take the lock.
