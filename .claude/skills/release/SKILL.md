---
name: release
description: "Use when building, verifying or shipping the plugin ZIP — scripts/release, .distignore, what the shipped composer.json/package.json may contain, Plugin Check, scripts/e2e-dist, build-release.yml — or when adding a dependency (Composer or pnpm) that could leak into the ZIP. Also covers the Product Bundles image CI pulls."
---

# Releasing

```bash
scripts/release                   # ZIP from the current HEAD
scripts/release --branch=main     # ZIP from any local git ref
```

Creates `release/alondra-<version>-<commit>.zip`.

## What the script does

- Exports the ref with `git archive` into `release/build/alondra/`, installs production Composer deps with an authoritative classmap, builds the assets, then zips with `wp dist-archive` (WP-CLI and `dist-archive-command` 3.1.0 in a throwaway `wordpress:cli-2.12` container).
- `git archive` ships only tracked files: uncommitted work, a new `.distignore` entry included, is excluded. Commit before building.
- What stays out of the ZIP is listed once, in `.distignore`. A pattern with a leading `/` matches only at the root; root-only entries carry it so they never drop a same-named folder or file inside `vendor/` (the Freemius SDK's `languages/` and `README.md`). Everything else ships, including `vendor/`, `composer.json`/`.lock`, `package.json`/`pnpm-lock.yaml`, `assets/js/src`, `assets/css/scss` and the webpack/babel/postcss configs: the GPL requires the sources of the compiled JS/CSS, and Plugin Check warns when `vendor/autoload.php` ships without `composer.json`.
- `build-release.yml` runs the same script with `PNPM=pnpm` (pnpm from `pnpm/action-setup`, Node 24, cache keyed on `pnpm-lock.yaml`).

## Dependencies and the shipped manifests

- **`package.json` ships**, since it is how the compiled assets are reproduced, so it must not advertise dependencies the ZIP cannot use. The script installs with `pnpm install --frozen-lockfile`, builds with `pnpm run build`, then runs `pnpm remove @playwright/test @types/node`, which also rewrites `pnpm-lock.yaml`. The distributed manifest is derived, never a second copy, and that happens inside `release/build/`, never in the working tree. A new build dependency needs nothing; a dependency only the browser suite uses goes on that `pnpm remove` list.
- **Nothing in the ZIP may reference anything outside it.** The dev tools (PHPUnit, PHPCS, PHPStan and extensions) sit in `require-dev` of the root `composer.json`; the script installs with `--no-dev`, so the ZIP's `vendor/` holds only the Freemius SDK and the autoloader. The shipped `composer.json` still lists the dev tools, inert unless someone installs without `--no-dev`. The autoloader stays authoritative through `config.classmap-authoritative`, not a `post-install-cmd`, so the shipped manifest runs no script that could reach outside the ZIP.

## The Freemius SDK from `lib/`

`freemius/wordpress-sdk` resolves from `lib/freemius-wordpress-sdk/`, an exact copy of the SDK release, through a path repository listed after Packagist. Both entries are `canonical: false`, so Composer weighs every version: a higher one on Packagist wins, and on an equal version Packagist wins because it comes first. The path repository's `versions` option gives the copy its version (the SDK's `composer.json` has none), and `symlink: false` mirrors it into `vendor/`, so the ZIP ships a real copy; `lib/` itself is in `.distignore` and excluded from PHPCS and the syntax check. While it is in use, the shipped `composer.json` and `composer.lock` name `lib/` as the SDK's source: the ZIP still works and `composer install` in it does nothing because `vendor/` ships; only rebuilding `vendor/` from scratch needs `lib/`, and that ends when the SDK moves back to Packagist.

Once Packagist has the version in `lib/` or a higher one, run `scripts/composer update freemius/wordpress-sdk` so the lock points at Packagist, then delete `lib/`, the path repository, the `packagist.org: false` entry with the explicit Packagist one, and the `lib` exclusions. When updating the SDK in `lib/` before that, change `versions` with it.

## Verify like a reviewer

Unzip somewhere clean, run `composer install --no-dev`, and run Plugin Check on the result. Plugin Check is not in the image: install it once per container (`wp plugin install plugin-check --activate`), copy the unzipped build into `wp-content/plugins/`, and run `wp plugin check <slug>`. It reports `outdated_tested_upto_header` as an **error** whenever `Tested up to` is below the current WordPress release (see `wp-version-upgrade`).

**Locally, end to end:** `scripts/e2e-dist [--zip=release/<file>.zip] [--check-only] [playwright args]`, on a stack `scripts/setup` already provisioned. It builds the ZIP unless given one, recreates `web-alondra` with `compose.dist.yml` (which drops the source bind-mount and mounts `./release` read-only at `/tmp/release`), runs `wp plugin install /tmp/release/<zip> --force --activate`, fails on any Plugin Check error, then runs Playwright. `docker compose up -d --force-recreate web-alondra` puts the source tree back.

## CI

`build-release.yml`: `build` → `e2e-dist` → `deploy`, on a created GitHub release or by dispatch. `build` checks the tag against `alondra.php`'s `Version` and uploads the ZIP as an artifact; `e2e-dist` downloads **that** artifact and runs `scripts/e2e-dist` per leg; `deploy` runs only for a release tag, attaches the same artifact to the release and publishes it to WordPress.org through `scripts/publish_to_wordpress.sh`. It never rebuilds. Without the `WPORG_SVN_USERNAME` / `WPORG_SVN_PASSWORD` secrets the publish step logs a notice and does nothing. Listing assets (`.wordpress-org/`) go out separately through `scripts/publish_assets_to_wordpress.sh`.

Before tagging: `Version` in `alondra.php`, `Stable tag` and the changelog in `readme.txt` (see `wp-readme`), and `scripts/make-pot` (the POT carries the version, see `i18n`).

## The Product Bundles image

The WooCommerce Product Bundles zip is not redistributable, so it reaches CI as an image, not through git. `scripts/publish-product-bundles` pushes it as a `FROM scratch` image (`docker/Dockerfile.product-bundles`) to `ghcr.io/$CONTAINER_REGISTRY_USER/woocommerce-product-bundles:<version>`, the version read from the zip's name in `docker/plugins/`. Each provisioning workflow logs into GHCR as `github.actor` and extracts the zip into `docker/plugins/` with `docker create` + `docker cp`. The version lives in one place, `PB_VERSION` in `.env.sample`; `scripts/setup` reads it (falling back to the sample when an older `.env` lacks it), and so does every workflow.

**Run `scripts/publish-product-bundles` once, and again whenever `PB_VERSION` changes**, or every provisioning job fails pulling a missing tag. The package must also grant this repository read access in its GHCR settings, or `GITHUB_TOKEN` cannot pull it. Locally the zip goes in `docker/plugins/` (untracked; override with `PB_ZIP_PATH`).
