---
name: run-tests
description: "Use after changing code, or when asked to run the tests or check a change, to decide which suites and gates to run and how. Covers PHPUnit vs Playwright by kind of change, the current commands, the QA gates (phpcs, phpstan, php-syntax-check, i18n), and what needs the Docker stack."
---

# Which tests to run

Everything runs inside Docker except Playwright's runner; the stack must be up (`scripts/setup`) for PHPUnit, PHPStan and E2E.

## By kind of change

| Changed | Run |
|---|---|
| PHP in `src/`, `alondra.php`, `uninstall.php` | PHPCS, PHPStan, syntax check, PHPUnit |
| PHP in `dev/` (helper, demo theme) | PHPCS, syntax check; the E2E spec that uses the helper task |
| `tests/` | PHPCS, PHPUnit |
| A translatable string or the version | `scripts/make-pot`, then `scripts/make-pot --check` and `scripts/check-text-domain` |
| JS/SCSS in `assets/` | `scripts/pnpm run build`, then the E2E spec covering that screen |
| Admin editor, settings page, storefront markup, cart pricing | PHPUnit first, then the matching E2E spec (`001` editor, `002` settings, `005` product/cart) |
| Activation, uninstall, plugin row | `003` |
| `docker/`, provisioning, seeder | rebuild the image (`docker/build-image.sh`), teardown, setup, then E2E (see `demo-store`) |
| Packaging, `.distignore`, dependencies | `scripts/release` and `scripts/e2e-dist --check-only` (see `release`) |

Prefer a PHPUnit test whenever it can prove the change: E2E is slow (minutes per spec), needs the seeded store, and leaves it without its demo pricing groups. A bug fix starts with a test that fails before the fix.

## Commands

```bash
scripts/phpcs [files]            # PHPCS over the repo root, dev/helper and dev/demo-theme
scripts/phpcbf [files]           # Auto-fix
scripts/phpstan                  # Level 9 on alondra.php, uninstall.php, src/
scripts/php-syntax-check [--php=8.3]   # php -l, default PHP 7.4 (hooks check 7.4–8.3)

docker compose exec -T web-alondra /usr/local/bin/setup-tests.sh   # once per container (and after a recreate)
docker compose exec -T web-alondra /usr/local/bin/run-tests.sh     # full PHPUnit suite
docker compose exec -T -w /var/www/html/wp-content/plugins/alondra web-alondra \
    vendor/bin/phpunit --configuration phpunit.xml.dist --filter <name>   # one test (run-tests.sh ignores args)

scripts/playwright 005           # one spec; no argument runs all (needs host Node + scripts/pnpm install)
```

Add `-e ALONDRA_LOAD_PB=1` to the PHPUnit `exec` to load WooCommerce Product Bundles; CI's Product Bundles leg fails if the testdox output lists any skipped (`↩`) case other than the one asserting Product Bundles' absence, since testdox drops the skip reason and a Product Bundles that failed to load would otherwise pass as skips. Details and traps: `phpunit-traps`, `e2e-playwright`.

## Gates

- PHPStan covers only `alondra.php`, `uninstall.php` and `src/` (with `vendor/freemius/` scanned); PHPCS is the only gate on `tests/` and `dev/`.
- `.phpcs.xml.dist`: PHPCompatibility 7.4+, WordPress and WooCommerce-Core standards; under `src/` WooCommerce's file rules apply (`Squiz.Classes.ClassFileName`), so a PascalCase file must declare the class it is named after; `[]` arrays only; `deactivate_plugins`/`activate_plugin` forbidden everywhere.
- `scripts/phpcs` and `scripts/phpcbf` share `scripts/lib/phpcs-run.sh`, which rewrites host paths to container paths and passes `--basepath=/var/www/html` so PHPCBF can write fixes back.
- CI's `php-qa.yml` also runs `composer dump-autoload --strict-psr`, which fails when a class's path does not match its FQCN.
- The git hooks run the same gates: pre-commit PHPCBF → PHPCS → syntax 7.4–8.3 → PHPStan (when a staged PHP file is outside `dev/`); pre-push the full PHPUnit suite.

## When something fails

Classify it before fixing: a regression (the change broke behaviour), an intended change the test must follow, a flaky or environment failure (unrelated to the diff), or missing setup (e.g. `setup-tests.sh` not run since the container was recreated — run it and retry once). Use `git diff --name-only` against the base to tie the failure to the change.

Report a check that could not be run (stack down, missing Product Bundles zip) as not run, never as passed.
