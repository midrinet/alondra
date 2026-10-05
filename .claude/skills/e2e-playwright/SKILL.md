---
name: e2e-playwright
description: "Use when writing, changing or debugging a Playwright spec or page object under tests-e2e/, or when a change to seeded catalog data, the admin editor, the settings page or the plugins screen could break one. Covers how to run the suite, the catalog contract module, the title/term/SKU collision rules, the cache-buster and snackbar traps, reload-after-publish, and the plugin-management (003) behaviours."
---

# Playwright E2E

## Running

The suite needs the stack running (`scripts/setup`) and is slow; prefer PHPUnit for anything it can prove. `scripts/playwright` loads `.env` and runs `npx playwright test` on the host (Node and `scripts/pnpm install` first).

```bash
scripts/playwright                                                     # All specs
scripts/playwright 001                                                 # Specs whose path matches
scripts/playwright ./tests-e2e/specs/001-tiered-pricing-admin.spec.js  # One file
scripts/playwright --headed | --ui | --debug                           # Visible runs (pnpm exec playwright install first)
```

One `chromium` project, `retries: 0`, one worker, timeout 5 minutes locally and 2 on CI, trace and screenshot kept on failure, slow motion only on visible runs. Page objects live in `tests-e2e/fixtures/`; specs take `test`/`expect` from `fixtures/test.js`.

Specs:

- `001-tiered-pricing-admin.spec.js` — admin form and CRUD, conflict resolution, quantity ranges.
- `002-settings.spec.js` — the settings page: only the plugin's own fields render; a checkbox save survives a reload (the spec puts the value back); the one banner opens the Alondra Plus checkout with no Ko-fi link; the plugin row offers "Get Alondra Plus" and no "Upgrade"; the header shows the logo and "Alondra"; the footer carries FREE, the changelog, help, Ko-fi and the rating (changelog, help and reviews URLs are requested and must answer 200 off the site root); none of those appear on Tiered Pricing; header, tabs and footer fit 360px; the form sits in the single Settings tab; the saved notice shows once.
- `003-plugin-management.spec.js` — activation, deactivation and uninstall (below).
- `005-tiered-pricing-frontend.spec.js` — product and cart price assertions.

`e2e-tests.yml` runs `001`, `002`, `003` and `005` as separate legs, each on its own runner with its own containers and volumes, so nothing a suite does to the seeded store reaches another. `003` has its own leg because "uninstalling removes the plugin's data" is a promise to WordPress.org users and something reviewers check. It is the least deterministic spec (activation redirects, a JS confirm dialog, AJAX row rewriting) against `retries: 0`; if it proves flaky, stabilise the spec rather than drop the leg. It reads no catalog data.

After a run the store has lost its demo pricing groups; re-seed before taking screenshots (see the `demo-store` skill).

## Writing specs and page objects

- Read the existing specs and fixtures first and match their patterns; new specs follow the `NNN-description.spec.js` sequence.
- Specs go through page objects, never raw selectors; page objects hold selectors and semantic actions, never assertions.
- No fixed waits: wait on a selector, a response or a load state.
- Assert what persists (reload, REST, cart), not only what the UI shows right after an action.

## The catalog contract

**The seeded catalog reaches the specs through one module, `tests-e2e/fixtures/catalog.js`; a spec holds no catalog literal of its own.** Everything is addressed by role — `ON_SALE`, `CHILD_A`, `CHILD_B`, `VARIATION`, `TERM_FIXTURE`, `CATEGORIES`, `TAG`, `OTHER_USER`, `PROFILES`, `SAMPLE_TIERS` — so re-pointing the suite at another catalog is one file.

- `dev/helper/demo-store/seed.php` is the source of truth for every value there, pinned ids included: a product renamed, repriced or re-slugged in the seeder changes here in the same commit, and nothing enforces it.
- `CreateSampleTieredPricingTask` is a third link: it resolves products, categories, tag and user by slug, SKU and login, so a renamed demo slug breaks the `create_sample_tiered_pricing` webhook `001` depends on.
- Two exports are seeded but currently unread: `TERM_FIXTURE` and `SAMPLE_TIERS` (the specs that call `createSampleTieredPricing()` only change its status).
- `Cordillera Reserve Kilo` (regular 65 / sale 55) is the only product with a native WooCommerce sale, so a native-sale-plus-tier scenario needs no setup. Its page carries no related products or up-sells, because a reader counting `.price del` anywhere on the page would read a neighbour's sale badge as this one's.
- `Single Origin` is the only seeded `product_tag`; the on-sale product does not carry it.
- `Term Matching Fixture` (post 1016) exists only for tests that rewrite a product's terms mid-run. It is seeded with catalog visibility **hidden** — out of the shop loop, home collection, search, related products and category counts — so an aborted run abandons a product no spec or screenshot sees. It stays reachable at its permalink and purchasable.
- **A spec that mutates any other seeded product corrupts the store for every later run**, and for the screenshots.
- Helper webhooks: `set_product_tags` / `set_product_categories` (`AlondraHelper.setProductTags()` / `.setProductCategories()`) assign terms; `set_user_roles` (`AlondraHelper.setUserRoles()`) grants extra roles (the admin otherwise has one).
- The rule form's users/profiles fields search by WordPress display name, not username or role slug ("Shop Manager", not "shopmanager").

## Collision rules for seeded names

Two properties of the running code bind every seeded name; breaking one silently retargets a test instead of failing it.

- `ProductRepo::get_products()` matches `post_title LIKE`, `ID = %d` and `_sku LIKE` over `product` and `product_variation`, caps at five rows and has **no `ORDER BY`**, so a term matching more rows than the cap can drop the target. Every product search a spec types must match **exactly one** row.
- The category, tag and user autocompletes return at most three and match by substring (`assets/js/src/components/RuleRepeater.js`).
- So no product title may contain another's, no term name or slug another term's, no SKU another SKU. And no apostrophe, quote or double hyphen in a title: `wptexturize()` rewrites them and breaks an exact `toHaveText`.

Not collisions: `'cordillera'` matching both a product and the group `Cordillera kilo wholesale` (groups live in the plugin's own table; the product search reads `wp_posts`), and the same for the `sku` keys `alondra_demo_pricing_groups()` names. The category autocomplete requests `_fields=id,name`, so options and chips carry the bare name (`Grinders`, never `Equipment > Grinders`), which keeps a `hasText` match on the parent from resolving the child's chip. `CartPage.getItemPrice()` reads `.first()` of the cart items block; every cart test empties the cart and buys one product.

## Traps

- **Reload after publish.** When a test edits an already-published entity right after `.publish()`, reload first: the admin SPA can still be mid post-create refetch, which silently reverts in-place edits.
- **Cache-buster assertions** (`001`): assert the request **URL** against `/[?&]_=\d+/`, never request headers — whether `cache: 'no-store'` also emits `Cache-Control`/`Pragma` is browser-dependent. Give each assertion its own search string: TomSelect memoizes searches per control (`loadedSearches`), so a repeated query never reaches the network and `waitForRequest` hangs.
- **Snackbar timing.** An assertion right after a previous snackbar must allow for the 300 ms re-show window `Snackbar.show()` waits out before swapping content.
- **Row action URLs** carry `_wpnonce`, so the fixture matches on `href*="action=…&id=…&"` rather than the whole URL.

## Plugin management (`003`)

- `WpAdmin.activatePlugin/deactivatePlugin/deletePlugin(pluginPath)` drive the real `plugins.php`; rows are targeted by `data-plugin` because a plugin and its test copy share the display name.
- Activate/deactivate poll the list until the state settles, which also rides out the Freemius opt-in redirect after activation. Deactivation opens Freemius' "Quick Feedback" modal, dismissed with "Skip & Deactivate". Delete is a JS confirm dialog, and WP removes the plugin by rewriting the row to a `.deleted` state rather than detaching it.
- To exercise uninstall without deleting the bind-mounted source, `provision_plugin_copy` (`AlondraHelper.provisionPluginCopy()`) copies the live plugin (minus `.git`, `dev/`, `docker/`, `release/`, `.env`) to `wp-content/plugins/alondra-uninstall-copy`, in the plugins **volume**, so the real Delete flow runs `uninstall.php` against it; `remove_plugin_copy` cleans up. Flow: deactivate `alondra` → activate copy → deactivate copy → delete copy.
- `plugin_status` (`AlondraHelper.getPluginStatus()`) probes the `wp_alondra_*` tables and the `alondra` option; `activate_plugin` (`AlondraHelper.ensureAlondraActive()`) reactivates `alondra` page-free in teardown (browser pages are unreliable in `afterAll`).
- `Requires Plugins: woocommerce` makes WordPress remove WooCommerce's Deactivate link, so `WpAdmin.isPluginActive('woocommerce/woocommerce.php')` always reports false. "No other plugin was touched" is enforced by the `Generic.PHP.ForbiddenFunctions` rule in `.phpcs.xml.dist` (no `deactivate_plugins`/`activate_plugin`) instead.

## Helper webhooks

`dev/helper/` (`alondra-helper`) serves the webhooks the fixtures call (`?alondra-webhook=<name>`). `Plugin::get_task_for_webhook()` builds its name → `Task` map through `alondra_helper_tasks`, so an extension's own helper can add or override tasks. `dev/helper/bruno/` keeps one Bruno request per webhook; a new or changed webhook updates it. Table-writing tasks call the plugin's `flush_cache()` after their direct `$wpdb` writes.
