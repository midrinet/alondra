---
name: i18n
description: "Use when adding or changing a translatable string (PHP or JS), changing the plugin version, touching languages/alondra.pot, or wiring translations into a script. Covers language packs only, the POT and its --check, check-text-domain, PHP-localized JS strings, and the details of scripts/lib/i18n.sh."
---

# Translations

The plugin is translated through WordPress.org language packs only. It never calls `load_plugin_textdomain()`, ships no `languages/` in the ZIP (`.distignore`) and so carries no `Domain Path` header (Plugin Check flags one pointing at a missing folder). The repo commits only `languages/alondra.pot`. Every string is on the `alondra` text domain.

JS strings reach the scripts as PHP-localized data (`wp_localize_script` in `AssetController` and `TieredPricingController`), so no script uses `wp.i18n` and there is no `wp_set_script_translations()`. A script that starts using `wp.i18n` needs the `wp-i18n` dependency and `wp_set_script_translations( <handle>, 'alondra' )` with no path, since language packs provide the files.

## Gates

- `scripts/make-pot [--check]` regenerates the POT from `alondra.php`, `uninstall.php`, `src/` and `assets/js/src/`. `--check` fails when the committed POT is stale, ignoring `POT-Creation-Date` and the `#:` source references, so a string that only moved does not fail it. Rerun it after changing a string or the version (`Project-Id-Version` carries it).
- `scripts/check-text-domain` fails on a gettext call in those sources whose domain is not `alondra`, or that has none, PHP and JS alike: it extracts once with `--domain=alondra` and once with `--ignore-domain`, and reports the difference.
- PHPCS's `WordPress.WP.I18n` also checks PHP domains.

## `scripts/lib/i18n.sh`

Both scripts source it; an extension's own scripts can reuse it with their own domain and sources.

- It runs WP-CLI's bundled i18n command in the `wordpress:cli-2.12` image `scripts/release` already uses, so nothing is added to `require-dev`.
- Sources are an explicit `--include`, because make-pot matches `--include` on any path segment (a `src/` inside `vendor/` would count) and an `--exclude` glob such as `assets/js/*.js` also matches `assets/js/src/`.
- The plugin is mounted at `/plugin-source`, not `/alondra`: make-pot strips the directory's path out of every file's, so `/alondra/alondra.php` would read as a hidden `.php` and be skipped.

## Writing strings

Write for a shop owner; no file or function names in user-facing text. `readme.txt` has its own register (see `wp-readme`).
