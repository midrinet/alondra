---
name: demo-store
description: "Use when touching the seeded demo store — dev/helper/demo-store/seed.php, the demo theme (dev/demo-theme/), the baked demo images and download file (docker/demo-store/), the store identity in docker/Dockerfile, or the first-boot entrypoint — or when the store looks wrong after a test run or a provisioning change. Covers seeding and idempotency, the Product Bundles gate, the fixed-amount pricing groups, re-seeding after Playwright, baked-image and re-provisioning traps, shipping and taxes, product surfaces, and the image-generation guide."
---

# The demo store

`scripts/setup` seeds a coffee-roastery catalog by running `dev/helper/demo-store/seed.php` through `wp eval-file`, after the entrypoint's first-boot block has finished. The store is also what the WordPress.org screenshots are taken on.

## Seeding

- **The seeder is the single source of truth** for every product, term, user and pricing group the store holds. It is idempotent by construction, not by a flag file: it resolves each entity by slug, SKU, login or title before writing, so a second run reports `unchanged` and writes nothing. WooCommerce's sample-data import is not used; no sample product (Beanie, Belt, Hoodie…) exists.
- **A downloadable row's file has to land in `wp-content/uploads/woocommerce_uploads/`.** WooCommerce turns Approved Download Directories on at install and seeds the list with that one directory, so `set_downloads()` refuses a file anywhere else: it auto-adds the parent directory *disabled* unless the current user can `manage_options`, which neither the webhook nor WP-CLI is. Each downloadable product gets its own copy of the one baked-in PDF, named after its slug, because WooCommerce puts the stored file's name in `Content-Disposition` and `sample.pdf` on a customer's downloads screen announces the fixture.

## The five demo packs are skipped, on purpose

The seeder's `alondra_demo_bundles()` rows need `WC_Product_Bundle`. `scripts/setup` installs WooCommerce Product Bundles without activating it, because this plugin does not price bundles, and a store showing packs it cannot price would advertise a feature the plugin does not have. The seeder reports them as `skipped (Product Bundles is not active)` on its own line and seeds everything else; `alondra_demo_seed_linked_products()` (up-sells and bundle-sells, both Product Bundles surfaces) skips with them. The rows stay in `seed.php`: a store where Product Bundles is active (an extension that prices bundles, for instance) gets them, and only the runtime `class_exists()` gate separates the two stores.

Consequences: the seeded `packs` category exists and is empty, and `dev/demo-theme/patterns/home.php` deliberately does **not** mention packs or gift boxes — the home copy must only claim what the store actually holds.

## Pricing groups are fixed amounts, one group per price point

The plugin has fixed per-unit tier prices only, so a group can only serve products that share a price. That is why `alondra_demo_pricing_groups()` holds one entry per bag and two for the blend's variations. The home copy states that every coffee carries volume pricing and that the product page's table shows what each bag costs, so a coffee left without a group makes the storefront say something untrue.

## Re-seed after a Playwright run

`AlondraHelper.clearData()` clears the plugin's three tables and nothing restores them, so a Playwright run leaves the store without its tier tables — exactly what the screenshots show. `seed_demo_store` (`AlondraHelper.seedDemoStore()`, or `curl -X POST 'http://localhost:8080/?alondra-webhook=seed_demo_store'`) puts them back; re-seed before capturing a store the suite has run against.

- **That webhook clears those three tables itself before seeding, so it also deletes any pricing group made by hand.** Nothing outside them is touched; the rest of the catalog reports `unchanged`.
- The clear is not optional: `clearData()` runs at the *start* of a spec, so the last spec's group survives the suite holding id 1, and `TieredPricingRepo::get_matching_order_columns()` orders candidates by creation id ascending — a leftover with a lower id outranks the re-seeded demo group on the same product, and the page shows that spec's single tier instead of the seeded ladder.
- `scripts/setup` reaches the same seeder through `wp eval-file`, a different entry point that clears nothing, so hand-made groups survive a normal setup.

## Baked image and re-provisioning

- **The entrypoint, the demo images and the demo download file are baked into the container image, and `scripts/setup` never refreshes a tag it already holds locally.** It resolves the image with `docker images -q` and uses an existing tag without pulling or rebuilding, so a tag predating a change to `docker/wp-entrypoint.sh` or `docker/demo-store/` keeps provisioning from the old one; neither `scripts/teardown` nor `scripts/setup` fixes it.
- That includes the three provisioning scripts: `docker/wp-entrypoint.sh`, `docker/setup-tests.sh` and `docker/run-tests.sh` are not bind-mounted over the image's copies. Testing a change to any of them means running `docker/build-image.sh` explicitly, between the teardown and the setup. The seeder aborts naming a missing image file rather than letting WooCommerce's grey placeholder into a capture.
- `build-docker-image.yml` republishes the tag on a push to `main` that touches `docker/**`, or by `workflow_dispatch` with `-f wp= -f php=`. The unit and E2E workflows run `docker/build-image.sh` before `scripts/setup` when the PR changes `docker/`, on a manual run, or when the leg's tag is not published, so such a leg tests the PR's provisioning rather than whatever `main` last published; any other PR pulls the published tag.
- **Re-provisioning is the only way to pick up a provisioning change**, because first boot is one-shot and volume-scoped: `docker/wp-entrypoint.sh` skips everything once `/var/www/html/.post-install-complete` exists, and only `scripts/teardown` (which passes `--volumes`) re-arms it. An existing container will not see a new entrypoint, site identity or theme activation without it. The seeder needs no reset: it is idempotent and `scripts/setup` runs it on every start.

## Store identity and theme live in `docker/Dockerfile`

Not in `.env.sample`: `.env` is gitignored, every developer already has one, and compose passes it through `env_file`, so a knob added to `.env.sample` is overridden by whatever the stale copy holds. The store name, tagline, address, currency, units and shipping method are `ENV` lines in the image, and a new one belongs there too. `wp core install --title` reads `WC_STORE_NAME`.

The active theme is `demo-theme`, the Twenty Twenty-Five child bind-mounted from `dev/demo-theme/`. The entrypoint activates it by name as its **last** theme step, so `WP_THEME` (still `twentytwentyfour`) is installed and activated first and then superseded — changing it does not change the storefront. The parent `twentytwentyfive` ships inside the `wordpress` image. First boot sets `woocommerce_coming_soon` to `no`, so the shop is reachable without `alondra-helper`'s `woocommerce_coming_soon_exclude` filter, which stays as a safety net.

**A new file in `dev/demo-theme/patterns/` needs `wp_clean_themes_cache()` before it registers.** `WP_Theme` caches the pattern file list, and neither `wp transient delete --all` nor `wp cache flush` clears it, so a new pattern silently renders as nothing. Only a fresh provision or that call picks it up.

## Shipping and taxes

Shipping is free and taxes are off. The one shipping method is `free_shipping` on zone 0 (`WC_SHIPPING_ZONE_METHOD_ID` in `docker/Dockerfile`): a shipping row stays in the cart and at checkout while the order total equals the goods, which lets a cart assertion compare a footer total against product sums. The method is written in the entrypoint's one-shot first-boot block, so giving it a cost breaks those assertions at the next teardown, not the next run.

## A product renders only the surfaces its own properties produce

WooCommerce registers the Additional Information tab only when the product `has_attributes()` or `wc_product_enable_dimensions_display` passes `has_weight() || has_dimensions()`. Every physical seeded row therefore carries a weight and dimensions in `kg` and `cm`, written from the image's `WC_WEIGHT_UNIT` / `WC_DIMENSION_UNIT`. Both options have to be written: WooCommerce reads its unit defaults from the **US** row of its locale table whatever `woocommerce_default_country` says, so a Madrid shop in euros renders `lbs` and inches until they exist. Four rows carry neither on purpose: the two virtual products, the hidden `Term Matching Fixture`, and the variable parent, whose visible `Weight` attribute already gives that page the tab.

Seeded names are also bound by the E2E collision rules (see the `e2e-playwright` skill): a product renamed, repriced or re-slugged here changes `tests-e2e/fixtures/catalog.js` in the same commit.

## Images

`docker/demo-store/images/` holds the product images baked into the image; `docker/demo-store/files/` the one PDF the downloadable products are sold as. How they are generated, named and checked is in [images.md](images.md).
