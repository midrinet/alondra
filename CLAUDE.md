# CLAUDE.md

Guidance for AI coding agents working in this repository.

## Model Selection

- **Opus** — Multi-file architectural analysis, complex refactors, auditing.
- **Sonnet** — Standard coding, debugging, reviews, implementation work.
- **Haiku** — Trivial tasks: translating, renaming, formatting, simple lookups, one-line fixes.

## Working Principles

1. **Think before coding.** State assumptions; if uncertain, ask. If several interpretations exist, present them rather than picking silently. If a simpler approach fits, say so.
2. **Simplicity first.** Minimum code that solves the problem: no unrequested features, no abstraction for single-use code, no handling of impossible cases. The codebase's bias: lazy container resolution over constructor wiring, native WP hooks over wrappers, an interface only when more than one class implements it.
3. **Surgical changes.** Touch only what the task needs, match the existing style, remove only what your own change orphaned; mention pre-existing dead code instead of deleting it.
4. **Goal-driven execution.** Turn a task into a verifiable goal: "fix the bug" means a PHPUnit test (or Playwright spec) that fails before the fix and passes after. For PHP, done means green `scripts/phpcs`, `scripts/phpstan` and the relevant suite, the same gates the git hooks enforce.
5. **Automate the invariant.** A fix that ends in "verify by hand" needs the assertion that would have failed before the fix. Name a genuinely untestable residue as such.
6. **Know why before deleting.** Trace the history of code (`git log -S`, the PR that added it) before proposing to remove it; "the history doesn't say" is not a reason.
7. **Test the competing case and the real scenario.** Matching and pricing tests cover several groups matching the same line and assert which wins; before calling a reported bug fixed, recheck that exact scenario on the running store.
8. **Verify against the declared floor.** The dev container runs the newest WordPress; behaviour that depends on core must also hold on `Requires at least` / `WC requires at least` from `alondra.php`.

## Project Overview

**Alondra** is a WordPress plugin for tiered pricing and quantity discounts in WooCommerce, published on WordPress.org. The repository root is the plugin. It carries the Freemius SDK as a free, directory-compliant parent product that add-ons (such as Alondra Plus) attach to; it ships no locked or switched-off paid code, only extension points.

- **PHP** 7.4+ (checked against 7.4–8.3), **WordPress** 6.8+, **WooCommerce** 10.4+
- **Namespace** `Midrinet\Alondra`, **text domain** `alondra`, REST namespace `alondra/v1`

## Repository Layout

- `alondra.php` — bootstrap; `uninstall.php` — deletes the options, and the tables and `alondra_migration_id` only when `uninstall_cleanup` is on (never another chain's cursor).
- `src/` — PSR-4 under `Midrinet\Alondra\`: `Infrastructure/` (`Config/PluginInfo`, `DI/Container`, `Controller/`, `Migration/`, `View/{Admin,Front,Preferences,Toolkit}/`, `Wp/`), `Application/` (`Service/`, `Dto/`), `Domain/` (`Datamapper/`, `Repository/`, `Entity/`).
- `assets/js/src/`, `assets/css/scss/` — sources (webpack); built files are gitignored.
- `tests/` — PHPUnit (`WP_UnitTestCase`, `tests/**/test-*.php`), `tests/stubs/` symbols PHPStan scans; `tests-e2e/` — Playwright specs and page objects.
- `dev/helper/` — `alondra-helper`, the dev/test plugin (webhooks, demo seeder `demo-store/seed.php`, Bruno requests); `dev/demo-theme/` — the storefront child theme.
- `lib/freemius-wordpress-sdk/` — the Freemius SDK while Packagist lacks that version, installed into `vendor/` through a path repository; left out of the ZIP and the gates (see `release`).
- `docker/` — image, entrypoint, test scripts, demo images and download; `docker/plugins/` takes the untracked Product Bundles zip.
- `scripts/` — developer wrappers. `bin/` stays free: `setup-tests.sh` scaffolds WP-CLI test files at the repo root.
- `.wordpress-org/` — listing assets (and the banner's editable `banner-source.svg`), published by `scripts/publish_assets_to_wordpress.sh`.

## Development Environment

```bash
cp .env.sample .env              # first time
scripts/setup --install          # start containers, install deps, provision, seed the demo store
scripts/setup                    # start again (reseeds idempotently)
scripts/teardown                 # remove containers and volumes
```

WordPress admin at `http://localhost:8080/wp-admin` (admin/admin). Ports bind to `127.0.0.1` only, and `docker/apache-deny-repo-files.conf` refuses every dotfile and dot-directory under the plugin mount plus the dev folders: the stack is admin/admin and the helper's webhooks are unauthenticated. Key `.env` vars: `PHP_VERSION`, `WP_VERSION`, `WC_VERSION`, `WP_HTTP_PORT`; store identity lives in `docker/Dockerfile` (see `demo-store`). `scripts/composer` pulls `ghcr.io/midrinet/composer:latest` (`docker/Dockerfile.composer` only rebuilds it).

## Build, Test, Quality

```bash
scripts/composer install         # Freemius SDK, autoloader, dev tools
scripts/pnpm install             # Node deps; pnpm pinned in package.json, run via npx in node:24-alpine
scripts/pnpm run build           # production JS + CSS (run dev for source maps)

scripts/phpcs | scripts/phpcbf   # WordPress + WooCommerce standards, PHPCompatibility 7.4+
scripts/phpstan                  # level 9
scripts/php-syntax-check [--php=X]
scripts/make-pot --check && scripts/check-text-domain

docker compose exec -T web-alondra /usr/local/bin/setup-tests.sh   # once per container
docker compose exec -T web-alondra /usr/local/bin/run-tests.sh     # PHPUnit (passes no args; see run-tests)
scripts/playwright [spec]        # E2E on the host's Node; slow, needs the stack
scripts/release                  # release ZIP
```

The wrappers run inside Docker except Playwright's runner. Remove a `node_modules` left by npm before the first `scripts/pnpm install`; the toolchain needs Node ^22.22.3 or ^24.15.0.

## Git Hooks and CI

`scripts/install-git-hooks` once after cloning. pre-commit (staged PHP): PHPCBF → PHPCS → syntax 7.4–8.3 → PHPStan when a file is outside `dev/`. pre-push: full PHPUnit. post-merge/post-checkout: reminds to reinstall deps when a lock file changed.

`.github/workflows/`, on pull requests into `main` unless noted: `unit-tests.yml` (WP 6.8/WC 10.4.4, WP 7.1/WC 11.0.1, a Product Bundles leg, and WP 7.1/WC 11.2.0), `e2e-tests.yml` (legs `001`, `002`, `003`, `005`, each on its own runner), `php-qa.yml` (on push: syntax, PHPCS, PHPStan, `composer dump-autoload --strict-psr`), `build-release.yml` (build → e2e-dist → deploy to WordPress.org, see `release`), `build-docker-image.yml` (republishes the dev image).

## Architecture

```
alondra.php → Container::build() → alondra_controllers → Controller::register()
  ├── Infrastructure/  — controllers (hooks, REST), config, DI, migrations, views, WP wrappers
  ├── Application/     — services and DTOs
  └── Domain/          — entities, repositories (wpdb), datamappers (row ↔ entity)
```

- **Bootstrap**: while Alondra 1.x (`alondra-pro/` in the site or network active plugins) is active, `alondra.php` stops right after the autoloader and the HPOS declaration (kept first so WooCommerce does not flag the dormant plugin) and only shows `LegacyMonolith`'s admin notice: no Freemius, no hooks fired, no activation hook. Otherwise Freemius init at include time, `alondra_loaded`, then on `plugins_loaded:20` the container is built, WooCommerce's version gated, and the `alondra_controllers` list registered. Full flow in `extension-points`.
- **Container**: `Container::instance()->get( X::class )` / `get_named( 'vendor/key', X::class )`, lazy and cached; unbound classes auto-resolve with no arguments; `alondra_di_definitions` rebinds any key. No constructor dependencies: collaborators are resolved at point of use.
- **No `Plugin` class**: each controller (extending `Controller`) registers its own hooks with native WP functions.
- **Plugin identity** comes from the header via `PluginInfo`; each option, handle and key is a constant on its owning class, pinned by `tests/test-stored-names.php`.
- **Migrations** run as chains, each with its own cursor, under one lock; shipped ids are never renumbered or reused.
- **Views, not templates**: one dumb View class per screen fragment, typed `render()` returning HTML, escaping kept; admin markup echoed through `TieredPricingController::kses_and_echo()`. Inline HTML ends in a column-0 `<?php` with a `ScopeIndent` ignore.
- **Preferences**: hardcoded defaults, stored values win only for `PreferencesService::CONFIGURABLE` keys; every getter treats stored values as untrusted. Colours are validated where they reach CSS.
- **Saves are PATCHes**: the settings save writes stored plus rendered keys only; the tiered pricing save keeps every stored key the payload omits. A save never clears what it does not render.
- **Matching**: ANY within a list, AND between populated sides (OR when a side is empty), first group created wins, fixed per-unit prices.
- **Repository cache**: generation token, negative caching, `isolate()` across the boundary, never `wp_cache_flush()` (see `cache-design`).
- **Security**: every state-changing admin action checks a nonce (bulk: `ListTable::verify_bulk_action_nonce()`; rows: `TieredPricingController::ROW_ACTION_NONCE`, which covers the action, not the item); read paths are gated by `manage_woocommerce` with a `phpcs:ignore … -- reason`.
- **One upsell banner** on the plugin's own screens, its link opening the Alondra Plus checkout; no per-field upsell.

## Coding Conventions

- PSR-4 in `src/`: PascalCase file named after its class; snake_case methods, functions, properties and variables (WordPress style). No `Imp_` prefix, no `final` classes, no interface with a single implementation.
- PHP 7.4 typed properties (no `@var` that repeats the type); `fn()` for single-expression closures; `[]` arrays only.
- Docblocks only for types PHP cannot express or for a why; comments short, only when they add a why.
- English for every repository artifact: code, comments, commits, PRs, docs.
- Every notable change gets its line under the version being prepared in `readme.txt` (see `wp-readme`).
- **No locked features** (WordPress.org Guideline 5): a field this plugin cannot vary is absent, not pinned or disabled; columns it does not use stay in storage and are carried through.
- **No switched-off paid logic**: add-on behaviour comes through extension points, never a dormant engine here. `tests/test-hook-surface.php` pins the hook surface.
- Never call `deactivate_plugins()` / `activate_plugin()` (enforced by PHPCS).
- Do not commit generated files (`vendor/`, `node_modules/`, built assets); `vendor/` ships in the ZIP.
- This file documents the repository only; tool-specific local setup belongs in untracked local files.

## Skills

Task-specific knowledge lives in `.claude/skills/`:

- `run-tests` — which suites and gates to run for a change, and the exact commands.
- `phpunit-traps` — temporary tables, DDL commits, cache test setup, query-count deltas, running one test.
- `e2e-playwright` — running specs, the catalog contract, name-collision rules, timing traps, plugin-management tests.
- `demo-store` — the seeder, re-seeding after E2E, baked image and re-provisioning, store identity, demo images.
- `extension-points` — the public `alondra_*` hooks and protected seams for add-ons.
- `cache-design` — the repository cache and admin HTTP caching.
- `i18n` — language packs, the POT, text-domain checks.
- `release` — building, verifying and shipping the ZIP; the Product Bundles CI image.
- `wp-version-upgrade` — moving to a new WordPress release and `Tested up to`.
- `wp-readme` — writing `readme.txt`.

## Debugging

```bash
docker compose exec web-alondra toggle-xdebug --mode=debug   # or --mode=profile / --mode=off (port 9003)
scripts/cp-sources                                           # copy WP/WC sources for IDE type resolution
docker compose logs -f web-alondra
```
