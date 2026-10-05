
=== Alondra ===
Contributors: midrinet
Donate link: https://ko-fi.com/midrinet
Tags: tiered pricing, quantity discount, bulk discount, wholesale, woocommerce, dynamic pricing, wholesale pricing, role based pricing, b2b, volume discount, bulk pricing, category discount
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 2.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Defines dynamic prices for products. Create powerful and flexible rules to decide when to apply them.

== Description ==

**Alondra** sets up tiered pricing and quantity discounts on your WooCommerce products. Each pricing group holds a list of quantity ranges with their price, plus the rules that decide when the group applies, so you can offer exclusive prices to selected products, categories, tags, users or roles.

The rules editor is built for B2B/B2C stores, retailers, wholesalers and service providers: it lives in the WordPress admin, follows the conventions you already know, and needs no code to configure.

### Features ###

- Set fixed prices to be used for a product quantity range.
- Compatible with products and variations.
- Prices are applied on the product page and in the cart.
- Dashboard for managing prices and rules that follows WordPress design and user experience, including draft and trash.
- Define rules to apply prices when some criteria are met. You can select one or more products, any categories, tags, users or roles.
- Show the prices to customers in a table on the product page, above the "Add to Cart" button, or hide the table altogether.
- Allows customer to click on the pricing options to quickly set the quantity value to add to cart.
- Highlights active price among available options for better user experience.
- Overrides the default product price that is shown to customers with a value according to the active rules and prices.
- Caches price lookups in the WordPress object cache, so repeated lookups on a page skip the database. A persistent object cache extends this across requests.
- A settings page in *Settings* > *Alondra* for where the prices table shows, how it behaves, caching and uninstall cleanup.

### Requirements ###

- WooCommerce 10.4.0 or greater. With an older version the plugin does nothing and shows a notice in the administration area.

### Alondra Plus ###

Alondra Plus is a separate, paid add-on from the same author. Everything listed above is part of this plugin and stays that way. Alondra Plus builds on top of it and adds:

- Percentage prices for a quantity range, alongside the fixed prices.
- A priority value on pricing groups, so you decide which group wins when two of them match the same product.
- Logic operators between categories, tags and roles, and between the rule element groups themselves.
- Two more layouts for the prices: pills and list.
- A total price and a product price text that update live as the customer changes the quantity.
- Tiered pricing for WooCommerce Product Bundles, for a whole bundle or for a product inside a specific bundle.

Visit [Alondra website](https://alondra.midri.net) to learn more about it.

== Installation ==

= Automatic installation =

1. Log into your WordPress admin panel and go to *Plugins* > *Add New*.
2. Enter "Alondra" into the search field.
3. Once you've located the plugin, click *Install Now*.
4. Click *Activate*.
5. Create your prices by going to *WooCommerce* > *Tiered Pricing*.

= Manual installation =

1. Download the plugin file to your computer and unzip it.
2. Using an FTP program, or your hosting control panel, upload the unzipped plugin folder to your WordPress installation's `wp-content/plugins/` directory on the server.
3. Log into your WordPress admin panel and activate the plugin from the *Plugins* menu.
4. Create your prices by going to *WooCommerce* > *Tiered Pricing*.

== Frequently Asked Questions ==

= Where do I create the prices? =

In *WooCommerce* > *Tiered Pricing*. Each group holds a list of quantity ranges with their price, plus the rules that decide when the group applies.

= Is it possible to apply discount based on User roles? =

Yes. You can offer discounts for individual users or for users who have a role.

= Is it possible to offer free products to customers with this plugin? =

Yes, you can define a row with price 0 when you define the pricing settings in the group.

= How can I increase the value of certain products? =

You can define a fixed price higher than the original one when you configure the row.

= Will it work with my theme? =

We have tested the plugin with the most popular themes and builders on the market, ensuring that the components that display the price on the front-end are displayed correctly.

= The admin screens show outdated pricing groups. What can I do? =

The plugin's admin requests already ask every cache not to store them, and each one is unique so a cache cannot match it against an older copy. A few setups override that anyway — a CDN told to cache everything and to ignore query strings, or a service worker added by another plugin. If the *Tiered Pricing* screens keep showing data you have already changed, exclude `/wp-json/` from your page cache and your CDN and then clear both. Your saved prices are not affected; only what the screen displays is.

= What happens to my prices if I uninstall the plugin? =

Nothing. Uninstalling removes the plugin's own settings but keeps your pricing groups, tiers and rules, so reinstalling finds them intact.

If you want them gone as well, turn on *Uninstall cleanup* in *Settings* > *Alondra* before you uninstall.

On a multisite network, uninstalling clears the site it runs on. Every other site in the network keeps its pricing, and there is no supported way to clear those from the network admin — removing them is a manual database job for whoever administers the network.

= Is there a paid version? =

Yes. [Alondra Plus](https://alondra.midri.net) is a separate, paid add-on that builds on this one. It adds percentage prices alongside the fixed ones, a priority value on pricing groups, logic operators inside and between the rule element groups, pills and list layouts for the prices, a total and product price text that update live as the customer changes the quantity, and tiered pricing for WooCommerce Product Bundles. A 7-day free trial (credit card required) and a 30-day money-back guarantee are offered.

= Where can I get support? =

Ask in the [plugin's support forum on WordPress.org](https://wordpress.org/support/plugin/alondra/). If you have found a bug you can reproduce, or want to contribute, open an issue or a pull request at [github.com/midrinet/alondra](https://github.com/midrinet/alondra).

= Is multisite supported? =

Currently Alondra plugin should be compatible with multisite out of the box, but it is still pending from being fully tested.

== Source code & build tools ==

The plugin ships the compiled `assets/js/*.js` and `assets/css/*.css` files together with the sources and tooling they are built from, so every distributed file can be reproduced:

* JavaScript sources: `assets/js/src/` (bundled by webpack, entry points `admin.js` and `front.js`).
* Stylesheet sources: `assets/css/scss/` (compiled by Dart Sass, then autoprefixed by PostCSS).
* Build configuration: `package.json`, `pnpm-lock.yaml`, `webpack.config.js`, `babel.config.json`, `postcss.config.js`.
* Autoloader configuration: `composer.json`, `composer.lock`. Composer installs the one runtime dependency, the Freemius SDK in `vendor/`, and generates the class autoloader.

To rebuild the assets from the plugin directory:

`
pnpm install
pnpm run build
`

`pnpm run dev` produces the same files unminified and with source maps, and `composer install --no-dev` regenerates the autoloader.

Our test suites and code quality configuration are development tools rather than part of the plugin, so they are not included here. They live in the source repository together with everything above.

== Screenshots ==

1. Tiered pricing list.
2. Tiered pricing editor.
3. Product page showing the tiered prices table.

== Changelog ==

= 2.0.0 =
* Added: A Settings link on the plugin's row in the plugins list.
* Added: A settings page in *Settings* > *Alondra*, where you choose where the prices table shows, how it behaves, whether prices are cached and whether uninstalling deletes your pricing, with the version, the changelog, help, Ko-fi and a rating link at hand in its footer.
* Changed: A pricing rule that lists both categories and tags now needs the product to match one of each. A rule that lists both products, categories or tags and users or roles now needs both sides to match. A rule that fills in only one side works as before.
* Changed: "Get Alondra Plus", on the plugin's screens and its row in the plugins list, opens the Alondra Plus checkout.
* Removed: The plugin's own Upgrade menu item and link.
* Removed: The code snippets that adjusted the plugin's defaults or deleted your pricing on uninstall no longer do anything. Whatever a site set through them is carried into the plugin's settings once, when it updates, so move any later change there.
* Fixed: Searching for products in the pricing group editor no longer fails when a product has no SKU.
* Fixed: Searching for products, categories, tags or users in the pricing group editor now finds names containing & or #.
* Fixed: A category, tag or user already picked in a pricing rule no longer shows up again in that field's search results.

The full changelog is at [alondra.midri.net/changelog](https://alondra.midri.net/changelog).

== Upgrade Notice ==

= 2.0.0 =
Your settings and pricing groups are kept. Rules that list both products or terms and users or roles now need both to match. Percentage prices, priorities, extra layouts, live prices and bundle pricing are provided by the separate Alondra Plus add-on.
