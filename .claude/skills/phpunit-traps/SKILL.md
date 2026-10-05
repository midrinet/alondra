---
name: phpunit-traps
description: "Use when writing or debugging PHPUnit tests under tests/ — especially tests that need real database tables, touch the repository cache, assert that a cache was hit, or run a single test. Covers WP_UnitTestCase's temporary tables, DDL committing the test transaction, the cache test setup, query-count deltas, and running one test with --filter."
---

# PHPUnit traps

## Running one test

`docker/run-tests.sh` passes no arguments through (it has no `"$@"`), so `run-tests.sh --filter …` silently runs the whole suite. Call PHPUnit directly:

```bash
docker compose exec -T web-alondra /usr/local/bin/setup-tests.sh     # once per container
docker compose exec -T -w /var/www/html/wp-content/plugins/alondra web-alondra \
    vendor/bin/phpunit --configuration phpunit.xml.dist --filter test_get_returns_singleton
```

Add `-e ALONDRA_LOAD_PB=1` to load the container's WooCommerce Product Bundles in the bootstrap (`tests/load-wc.php`); without it the bundle-dependent cases skip.

## Temporary tables

`WP_UnitTestCase` rewrites `CREATE TABLE` into `CREATE TEMPORARY TABLE`, which `SHOW TABLES` does not list. A test that needs a real table (e.g. `test-uninstall.php`) must first `remove_filter( 'query', [ $this, '_create_temporary_tables' ] )` and drop the table itself in `tear_down()`.

## DDL commits the test transaction

DDL implicitly commits the framework's per-test transaction. The repository cache tests (`test-tiered-pricing-repo.php`) therefore create their tables once in `set_up_before_class()`, not per test; otherwise fixture rows and the `alondra_cache_generation` option leak into the next test. Dropping them after `parent::tear_down()` does not work either: `_restore_hooks()` reinstates the temporary-table filters first and turns a late `DROP TABLE` into `DROP TEMPORARY TABLE`.

## Proving a cache was consulted

Query-count deltas (`$wpdb->num_queries` before and after) are the only assertions that prove a cache was actually hit rather than the right value merely returned. Assert the property that would fail before the fix, not one that only observes the new output. See the `cache-design` skill for what the cache guarantees.

## Foreign keys and portable DDL

The child tables declare no foreign keys. An existing install has them dropped by `DropForeignKeysMigration` (`MigrationRunner::DROP_FOREIGN_KEYS`), which runs from the migration cursor like any schema change and is retried on the runner's lock if it fails. It reads `SHOW CREATE TABLE` once per child table, adds `KEY tiered_pricing_id` if no `KEY` line names it, then drops every constraint that DDL names; the index goes in before the constraint comes out, so a request dying between the two cannot leave the join column unindexed.

- `SHOW CREATE TABLE` plus `DROP FOREIGN KEY <name>` is the only pair portable across every MySQL and MariaDB version in play: `DROP FOREIGN KEY IF EXISTS` is MariaDB-only and `DROP CONSTRAINT` is too recent.
- Constraint names are read, never constructed: they are server-generated and differ by engine (MariaDB numbers them, MySQL uses `<table>_ibfk_1`).
- `information_schema` is avoided, because a drop-in database layer such as HyperDB routes those queries to the wrong server.

## Pinned contracts

Some tests exist to fail when a contract changes, and changing them must be deliberate:

- `tests/Migrations/test-migration-runner.php` pins the shipped migration id → class map.
- `tests/test-hook-surface.php` pins the `alondra_*` hooks fired (see `extension-points`).
- `tests/test-stored-names.php` pins option, preference and handle names to their literals.
- `tests/Views/test-view-parity.php` holds the captured markup the views must keep (compared on normalized whitespace).

## Container seam

`Container::set_instance()` is test-only; `tests/Support/trait-container-seam.php` wraps it. Services resolve collaborators at point of use, so a test swaps a collaborator by binding a mock in a fresh container.

## Competing groups

For matching or pricing changes, cover the case where several published groups match the same line and assert which one wins; a fixture where each test sees only its own group hides ordering bugs.

PHPStan does not analyse `tests/`; PHPCS is the only automated gate there.
