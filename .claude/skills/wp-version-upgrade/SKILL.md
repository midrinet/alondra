---
name: wp-version-upgrade
description: "Use when moving the dev stack or CI to a new WordPress release, raising the plugin's `Tested up to`, or checking which WordPress core the site and the unit suite actually run. Covers the WP_VERSION pin and its image-tag constraint, verifying both suites' cores, and upgrading a site in place when no Docker tag exists yet."
---

# Moving to a new WordPress release

## The pin

`WP_VERSION` names a published `wordpress:<version>-php<php>` tag, and `scripts/setup` fails on a missing base image, so it cannot point at a release the Docker library has not built yet (the library publishes a `beta-<version>` channel before the stable tag). `.env.sample` pins the stable release; CI copies `.env.sample` verbatim, so the pin is what CI runs. The unit-test matrix in `unit-tests.yml` names its own WordPress and WooCommerce versions per leg (the declared minimum and the newest) and moves with it.

## `Tested up to` names what was exercised

Check what the environment actually runs rather than trusting the pin: a container created before the pin changed keeps its own core until it is recreated, and a `beta-` image reports an RC rather than the release. The two suites can sit on different cores: `install-wp-tests.sh` downloads its own copy, so the unit suite runs whatever version `setup-tests.sh` was given while Playwright drives the image's core.

```bash
docker compose exec --user www-data web-alondra wp core version                        # what the site runs
docker compose exec -T web-alondra grep -m1 'wp_version =' /tmp/wordpress/wp-includes/version.php   # what PHPUnit runs
```

Plugin Check reports `outdated_tested_upto_header` as an **error** whenever `Tested up to` is below the current WordPress release, so the header has to be raised, and actually exercised, before every submission. Keep `readme.txt` and `alondra.php` in step (see the `wp-readme` skill).

## Upgrading in place when no tag exists

Instead of editing `.env` (replace `7.1` with the target):

```bash
docker compose exec --user www-data web-alondra wp core update --version=7.1
docker compose exec --user www-data web-alondra wp core update-db
docker compose exec -T web-alondra rm -rf /tmp/wordpress /tmp/wordpress-tests-lib
printf 'y\n' | docker compose exec -T web-alondra /usr/local/bin/setup-tests.sh 7.1
```

The upgrade lives in the `alondra_wordpress` volume: it survives restarts but is overwritten the moment the container is recreated from a different image tag. The cache removal matters: `install-wp-tests.sh` reuses an existing `/tmp/wordpress` and would otherwise leave the unit suite on the old core.

## The floor matters as much as the ceiling

The container tracks the newest tested release, so behaviour verified there says nothing about the declared minimum (`Requires at least` and `WC requires at least` in `alondra.php`, plus `ActivationController`'s runtime check). When a change depends on core behaviour, diff the relevant core function across the supported range, or run the oldest matrix leg.
