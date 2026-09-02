# Database Migrations

Per-task PHP migrations applied to the live MongoDB. Each file in this
directory represents exactly one migration, identified by its JIRA reference
(or a synthetic code when no ticket is attached). Files are discovered and
executed by `DbmigrateModel`, which `DbinitAction` invokes after schema
creation; the same model is also runnable on its own via `--dbmigrate`.

## When migrations run

```
php public/index.php --env <env> --dbinit
   └── DbinitAction
         ├── DbinitModel::execute()
         │     ├── init config collection (capped) + seed base config
         │     ├── create missing collections + indexes
         │     ├── seed mongo/base/taxes.export   (if taxes was missing)
         │     ├── seed mongo/first_users.json    (if users was missing)
         │     └── ShardingModel::execute()       (no-op when not on mongos)
         └── DbmigrateModel::execute()
               ├── load latest config record
               ├── glob *.php in this directory (sorted)
               ├── for each: skip if task code is in past_migration_tasks
               ├── otherwise run it and record the task code
               └── insert mutated config as a new revision
```

You can also run only the data step directly:

```
php public/index.php --env <env> --dbmigrate
```

`CreatetenantAction` calls the same models when provisioning a new tenant
database, so migrations in this directory also apply to every newly
created tenant on its first init.

## Filename convention

```
YYYYMMDD_NNN_<JIRA_REF>.php
```

- `YYYYMMDD` - the date the migration was authored. Lexicographic sort of
  filenames matches chronological order, so this dictates execution order.
- `NNN` - three-digit sequence number within the day. Use it to control
  ordering when two migrations share a date and one depends on the other.
- `<JIRA_REF>` - the JIRA ticket the migration belongs to. When no ticket
  exists, use a synthetic code that still ends in `-<digits>` (for example
  `BRDB-COUNTERS-1`); otherwise the runner will reject it.

Example: `20260514_006_BRCD-1443.php`.

## File contents

Every migration file `return`s a single anonymous class extending
`Billrun_Migration_Base`:

```php
<?php

return new class extends Billrun_Migration_Base {

    public function getTaskCode() {
        return 'BRCD-0000'; // JIRA reference (must end in -<digits>)
    }

    public function run() {
        // mutate $this->lastConfig and/or call collection ops via $this->db
    }

};
```

The loader does `$migration = require $file;` - the file's `return` value is
the migration instance. The loader then calls `setContext()` to inject
`$this->db`, `$this->lastConfig` (by reference), and `$this->controller`
before invoking `run()`.

## Helpers available on `$this`

| Member | Purpose |
|---|---|
| `$this->db` | `Billrun_Db` instance. Use `$this->db->ratesCollection()->...` etc. |
| `$this->lastConfig` | Current config revision as an array. Mutate freely - it is saved as a new revision after all migrations run. |
| `$this->log($msg)` | Print a status line to CLI output. |
| `$this->addFieldToConfig(&$conf, $entity, $fieldDef)` | Idempotent append-by-`field_name` into `$conf[$entity]['fields']`. |
| `$this->removeFieldFromConfig(&$conf, $entity, $names)` | Remove fields by `field_name` from `$conf[$entity]['fields']`. |

## Sharding

`DbinitModel::execute()` invokes `ShardingModel::execute()` at the end of
its run, which:

- calls `enableSharding` on the active database
- shards the default collections (`lines`, `archive`, `rates`, `billrun`,
  `balances`, `audit`, `queue`, plus `bills` on MongoDB >= 6 and
  `jobs_messages` on >= 8) with the keys ported from `mongo/sharding.js`
- self-gates on `$adminDb->isCluster()` - no-op on standalone or
  replica-set deployments, so nothing happens unless you're actually on a
  `mongos` router

The admin connection used for sharding commands comes from
`Billrun_Factory::admindb()` (driven by the `admindb` config block or
`BR_ADMDB_*` env vars).

Migrations can shard a new collection or re-shard an existing one by
calling `ShardingModel` directly from `run()`:

```php
public function run() {
    $dbName = $this->db->getName();
    (new ShardingModel())->shardOne($dbName, 'new_coll', ['shard_key' => 1], $this->controller);
    // or to re-shard with a new key (MongoDB >= 5):
    (new ShardingModel())->reshardOne($dbName, 'existing_coll', ['new_key' => 1], $this->controller);
}
```

Both `shardOne()` and `reshardOne()` also self-gate on `isCluster()`, so
they're safe to call from any migration regardless of deployment topology.

Sharding runs *before* the migration loop, so the post-init pass shards
only the default set. Migrations that introduce new sharded collections
are responsible for calling `shardOne()` themselves.

## Task code rules

- Must end with `-<digits>` (regex `.*-\d+$`); the runner rejects anything else.
- Must be unique across all migrations.
- Compared case-insensitively (codes are upper-cased before lookup).

## Idempotency

The loader wraps every `run()` in a `runOnce(taskCode, ...)` guard. On
success the task code is appended to `lastConfig.past_migration_tasks`,
which is persisted with the new config revision. Subsequent invocations
skip any migration whose code is already there, so `--dbmigrate` is safe to
re-run on every container boot.

If `run()` throws, the task code is **not** recorded - the migration will
be retried on the next invocation. Migrations that mutate documents
piecewise should therefore be resumable, or wrap their work in a way that
either commits everything or nothing.

## Authoring a new migration

1. Decide a JIRA reference (or a synthetic `-<digits>` code).
2. Pick the next sequence number for today's date: `ls 20260514_*` shows
   what's used.
3. Create `YYYYMMDD_NNN_<JIRA_REF>.php` in this directory, following the
   skeleton in **File contents** above.
4. Run `php public/index.php --env <env> --dbmigrate` against a test DB
   and verify it does what you expect.
5. Run it a second time and verify it is a no-op - i.e. the task code is
   now recorded and the migration is skipped.

## Samples

The [`samples/`](samples/) subdirectory holds migrations ported from the
legacy `mongo/migration/script.js`, one per update pattern in that script
(add a config field, push to a config array, run an `updateMany`, drop and
recreate an index, etc.). The loader globs `*.php` non-recursively, so
nothing under `samples/` is executed - treat the directory as a cookbook of
working examples to copy from when authoring a new migration.
