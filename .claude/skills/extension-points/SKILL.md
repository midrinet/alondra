---
name: extension-points
description: "Use when adding, changing or relying on a public alondra_* hook or a protected seam an add-on extends — the DI definitions filter, the controllers filter, migration chains, the settings page seams and its PATCH save, storefront layout views, the tiered pricing save seams, the matching filters, alondra_tier_price, or the upsell banner. Also use before adding anything that looks like a paid feature to this plugin: it ships extension points, never switched-off logic."
---

# Extension points

This plugin is complete on its own and also the base an add-on (such as Alondra Plus) builds on. The add-on brings its own logic through the hooks and protected methods below; this plugin never carries switched-off logic for it. `tests/test-hook-surface.php` pins the exact set of `alondra_*` hooks fired, that `alondra_components` passes two arguments, and that `src/`, `assets/js/src/`, `alondra.php` and `uninstall.php` never mention `can_use_premium_code`, `is_free_plan`, `__premium_only` or `load_plugin_textdomain`. Changing the hook surface means changing that list on purpose, with a `@since` on the hook and a changelog line. Retiring a hook follows the deprecation policy in `README.md`: `apply_filters_deprecated()` / `do_action_deprecated()` naming the replacement, kept working for at least two minor releases, never deleted outright; adding a trailing argument is compatible, removing or reordering one is a deprecation.

## Bootstrap and timing

`alondra.php`, inside a closure:

1. Requires `vendor/autoload.php` (PSR-4, authoritative classmap) and declares HPOS compatibility on `before_woocommerce_init`, outside the closure and before the Alondra 1.x guard (`LegacyMonolith`), which stops the file there while 1.x is active.
2. Calls `fs_dynamic_init()` at file scope (Freemius requires it during the include). On that instance `is_pricing_page_visible` is filtered to false (the plugin's own pricing page would sell a plan that unlocks nothing; the hidden page still serves the add-on's checkout) and `pricing_url` to the settings page.
3. Fires **`alondra_loaded`** `( Freemius $fs )`: add-ons initialise their own Freemius instance here.
4. Binds `$fs` under the named key `alondra/freemius` through `alondra_di_definitions` at priority 1. An add-on uses its own named key, never `Freemius::class`.
5. `register_activation_hook()` builds the container on demand (`Container::build( __FILE__ )`) and calls `ActivationController::activate()`, because the activation request includes the file after `plugins_loaded`.
6. On `plugins_loaded` priority 20 the container is built, then `ActivationController::requirements_met()` gates everything: with an older WooCommerce than supported, no controller registers and only an admin notice shows.
7. Otherwise **`alondra_controllers`** `( string[] )` is read — default `[ ActivationController, AssetController, TieredPricingController, PreferencesController ]` — each entry is checked with `is_subclass_of( …, Controller::class )` (anything else is skipped with `_doing_it_wrong()`), and `register()` is called on each.

Both `alondra_di_definitions` and `alondra_controllers` are read at `plugins_loaded:20`, never at include time, because an add-on cannot rely on sorting before `alondra` in `active_plugins`. A listener registered any time before then applies; one registered after is ignored. The container is built there also because `alondra` sorts before `woocommerce`, so `WC_VERSION` is undefined while the main file runs.

## The container

`Infrastructure\DI\Container`. `Container::build( $plugin_file )` applies **`alondra_di_definitions`** `( array $definitions )` exactly once and returns the one instance; `Container::instance()` returns it (and throws before the build).

- `get( X::class )` and `get_named( 'vendor/key', X::class )` resolve lazily, cache, and check `instanceof` on both the build and cache-hit paths.
- A definition is a class-string (instantiated with no arguments) or a zero-argument callable. `build()` lists only classes that need wiring (`\wpdb`, `PluginInfo`, `TieredPricingRepo`); a class with no definition is instantiated with no arguments and cached like a bound one; only a class that does not exist throws. No reflection, no constructor injection.
- Named keys carry a `/`, which a class name cannot, so they never collide with a class-string.
- **Replacing a class**: bind your subclass under the parent's class-string. The plugin reads its collaborators through the container by those keys, so the subclass is what runs (services, views factory, list table adapter, controllers, `MigrationRunner`).

## Migration chains

`MigrationRunner::chains()` returns `MigrationChain( name, cursor_option, migrations )` objects, this plugin's `alondra` chain (cursor `alondra_migration_id`) first. An add-on appends its own chain by subclassing the runner and rebinding `MigrationRunner::class`. Each chain has its own cursor and ids (unique, ascending, never 0, never reused), advanced only after `execute()` returns. One `alondra_migration_lock` transient (5 minutes) covers the whole run; a throw keeps it (the retry throttle) and the first failure stops the run, so later chains wait for the earlier chain's schema. `is_applied( $id, $chain = 'alondra' )` answers per chain, `has_pending()` across all. On failure **`alondra_migration_failed`** `( $id, $message, $kind, $type, $chain )` fires; the admin notice stays generic. Stored columns this plugin does not compute with (relationships, priority, tier type, bundle pairs) stay in its schema and are round-tripped.

The runner reads and writes the cursors itself (`get_migration_id()` / `set_migration_id()`, memoised per request). The failure record and the `alondra-migrations` log line name the chain. The installed-version marker (`alondra_version`) belongs to `ActivationController::get_version()` / `set_version()`, not to the preferences. `tests/Migrations/test-migration-runner.php` pins the shipped id → class map, so renumbering, removing or reusing an id fails the suite.

The autoloader is an authoritative classmap: after switching to a commit that adds a class (a migration included), run `scripts/composer dump-autoload`, or it fails class-not-found and the migration stays behind the lock.

## Admin pages: `alondra_components`

**`alondra_components`** `( array $components, LoaderTemplate $loader )` collects the admin pages (`PrefPage` instances). `LoaderTemplate` renders nothing of this plugin's; it stays bound only as that second argument, so a listener loading its own templates by absolute path keeps working. `PrefPage` fires no action: its owner passes header, content, titlebar and footer callbacks (`set_header()` / `set_content()` / `set_titlebar_options()` / `set_footer()`, each called with the page id). `PrefPage`, `Toolkit` and `LoaderTemplate` live in `View/Toolkit/`; `PrefPage` is the shell both admin screens are built on, so do not delete it as settings-only machinery.

## Settings page seams

`PreferencesController` (*Settings* > *Alondra*, slug `alondra-settings`) registers `alondra_settings` → option `alondra` with a sanitize callback. Protected seams: `tabs()` (ordered tabs keyed by id, each `title` and escaped `content`; the form lives only in the Settings tab), `settings_form()`, `footer()` (an add-on prints its edition and version there), `sections()` (sections with fields, each `type`/`label`/`description`/`options`/`value`), `fields()`, `rendered_keys()`, `layout_positions()`, `sanitize( array $submitted ): array`, `sanitize_field()` → `sanitize_checkbox()` / `sanitize_select()`, `render_field()`.

- **The save is a PATCH.** `sanitize()` writes back the stored array plus the rendered keys only, so an unrendered or unknown key is never overwritten; an unchecked box is absent from the post and saves as `'0'`. The callback runs on every `update_option( 'alondra' )`, so it patches only when `$_POST['option_page']` is this group.
- **Mixed-field rule** (`sanitize_select()`): a select may display a fallback instead of what is stored (an unknown position shows as the default), so the value it displayed is recomputed from storage on the save request and the submitted value is written only when it differs and is one of the options. A stored value an extension understands survives a save that left the select alone; switching from it straight to the displayed default takes two saves.
- The page today: the titlebar shared with Tiered Pricing (`PreferencesPageTitlebarView`, logo and title), one Settings tab (`TabsView`, rendered from the content callback; `admin.js` switches tabs) holding `layout_pos` (two positions), `clickable_layout`, `highlight_pricing`, `overwrite_product_price`, `enable_cache` and `uninstall_cleanup` and nothing else — no control, placeholder or hidden input for a key it does not use — and the footer (`SettingsFooterView`, echoed without kses since it carries SVG: edition and version badge, Changelog and Get help links, the Ko-fi "Support Us" pill and the "Rate us on WordPress.org" pill, all new-tab; URLs are constants on the controller). This plugin always prints FREE and its own version.
- WordPress prints "Settings saved" itself on a Settings page, so the content never calls `settings_errors()`.

## Preferences

`PreferencesService::VALUES` holds the defaults; `defaults()` merges over them only the stored keys listed in `CONFIGURABLE` (`layout_pos`, `clickable_layout`, `highlight_pricing`, `overwrite_product_price`, `enable_cache`, `uninstall_cleanup`). Other stored keys are ignored for behaviour but stay in the option. Getters go through `get_string()` / `get_boolean()`; `get_layout_pos()` clamps to before-add-to-cart or `hide`; `get_pricing_layout()` returns the stored id unvalidated (`table` when absent). `CONFIGURABLE` is private, so a subclass overrides getters rather than extending the list. `VALUES` keeps the full key set on purpose: `array_merge()` would otherwise let an unknown key back in, and `get_hex_color()` falls back to `VALUES[ $key ]`. The six colours are not configurable (not in `CONFIGURABLE`, not documented in `readme.txt`) but are still read through `get_hex_color()` (`sanitize_hex_color()` with the default as fallback) because they reach an inline CSS declaration through `wp_add_inline_style()`: validate at that sink, whatever the input path, and keep it if a colour key ever becomes configurable. `test_color_is_validated_where_it_is_read` reaches it by reflection.

The retired seed filters `alondra_default_prefs` and `alondra_delete_data_on_uninstall` are called once, by `ImportSeedFiltersMigration` (migration 4, on `init` 11 so a theme's listeners are registered), which stores what they return without overwriting a key already present. `alondra_default_prefs` is handed the old defaults for its four keys and only keys a listener changed are imported. An option already holding keys the import does not write came from an older settings screen that saved an unchecked box by leaving it out, so `clickable_layout`, `highlight_pricing`, `overwrite_product_price` and `enable_cache` are written `'0'` when absent there, before and over the filter. Nothing else reads the two filters.

## Storefront layouts

**`alondra_pricing_layout_views`** `( [ 'table' => TableView::class ] )` maps a layout id to a `Front\PricingLayoutView` subclass. `PricingLayoutViewFactory::create( get_pricing_layout() )` keeps only class-strings that extend `PricingLayoutView`, falls back to the table for any other id, and returns a fresh instance per call. The stored id is never rewritten, so a layout whose view is not registered renders as the table and returns when it is. `PricingLayoutView::render( SimpleTierDto[] $tiers, string $is_clickable_class )`; `TableView` calls `protected subtotal()` (returns `''`) between `</tbody>` and `</table>` for a subclass.

Views are dumb: typed `render()` parameters, return HTML, no container, services, state or hooks.

## Tiered pricing save seams

`TieredPricingController`'s protected `patch_tiers()` / `patch_rules()`, `tier_from_payload()` / `rule_from_payload()` (one payload entry over its stored or default base) and `entity_dto()` (what REST responses and the editor's localized data carry). A subclass widens payload and response there.

- The save is a patch too: a key the payload omits keeps what is stored; omitting `tiers` or `rules` entirely keeps the stored list, sending it makes the list authoritative.
- Columns this plugin has no control for are carried through from storage, never reset: `priority` (new groups get `TieredPricing::MIN_PRIORITY`), the five `*_rel` relationship columns (new rules get `ANY`/`OR`), each tier's `is_fixed` (new tiers are fixed) and `bundle_products`.
- `create_from_request()` reads the raw JSON body, not `$request->get_params()`, so a REST arg `default` is never applied.
- The list (admin table and REST `list`) sorts by `TieredPricingService::sortable_columns()` (`id`, `title`; the REST `sort` arg is validated through `is_sortable()`), `id` ASC always appended; a subclass widens it to get a native `ORDER BY`.

REST namespace `alondra/v1`: `GET|POST|PUT /tiered-pricing`, `GET /tiered-pricing/product`.

## Matching

This plugin's own matching (`Rule::is_fulfilled()`): ANY within each list; between categories and tags, and between the target block (products, categories, tags) and the user/role block, AND when both sides are populated, relaxing to OR when one side is empty. Candidates are ordered by group id ascending, so the first group created wins. Prices are the tier's fixed per-unit value.

`find_matching( $product_id, $user_id, $quantity, array $line = [] )` / `find_all_matching( …, array $line = [] )` take an optional `$line` this plugin never interprets; it only passes it to the filters and folds it into the cache key. On a cache miss:

- **`alondra_matching_args`** reshapes the candidate args (keys whitelisted to the rule columns from `RuleDatamapper`; bad values and empty lists fall back; no clause is emitted when every list is empty).
- **`alondra_matching_order_columns`** picks group-table columns to order by (`id` always appended last).
- **`alondra_tiered_pricing_matches`** overrules `is_fulfilled()` per candidate before tiers load.
- **`alondra_cache_key_context`** — any listener that changes the answer must also return a context string here, or the cache serves the old answer (see `cache-design`).

Every non-conforming return falls back to this plugin's own value.

## Tier price

`TieredPricingService::tier_price()` is the one door for both the cart (`get_tiered_price()`) and the tier table and price range (`get_tiers()`): **`alondra_tier_price`** `( float $price, Tier $tier, float $basis )`. `$price` is the tier's fixed value; `$basis` is the active product price (in the cart, the price the line had before its first repricing in the request, memoised per cart item key and product object, since WooCommerce can total a cart more than once). Return `null` to decline the tier (the cart keeps its active price; the row is dropped but still holds its range against lower groups); any other non-finite, negative or non-number return falls back to the fixed value.

## Upsell banner

`Controller::upsell_banner(): string` renders the one notice on the plugin's own screens (`Admin\UpsellBannerView`, non-dismissible, "Get Alondra Plus"), printed as the `PrefPage` header of Tiered Pricing and Settings; `PreferencesController::add_action_links()` adds the same link to the plugin row whenever the banner is not empty. `Controller::upgrade_url()` builds the add-on's checkout through the `alondra/freemius` instance with no pricing id, so the buyer picks the license count; since this plugin is not premium, the SDK sends it to Freemius' hosted checkout rather than an iframe, on purpose. It is built by hand with the same parameters when the instance cannot answer (the test suite). Never this plugin's own pricing page. An override returning `''` hides both. No per-field upsell exists or may be added.

## Helper tasks

`dev/helper/` fires **`alondra_helper_tasks`** (webhook name → `Task` class) so an extension's dev helper can add or override test webhooks. It is not part of the shipped plugin.
