---
name: cache-design
description: "Use when changing TieredPricingRepo's caching, adding a matching input to Rule, touching cache invalidation hooks, the enable_cache setting, or HTTP caching of the admin's REST requests. Covers the generation token, key inputs, isolate(), negative caching, invalidation hooks and why there is no wp_cache_flush()."
---

# Repository cache design

`TieredPricingRepo` caches `find()`, `find_matching()` and `find_all_matching()` in the object cache, one fixed group (`TieredPricingRepo::CACHE_GROUP`); the product id is folded into the key rather than kept as a group per product.

## Generation token

- An opaque token stored in the `alondra_cache_generation` option is folded, with the plugin version from `PluginInfo`, into every key. `flush_cache()` rotates it.
- The token is **rotated, never incremented**: `update_option()` returns early when the new value equals the old, so a counter loses its invalidation to a request that already advanced the option.
- **Never `wp_cache_flush()`.** Flushing the whole site object cache on every save or profile update is worse than no cache at all on a Redis-backed store.

## Key inputs

- Every key assumes all price-affecting inputs are in it or derivable from it: roles from `user_id`; categories, tags and parent product from `product_id`. A matching input added to `Rule` that is not derivable from `(product_id, user_id, quantity)` would silently serve one customer another's price.
- The `find_matching()` / `find_all_matching()` keys (never the `find()` entity key) also end with the `alondra_cache_key_context` filter's string (sanitized to `[A-Za-z0-9.-]`, 32 chars max, non-strings dropped, read once per blog per request) and with an `md5` of the optional `$line` argument after `ksort`. Each is appended only when non-empty, so without them the keys are unchanged. A listener that changes the matching answer must also set `alondra_cache_key_context` (see `extension-points`).

## Reads and writes

- Reads use `wp_cache_get( $key, $group, false, $found )` and branch on `$found`, never on the value: a cached `null` is a real hit ("no rule matched" is `find_matching()`'s common case). Treating a falsy value as the only miss silently disables negative caching on a drop-in that cannot round-trip `null`.
- Both `cache_set()` and the `cache_get_*()` readers copy the entity graph through `isolate()`, on the way in and the way out. `clone` is shallow, so `isolate()` also clones the child `Tier`/`Rule` arrays; without that, `get_tiers()`'s `usort()` reordered a cached entity in place on every front-end request. The set side needs it too: `wp_cache_set()` clones an object payload but never an array one, so the caller that populated a `find_all_matching()` entry would keep live references into it.
- `isolate()` runs only after `is_well_formed()` passes, because its typed callbacks raise a `TypeError` (a fatal on the front-end price path) on a graph whose `tiers`/`rules` hold anything else. A malformed payload is refused on write and degrades to a plain miss on read.

## Invalidation

Beyond the repo's own `save()`/`delete()`, `TieredPricingController::register()` wires `flush_cache()` to `set_user_role` / `add_user_role` / `remove_user_role` and to `set_object_terms` (and term removal) filtered to `product_cat` / `product_tag`. Deliberately not `personal_options_update` (a programmatic `add_role()` fires neither) nor `woocommerce_update_product` (no product field feeds a key). `alondra-helper`'s table-writing tasks call `flush_cache()` after their direct `$wpdb` writes, since those bypass the repository.

## The `enable_cache` setting

The container's `TieredPricingRepo` definition calls `use_cache( enable_cache() )` when it builds the repo, so every caller of the shared instance gets it. Off, every lookup reads the database and nothing is written. On by default.

## HTTP caching of admin requests is request-side

`WP_REST_Server::serve_request()` already sends `Cache-Control: no-cache, must-revalidate, max-age=0, no-store, private` and a 1984 `Expires` on every REST response whenever `rest_send_nocache_headers` is true (default `is_user_logged_in()`, verified on 6.8, 6.9 and 7.1), so a `nocache_headers()` call or a `rest_post_dispatch` filter would only duplicate core. What core cannot reach is a proxy or CDN that matches on URL alone; that is why the search `fetch()`es in `assets/js/src/components/RuleRepeater.js` append a unique `&_=<timestamp>` and every admin `fetch()` sets `cache: 'no-store'`. Writes carry no buster: `POST`/`PUT` are not cache-matched. Adding a response header here looks right and does nothing.

## Testing

Prove a cache hit with query-count deltas; see `phpunit-traps` for the table setup the cache tests need.
