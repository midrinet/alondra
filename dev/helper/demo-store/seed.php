<?php
/**
 * Demo store seeder
 *
 * Builds the coffee roastery demo catalog through the WooCommerce CRUD API, and its wholesale
 * pricing through Alondra's own service. Run from scripts/setup with
 * `wp eval-file wp-content/plugins/alondra-helper/demo-store/seed.php`, or over HTTP through the
 * `seed_demo_store` helper webhook, which includes this file. It seeds on include either way.
 *
 * Idempotent: every entity is resolved by slug, SKU, login or title before anything is inserted,
 * so a second run reports "unchanged" and writes nothing.
 *
 * @package Alondra/Helper
 */

use Midrinet\Alondra\Application\Service\TieredPricingService;
use Midrinet\Alondra\Domain\Entity\Rule;
use Midrinet\Alondra\Domain\Entity\Tier;
use Midrinet\Alondra\Domain\Entity\TieredPricing;
use Midrinet\Alondra\Infrastructure\DI\Container;

defined( 'ABSPATH' ) || die( 'Run this file with: wp eval-file wp-content/plugins/alondra-helper/demo-store/seed.php' );

/**
 * Product categories and tags, parents before their children.
 *
 * @return array<int, array<string, string>>
 */
function alondra_demo_terms(): array {
	return [
		[
			'taxonomy' => 'product_cat',
			'name'     => 'Wholesale',
			'slug'     => 'wholesale',
		],
		[
			'taxonomy' => 'product_cat',
			'name'     => 'Roasts',
			'slug'     => 'roasts',
		],
		[
			'taxonomy' => 'product_cat',
			'name'     => 'Equipment',
			'slug'     => 'equipment',
		],
		[
			'taxonomy' => 'product_cat',
			'name'     => 'Grinders',
			'slug'     => 'grinders',
			'parent'   => 'equipment',
		],
		[
			'taxonomy' => 'product_cat',
			'name'     => 'Guides',
			'slug'     => 'guides',
		],
		[
			'taxonomy' => 'product_cat',
			'name'     => 'Packs',
			'slug'     => 'packs',
		],
		[
			'taxonomy' => 'product_tag',
			'name'     => 'Single Origin',
			'slug'     => 'single-origin',
		],
	];
}

/**
 * The demo catalog, in creation order. Post ids are pinned so the E2E catalog contract can
 * hold them; a variation follows its parent. The pinned ids are absolute rather than sequential:
 * the hidden fixture closing this list sits at 1016, above the packs alondra_demo_bundles() pinned
 * at 1011-1015 before it existed.
 *
 * @return array<int, array<string, mixed>>
 */
function alondra_demo_products(): array {
	return [
		[
			'id'                => 1001,
			'type'              => 'simple',
			'title'             => 'Cordillera Reserve Kilo',
			'slug'              => 'cordillera-reserve-kilo',
			'sku'               => 'CDL-KG',
			'regular_price'     => '65.00',
			'sale_price'        => '55.00',
			'weight'            => '1.08',
			'length'            => '30',
			'width'             => '17',
			'height'            => '10',
			'categories'        => [ 'wholesale' ],
			'short_description' => 'Our washed Colombia Huila house darling, roasted for espresso and packed by the kilo.',
			'description'       => 'Grown between 1,700 and 1,900 metres in the Huila highlands and fully washed at the farm, Cordillera Reserve is the lot we roast most of. We take it to a medium roast that keeps the fruit intact while building enough body for espresso, and the cup lands on red apple, panela and toasted almond with a long cocoa finish. Supplied in a one kilogram valved bag, roasted to order and rested for three days before it ships.',
		],
		[
			'id'                => 1002,
			'type'              => 'simple',
			'title'             => 'Yirgacheffe Filter Roast',
			'slug'              => 'yirgacheffe-filter-roast',
			'sku'               => 'YRG-250',
			'regular_price'     => '25.00',
			'weight'            => '0.27',
			'length'            => '18',
			'width'             => '11',
			'height'            => '6',
			'categories'        => [ 'roasts' ],
			'tags'              => [ 'single-origin' ],
			'short_description' => 'A light filter roast from Yirgacheffe, floral and bright, in a 250 gram bag.',
			'description'       => 'Sourced from smallholder washing stations around Yirgacheffe in southern Ethiopia and roasted light for filter, this is the lot we reach for when we want the cup to smell like a garden. Jasmine and bergamot on the nose, stone fruit and lemon through the body, with the clean tea-like finish the region is known for. 250 grams of whole bean, at its best between five and thirty days after the roast date.',
		],
		[
			'id'                => 1003,
			'type'              => 'simple',
			'title'             => 'Sumatra Nightfall Roast',
			'slug'              => 'sumatra-nightfall-roast',
			'sku'               => 'SMT-250',
			'regular_price'     => '20.00',
			'weight'            => '0.27',
			'length'            => '18',
			'width'             => '11',
			'height'            => '6',
			'categories'        => [ 'roasts' ],
			'tags'              => [ 'single-origin' ],
			'short_description' => 'A dark wet-hulled Sumatra, heavy and earthy, in a 250 gram bag.',
			'description'       => 'Wet hulled in the Gayo highlands of northern Sumatra and taken to a full dark roast, Nightfall is the heaviest coffee we make. Expect cedar, dark chocolate and a savoury note of pipe tobacco over a syrupy body that holds its own against milk and against a long steep in a press. 250 grams of whole bean; ground coarse it makes a cold brew that needs nothing added.',
		],
		[
			// No weight or dimensions: the two variations carry their own, and a variation only falls
			// back to its parent when its own is empty. The visible Weight attribute is what gives this
			// one an Additional Information tab.
			'id'                => 1004,
			'type'              => 'variable',
			'title'             => 'Cascade House Blend',
			'slug'              => 'cascade-house-blend',
			'sku'               => 'CSC-000',
			'categories'        => [ 'roasts' ],
			'attribute'         => [
				'name'    => 'Weight',
				'options' => [ '250 g', '1 kg' ],
			],
			'short_description' => 'The blend we drink all day: balanced, sweet and forgiving, in 250 gram and one kilogram bags.',
			'description'       => 'Three quarters washed Central American for sweetness and clarity, one quarter natural Brazilian for body, roasted to the medium point where it works in a pourover on Monday and on an espresso machine come Saturday. Milk chocolate, orange peel and roasted hazelnut, with enough sugar in the cup to survive a slightly careless brew. Take the 250 gram bag to try it and the kilo once it becomes the coffee you stop thinking about.',
		],
		[
			'id'            => 1005,
			'type'          => 'variation',
			'parent'        => 1004,
			'title'         => 'Cascade House Blend - 250 g',
			'slug'          => 'cascade-house-blend-250-g',
			'sku'           => 'CSC-250',
			'regular_price' => '18.00',
			'weight'        => '0.27',
			'length'        => '18',
			'width'         => '11',
			'height'        => '6',
			'attributes'    => [ 'weight' => '250 g' ],
			'menu_order'    => 0,
		],
		[
			'id'            => 1006,
			'type'          => 'variation',
			'parent'        => 1004,
			'title'         => 'Cascade House Blend - 1 kg',
			'slug'          => 'cascade-house-blend-1-kg',
			'sku'           => 'CSC-KG',
			'regular_price' => '58.00',
			'weight'        => '1.08',
			'length'        => '30',
			'width'         => '17',
			'height'        => '10',
			'attributes'    => [ 'weight' => '1 kg' ],
			'menu_order'    => 1,
		],
		[
			'id'                => 1007,
			'type'              => 'simple',
			'title'             => 'Stoneburr Hand Grinder',
			'slug'              => 'stoneburr-hand-grinder',
			'sku'               => 'EQP-GRD',
			'regular_price'     => '45.00',
			'weight'            => '0.56',
			'length'            => '18',
			'width'             => '6',
			'height'            => '6',
			'categories'        => [ 'grinders' ],
			'short_description' => 'A steel burr hand grinder that holds its setting from espresso to press.',
			'description'       => 'A stainless steel body, a walnut crank knob and a 38 millimetre conical burr set that steps cleanly from espresso to French press without wandering. The folding handle and the 30 gram catch cup make it the grinder we pack for travel, and the burrs are replaceable, so it outlasts most of the kit on this page. Weighs 560 grams and needs no power.',
		],
		[
			'id'                => 1008,
			'type'              => 'simple',
			'title'             => 'Glass Pourover Dripper',
			'slug'              => 'glass-pourover-dripper',
			'sku'               => 'EQP-DRP',
			'regular_price'     => '18.00',
			'weight'            => '0.35',
			'length'            => '14',
			'width'             => '14',
			'height'            => '10',
			'categories'        => [ 'equipment' ],
			'short_description' => 'A borosilicate cone dripper with a walnut collar, sized for one or two cups.',
			'description'       => 'A single piece of borosilicate glass with tall internal ribs that keep the paper off the wall and the drawdown even, finished with a walnut collar and a leather tie so it stays safe to hold when the brew is hot. Takes standard size 02 cone filters and sits on any mug or carafe up to 12 centimetres wide. Dishwasher safe, though a rinse and a dry treats it better.',
		],
		[
			'id'                => 1009,
			'type'              => 'simple',
			'title'             => 'Home Roasting Course',
			'slug'              => 'home-roasting-course',
			'sku'               => 'DIG-CRS',
			'regular_price'     => '29.00',
			'categories'        => [ 'guides' ],
			'virtual'           => true,
			'downloadable'      => true,
			'short_description' => 'Six video lessons that take you from green beans to a repeatable roast at home.',
			'description'       => 'Six filmed lessons, about two hours in total, covering green coffee selection, drying and development, reading first crack by ear, logging a profile and correcting the three faults that spoil most first attempts. Recorded on the small drum roaster we use for sample batches, with the same charge weights and temperatures you can repeat on a popper or in a pan. Access is immediate after checkout and yours to keep.',
		],
		[
			'id'                => 1010,
			'type'              => 'simple',
			'title'             => 'Brew Guide Ebook',
			'slug'              => 'brew-guide-ebook',
			'sku'               => 'DIG-EBK',
			'regular_price'     => '9.00',
			'categories'        => [ 'guides' ],
			'virtual'           => true,
			'downloadable'      => true,
			'short_description' => 'A 48 page brewing guide with recipes for pourover, press, moka and cold brew.',
			'description'       => 'Forty eight pages of recipes and ratios for the four brewers most people already own, written so a page can be followed with one hand while the kettle is going. Each method gets a dose, a grind description, a pour schedule and a short list of what to change when the cup comes out thin or bitter. Delivered as a PDF sized for a phone screen as well as for paper.',
		],
		[
			'id'                 => 1016,
			'type'               => 'simple',
			'title'              => 'Term Matching Fixture',
			'slug'               => 'term-matching-fixture',
			'sku'                => 'FIX-TERM',
			'regular_price'      => '30.00',
			// One category and no tag, so a test telling ALL from ANY can add a second category
			// and a second tag.
			'categories'         => [ 'wholesale' ],
			// Hidden from the catalog and from search, so the shop, the home collection, the site
			// search, the related products block and the category counts all pass it over and a
			// mutation a failed run abandons cannot reach a shopper or a screenshot. Hidden still
			// leaves it reachable at its permalink and purchasable, which is how 005 prices it.
			'catalog_visibility' => 'hidden',
			'image'              => false,
			'short_description'  => 'Never listed and never for sale: the product the E2E suite rewrites terms on.',
			'description'        => 'This product exists so the end to end suite has somewhere to add and remove categories and tags while it checks the relationship operators, instead of doing that to a product the store actually sells. It is seeded with one category and no tag, and the specs put it back that way.',
		],
	];
}

/**
 * The demo packs, in creation order, pinned at 1011-1015 inside the same block as the products.
 *
 * Seeded only when Product Bundles is active, so they are kept out of alondra_demo_products() rather
 * than filtered back out of it. Each row carries an extra `bundle` entry: the container settings and
 * the bundled items, whose meta uses Product Bundles' own vocabulary so the stored value and the one
 * written here compare directly.
 *
 * The set covers both container shapes deliberately. Roastery Gift Box and Brew Starter Kit are static
 * -- nothing inside them is priced individually, so the cart prices the container as it would any plain
 * product -- while the other three are per item, which is the shape the bundle-specific pricing
 * surfaces answer for.
 *
 * @return array<int, array<string, mixed>>
 */
function alondra_demo_bundles(): array {
	return [
		[
			'id'                => 1011,
			'type'              => 'bundle',
			'title'             => 'Roastery Gift Box',
			'slug'              => 'roastery-gift-box',
			'sku'               => 'BDL-GIFT',
			// Regular is the sum of the three, sale is what the box actually costs, so the saving is
			// on screen rather than only in the description.
			'regular_price'     => '63.00',
			'sale_price'        => '55.00',
			'weight'            => '1.25',
			'length'            => '32',
			'width'             => '24',
			'height'            => '12',
			'categories'        => [ 'packs' ],
			'short_description' => 'Two single origins and a glass dripper in one box, priced as a set.',
			'description'       => 'A 250 gram bag of Yirgacheffe, a 250 gram bag of Sumatra Nightfall and the glass pourover dripper to brew them, packed in a ribboned box with a card you can write on. The three come to 63.00 bought separately and the box is 55.00, so the saving sits in the container rather than in the parts. Nothing inside carries its own price, which is what makes it a gift: one line on the receipt and one figure to explain.',
			'bundle'            => [
				'items' => [
					[
						'product_id' => 1002,
						'meta'       => [
							'priced_individually'  => 'no',
							'shipped_individually' => 'no',
						],
					],
					[
						'product_id' => 1003,
						'meta'       => [
							'priced_individually'  => 'no',
							'shipped_individually' => 'no',
						],
					],
					[
						'product_id' => 1008,
						'meta'       => [
							'priced_individually'  => 'no',
							'shipped_individually' => 'no',
						],
					],
				],
			],
		],
		[
			'id'                => 1012,
			'type'              => 'bundle',
			'title'             => 'Origin Sampler Pack',
			'slug'              => 'origin-sampler-pack',
			'sku'               => 'BDL-SMPL',
			'weight'            => '0.7',
			'length'            => '24',
			'width'             => '20',
			'height'            => '8',
			'categories'        => [ 'packs' ],
			'short_description' => 'Both single origins together, each bag 15 percent off.',
			'description'       => 'The light one and the dark one side by side: 250 grams of Yirgacheffe Filter Roast and 250 grams of Sumatra Nightfall Roast, the two ends of what we roast. Each bag keeps its own price and gives up 15 percent of it inside the pack, so the discount lands on the coffee rather than on the box and the receipt still shows what each bag cost. The one most people start with when they cannot decide.',
			'bundle'            => [
				'items' => [
					[
						'product_id' => 1002,
						'meta'       => [
							'priced_individually' => 'yes',
							'discount'            => '15',
						],
					],
					[
						'product_id' => 1003,
						'meta'       => [
							'priced_individually' => 'yes',
							'discount'            => '15',
						],
					],
				],
			],
		],
		[
			// Unassembled: the container is virtual, the chosen bags keep their own weight, dimensions
			// and shipping classes, and nothing here is repacked. That is also what lets Item Grouping
			// be None -- Product Bundles only accepts it on a virtual container with no base price.
			'id'                => 1013,
			'type'              => 'bundle',
			'title'             => 'Build Your Own Box',
			'slug'              => 'build-your-own-box',
			'sku'               => 'BDL-MIX',
			'categories'        => [ 'packs' ],
			'virtual'           => true,
			'short_description' => 'Pick three bags or more and take 10 percent off every one.',
			'description'       => 'Choose at least three bags from the coffees we have open, in any mix you like: three of one, two and one, or one of each, and up to three of any single coffee. The house blend comes in whichever weight you pick. Every bag is priced on its own and gives up 10 percent inside the selection, and each one travels in its own packaging rather than being repacked, so the coffee arrives exactly as it would bought separately.',
			'bundle'            => [
				'min_size'   => 3,
				'group_mode' => 'none',
				'layout'     => 'tabular',
				'items'      => [
					[
						'product_id' => 1002,
						'meta'       => alondra_demo_pick_and_mix_item_meta(),
					],
					[
						'product_id' => 1003,
						'meta'       => alondra_demo_pick_and_mix_item_meta(),
					],
					[
						'product_id' => 1004,
						'meta'       => alondra_demo_pick_and_mix_item_meta(),
					],
				],
			],
		],
		[
			'id'                => 1014,
			'type'              => 'bundle',
			'title'             => 'Brew Starter Kit',
			'slug'              => 'brew-starter-kit',
			'sku'               => 'BDL-KIT',
			// One price for the whole kit: nothing inside it is priced individually, so the cart prices
			// the container the way it prices any plain product.
			'regular_price'     => '79.00',
			'weight'            => '1.5',
			'length'            => '34',
			'width'             => '26',
			'height'            => '14',
			'categories'        => [ 'packs' ],
			'short_description' => 'A grinder, a dripper and a bag of coffee, assembled as one kit.',
			'description'       => 'Everything needed to brew a good cup on a kitchen counter, put together and shipped as one item: the Stoneburr hand grinder, the glass pourover dripper and a 250 gram bag of Yirgacheffe to start on. The three come to 88.00 bought separately and the assembled kit is 79.00. The contents are fixed and the kit leaves here in a single box rather than as three parcels, which is why the parts are not listed one by one at checkout.',
			'bundle'            => [
				'items' => [
					[
						'product_id' => 1008,
						'meta'       => alondra_demo_assembled_item_meta(),
					],
					[
						'product_id' => 1007,
						'meta'       => alondra_demo_assembled_item_meta(),
					],
					[
						'product_id' => 1002,
						'meta'       => alondra_demo_assembled_item_meta(),
					],
				],
			],
		],
		[
			'id'                => 1015,
			'type'              => 'bundle',
			'title'             => 'Digital Brewing Pack',
			'slug'              => 'digital-brewing-pack',
			'sku'               => 'BDL-DIGI',
			'categories'        => [ 'guides' ],
			// Product Bundles derives the container's own virtual flag from this one, but set_props()
			// runs first and would leave a change behind on every later run if the row omitted it.
			'virtual'           => true,
			// The pack offers one download of its own alongside the two it contains: Product Bundles does
			// not build that archive, so the seeder supplies the file the way it does for any other
			// downloadable row.
			'downloadable'      => true,
			'short_description' => 'The roasting course with the brewing guide added at no cost.',
			'description'       => 'The six lesson home roasting course and the 48 page brew guide in one download. The course is priced as it always is and the guide rides along free inside the pack, so the pack costs what the course costs on its own. Nothing ships: both parts are delivered as files the moment the order completes.',
			'bundle'            => [
				'virtual' => true,
				'items'   => [
					[
						'product_id' => 1009,
						'meta'       => [
							'priced_individually' => 'yes',
							// The course carries the pack's whole price, so quoting it beside the pack's
							// own figure only invites the reader to check the arithmetic.
							'single_product_price_visibility' => 'hidden',
						],
					],
					[
						'product_id' => 1010,
						// The free guide keeps its price on screen: a struck-through 9.00 is the offer.
						'meta'       => [
							'priced_individually' => 'yes',
							'discount'            => '100',
						],
					],
				],
			],
		],
	];
}

/**
 * Bundled item meta for an assembled kit: a part of the container rather than a product of its own,
 * so it carries no price, ships inside the container, and is hidden on the product page, in the cart
 * and on the order.
 *
 * @return array<string, string>
 */
function alondra_demo_assembled_item_meta(): array {
	return [
		'priced_individually'       => 'no',
		'shipped_individually'      => 'no',
		'single_product_visibility' => 'hidden',
		'cart_visibility'           => 'hidden',
		'order_visibility'          => 'hidden',
	];
}

/**
 * Bundled item meta for a pick-and-mix slot: priced on its own at a discount, and free to be left out
 * of the selection entirely or taken up to three times.
 *
 * A zero minimum rather than the Optional checkbox, because Optional renders a tick box beside each
 * row and the tabular layout this container uses is a quantity table.
 *
 * @return array<string, string|int>
 */
function alondra_demo_pick_and_mix_item_meta(): array {
	return [
		'priced_individually' => 'yes',
		'discount'            => '10',
		'quantity_min'        => 0,
		'quantity_max'        => 3,
	];
}

/**
 * The wholesale pricing groups, one per price point.
 *
 * A tier holds the absolute price of one unit, not a discount off it: this plugin prices every
 * tier as an amount. A single group can therefore only serve products that share a price, which is why
 * there is one per bag rather than one per shelf, and why the blend needs one per variation.
 *
 * Each tier is keyed by the quantity it opens at. Ranges are contiguous -- a tier runs to one below
 * the next and the last one is open ended -- so they cannot overlap, which the entity rejects.
 *
 * @return array<int, array{title: string, sku: string, tiers: array<int, float>}>
 */
function alondra_demo_pricing_groups(): array {
	return [
		[
			'title' => 'Cordillera kilo wholesale',
			'sku'   => 'CDL-KG',
			'tiers' => [
				2 => 52.00,
				4 => 48.00,
				8 => 44.00,
			],
		],
		[
			'title' => 'Yirgacheffe 250 g wholesale',
			'sku'   => 'YRG-250',
			'tiers' => [
				3  => 23.00,
				6  => 21.00,
				12 => 19.00,
			],
		],
		[
			'title' => 'Sumatra 250 g wholesale',
			'sku'   => 'SMT-250',
			'tiers' => [
				3  => 18.50,
				6  => 17.00,
				12 => 16.00,
			],
		],
		[
			'title' => 'Cascade 250 g wholesale',
			'sku'   => 'CSC-250',
			'tiers' => [
				3  => 16.50,
				6  => 15.50,
				12 => 14.50,
			],
		],
		[
			'title' => 'Cascade 1 kg wholesale',
			'sku'   => 'CSC-KG',
			'tiers' => [
				2 => 54.00,
				4 => 51.00,
				8 => 47.00,
			],
		],
	];
}

/**
 * Abort the run, naming what went wrong.
 *
 * @param string $message Failure message.
 * @throws Exception Off WP-CLI, where the webhook that included this file turns it into a 500.
 * @return never
 */
function alondra_demo_abort( string $message ): void {
	if ( defined( 'WP_CLI' ) ) {
		WP_CLI::error( $message );
		exit( 1 );
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- the webhook returns the reason as JSON, and WP-CLI prints it to a terminal; neither renders it as HTML.
	throw new Exception( $message, 500 );
}

/**
 * Emit one line of the provisioning report: printed under WP-CLI, collected otherwise.
 *
 * Called with no line it returns what has been collected, which is how the webhook reads the
 * report back after including this file.
 *
 * @param string|null $line Line to emit, or null to read the collected ones.
 * @return array<int, string> Every line collected so far.
 */
function alondra_demo_line( ?string $line = null ): array {
	static $lines = [];

	if ( null === $line ) {
		return $lines;
	}

	$lines[] = $line;

	if ( defined( 'WP_CLI' ) ) {
		WP_CLI::line( $line );
	}

	return $lines;
}

/**
 * Emit one entity line of the provisioning report.
 *
 * @param string $kind   Entity kind, e.g. "product".
 * @param string $name   Entity name, slug or SKU.
 * @param string $action What happened: created, updated or unchanged.
 */
function alondra_demo_report( string $kind, string $name, string $action ): void {
	alondra_demo_line( sprintf( '  %-10s %-30s %s', $kind, $name, $action ) );
}

/**
 * Create the demo categories and tag, and return their term ids keyed by slug.
 *
 * @return array<string, int>
 */
function alondra_demo_seed_terms(): array {
	$term_ids = [];

	foreach ( alondra_demo_terms() as $term ) {
		$existing = get_term_by( 'slug', $term['slug'], $term['taxonomy'] );

		if ( $existing instanceof WP_Term ) {
			$term_ids[ $term['slug'] ] = (int) $existing->term_id;
			alondra_demo_report( $term['taxonomy'], $term['slug'], 'unchanged' );
			continue;
		}

		$created = wp_insert_term(
			$term['name'],
			$term['taxonomy'],
			[
				'slug'   => $term['slug'],
				'parent' => isset( $term['parent'] ) ? $term_ids[ $term['parent'] ] : 0,
			]
		);

		if ( is_wp_error( $created ) ) {
			alondra_demo_abort( sprintf( 'Could not create the %s term "%s": %s', $term['taxonomy'], $term['slug'], $created->get_error_message() ) );
		}

		$term_ids[ $term['slug'] ] = (int) $created['term_id'];
		alondra_demo_report( $term['taxonomy'], $term['slug'], 'created' );
	}

	return $term_ids;
}

/**
 * Create the second shop user the E2E user autocomplete needs.
 */
function alondra_demo_seed_user(): void {
	$existing = get_user_by( 'login', 'marta' );

	if ( $existing instanceof WP_User ) {
		if ( in_array( 'shop_manager', $existing->roles, true ) ) {
			alondra_demo_report( 'user', 'marta', 'unchanged' );
			return;
		}

		$existing->set_role( 'shop_manager' );
		alondra_demo_report( 'user', 'marta', 'updated' );
		return;
	}

	$user_id = wp_insert_user(
		[
			'user_login'   => 'marta',
			'user_pass'    => wp_generate_password( 24 ),
			'user_email'   => 'marta@example.com',
			'first_name'   => 'Marta',
			'last_name'    => 'Ruiz',
			'nickname'     => 'Marta Ruiz',
			'display_name' => 'Marta Ruiz',
			'role'         => 'shop_manager',
		]
	);

	if ( is_wp_error( $user_id ) ) {
		alondra_demo_abort( 'Could not create the demo shop manager "marta": ' . $user_id->get_error_message() );
	}

	alondra_demo_report( 'user', 'marta', 'created' );
}

/**
 * Resolve a demo post, creating it as a draft at its pinned id when it is absent.
 *
 * @param array<string, mixed> $row A row of alondra_demo_products().
 * @return bool True when the post was created by this call.
 */
function alondra_demo_reserve_post( array $row ): bool {
	$is_variation = 'variation' === $row['type'];
	$post_type    = $is_variation ? 'product_variation' : 'product';
	$pinned_id    = (int) $row['id'];

	if ( $is_variation ) {
		$existing = (int) wc_get_product_id_by_sku( (string) $row['sku'] );
	} else {
		$post     = get_page_by_path( (string) $row['slug'], OBJECT, 'product' );
		$existing = $post instanceof WP_Post ? (int) $post->ID : 0;
	}

	if ( 0 === $existing ) {
		// A run that aborted between reserving a post and writing its slug or SKU leaves the
		// post unresolvable by either; the pinned id is what lets the next run pick it back up.
		$reserved = get_post( $pinned_id );
		if ( $reserved instanceof WP_Post && $post_type === $reserved->post_type ) {
			$existing = $pinned_id;
		}
	}

	if ( $existing > 0 ) {
		if ( $existing !== $pinned_id ) {
			alondra_demo_abort( sprintf( 'The demo catalog pins "%s" to post %d, but the store already holds it as post %d. Reset the store with scripts/teardown and provision it again.', $row['slug'], $pinned_id, $existing ) );
		}

		return false;
	}

	$created = wp_insert_post(
		[
			'import_id'   => $pinned_id,
			'post_type'   => $post_type,
			// Draft until the pass that follows has written the price, the product type, the terms
			// and the image; set_props() publishes it there. Reserving as publish puts every row
			// after an abort into the shop loop priceless and typeless.
			'post_status' => 'draft',
			'post_title'  => (string) $row['title'],
			'post_name'   => (string) $row['slug'],
			'post_parent' => isset( $row['parent'] ) ? (int) $row['parent'] : 0,
			'post_author' => 1,
		],
		true
	);

	if ( is_wp_error( $created ) ) {
		alondra_demo_abort( sprintf( 'Could not create the demo post for "%s": %s', $row['slug'], $created->get_error_message() ) );
	}

	// wp_insert_post() drops import_id and falls back to auto-increment without saying so when
	// the id is already taken, which would retarget every spec that reads the catalog contract.
	if ( (int) $created !== $pinned_id ) {
		alondra_demo_abort( sprintf( 'The demo catalog pins "%s" to post %d, but WordPress created it as post %d because %d was already taken. Reset the store with scripts/teardown and provision it again.', $row['slug'], $pinned_id, (int) $created, $pinned_id ) );
	}

	return true;
}

/**
 * Sideload the product's featured image from the images baked into the dev container.
 *
 * @param WC_Product $product Product to attach the image to. Not saved by this function.
 * @param string     $slug    Product slug; the image file is named after it.
 * @return bool True when an image was sideloaded by this call.
 */
function alondra_demo_attach_image( WC_Product $product, string $slug ): bool {
	if ( $product->get_image_id() && wp_attachment_is_image( (int) $product->get_image_id() ) ) {
		return false;
	}

	$source = '/demo-store/images/' . $slug . '.jpg';

	if ( ! is_readable( $source ) ) {
		alondra_demo_abort( sprintf( 'The demo product image %s is missing or unreadable. It is baked into the dev container by docker/Dockerfile, so a published image tag that predates it carries no images: rebuild the image or republish the tag, then provision again.', $source ) );
	}

	// wp_handle_sideload() unlinks the file it is handed (_wp_handle_upload() in
	// wp-admin/includes/file.php copies and then unlinks), so it gets a throwaway copy and the
	// baked-in original survives for the next run.
	$temp_file = wp_tempnam( $slug . '.jpg' );

	if ( ! $temp_file || ! copy( $source, $temp_file ) ) {
		alondra_demo_abort( sprintf( 'Could not copy %s to a temporary file for sideloading.', $source ) );
	}

	$attachment_id = media_handle_sideload(
		[
			'name'     => $slug . '.jpg',
			'tmp_name' => $temp_file,
		],
		$product->get_id(),
		$product->get_name()
	);

	if ( is_wp_error( $attachment_id ) ) {
		if ( file_exists( $temp_file ) ) {
			wp_delete_file( $temp_file );
		}

		alondra_demo_abort( sprintf( 'Could not sideload %s: %s', $source, $attachment_id->get_error_message() ) );
	}

	$product->set_image_id( $attachment_id );

	return true;
}

/**
 * Register a downloadable product's file, copying it out of the dev container under a name derived
 * from the product.
 *
 * One source file serves every downloadable product, but each gets its own copy: the file name is
 * what WooCommerce puts in the Content-Disposition header, so a shared "sample.pdf" would announce
 * the fixture on the customer's downloads screen.
 *
 * The copy lands in wp-content/uploads/woocommerce_uploads because that is the one directory a
 * download can live in without further setup: WooCommerce enables its Approved Download Directories
 * on install and seeds the list with that directory alone, so anywhere else is refused unless a site
 * administrator approves it. It is also the only one WooCommerce protects with a deny-all .htaccess,
 * so the file is reached through the download endpoint rather than by guessing its URL. The baked-in
 * original is outside the web root and serves neither purpose.
 *
 * @param WC_Product $product Product to register the download on. Not saved by this function.
 * @param string     $slug    Product slug; the copy and the download id are named after it.
 * @param string     $name    Download name, which WooCommerce lists on the downloads screen.
 * @return bool True when this call copied the file or wrote the download.
 */
function alondra_demo_attach_download( WC_Product $product, string $slug, string $name ): bool {
	$uploads = wp_upload_dir();
	$target  = $uploads['basedir'] . '/woocommerce_uploads/' . $slug . '.pdf';
	$copied  = false;

	if ( ! file_exists( $target ) ) {
		$source = '/demo-store/files/sample.pdf';

		if ( ! is_readable( $source ) ) {
			alondra_demo_abort( sprintf( 'The demo download file %s is missing or unreadable. It is baked into the dev container by docker/Dockerfile, so a published image tag that predates it carries no download file: rebuild the image or republish the tag, then provision again.', $source ) );
		}

		if ( ! wp_mkdir_p( dirname( $target ) ) || ! copy( $source, $target ) ) {
			alondra_demo_abort( sprintf( 'Could not copy %s to %s.', $source, $target ) );
		}

		$copied = true;
	}

	$url     = $uploads['baseurl'] . '/woocommerce_uploads/' . $slug . '.pdf';
	$stored  = $product->get_downloads( 'edit' );
	$current = $stored[ $slug ] ?? null;

	// get_downloads() hands back WC_Product_Download objects and set_prop() compares with !==, which
	// for objects is identity: handing the stored download back would read as a change on every run,
	// so an unchanged one is never handed over at all.
	if ( 1 === count( $stored ) && $current instanceof WC_Product_Download && $current->get_file() === $url && $current->get_name() === $name && $current->get_enabled() ) {
		return $copied;
	}

	$download = new WC_Product_Download();
	// An id of our own rather than the UUID WooCommerce mints for an id-less download, which would be
	// a new one on every run.
	$download->set_id( $slug );
	$download->set_name( $name );
	$download->set_file( $url );

	$product->set_downloads( [ $download ] );

	return true;
}

/**
 * Write a simple or variable product's props, image and terms.
 *
 * @param array<string, mixed> $row      A row of alondra_demo_products().
 * @param array<string, int>   $term_ids Term ids keyed by slug.
 * @param bool                 $created  Whether the post was created by this run.
 */
function alondra_demo_seed_product( array $row, array $term_ids, bool $created ): void {
	$is_variable = 'variable' === $row['type'];
	$product     = alondra_demo_product_object( $row );

	// set_props() collects a rejected prop into a WP_Error instead of throwing, so an unchecked
	// return is how a duplicate SKU saves a product silently without one.
	$result = $product->set_props(
		[
			'name'               => $row['title'],
			'slug'               => $row['slug'],
			'status'             => 'publish',
			'catalog_visibility' => $row['catalog_visibility'] ?? 'visible',
			'sku'                => $row['sku'],
			'description'        => $row['description'],
			'short_description'  => $row['short_description'],
			'regular_price'      => $row['regular_price'] ?? '',
			'sale_price'         => $row['sale_price'] ?? '',
			// Every physical row carries all four: a product with neither a weight nor a dimension gets
			// no Additional Information tab, and layout_pos = tab_additional_info then has nowhere to
			// render the tier block.
			'weight'             => $row['weight'] ?? '',
			'length'             => $row['length'] ?? '',
			'width'              => $row['width'] ?? '',
			'height'             => $row['height'] ?? '',
			'virtual'            => ! empty( $row['virtual'] ),
			'downloadable'       => ! empty( $row['downloadable'] ),
			'category_ids'       => alondra_demo_term_ids( $row['categories'], $term_ids ),
			'tag_ids'            => alondra_demo_term_ids( $row['tags'] ?? [], $term_ids ),
		]
	);

	if ( is_wp_error( $result ) ) {
		alondra_demo_abort( sprintf( 'WooCommerce rejected the demo product "%s": %s', $row['slug'], $result->get_error_message() ) );
	}

	if ( $is_variable ) {
		alondra_demo_set_variation_attribute( $product, $row['attribute'] );
	}

	// Bundled items live in their own tables rather than in the product's props, so a rewrite of them
	// is invisible to get_changes() and has to be reported separately.
	$items_rewritten = false;

	if ( $product instanceof WC_Product_Bundle ) {
		$items_rewritten = alondra_demo_set_bundle_config( $product, $row['bundle'] );
	}

	// A row can opt out of an image, and exactly one does: the hidden fixture is never rendered, so
	// generating one for it would be waste. The opt-out is reported so it cannot be mistaken for a
	// missing file, which still aborts the run.
	$sideloaded = false;

	if ( false === ( $row['image'] ?? true ) ) {
		alondra_demo_report( 'image', (string) $row['slug'], 'skipped (never rendered)' );
	} else {
		$sideloaded = alondra_demo_attach_image( $product, (string) $row['slug'] );
	}

	// Every downloadable row gets a file, the pack included: WooCommerce sells a downloadable product
	// with no file as one that delivers nothing, and Product Bundles does not build a container's
	// download out of its children's.
	$download_written = ! empty( $row['downloadable'] ) && alondra_demo_attach_download( $product, (string) $row['slug'], (string) $row['title'] );

	$changed = $product->get_changes();
	$product->save();

	alondra_demo_report( 'product', $row['slug'] . ' (' . $row['id'] . ')', alondra_demo_action( $created, ! empty( $changed ) || $items_rewritten || $download_written, $sideloaded ) );
}

/**
 * The CRUD object a catalog row is written through.
 *
 * @param array<string, mixed> $row A row of alondra_demo_products() or alondra_demo_bundles().
 * @return WC_Product
 */
function alondra_demo_product_object( array $row ): WC_Product {
	$id = (int) $row['id'];

	if ( 'bundle' === $row['type'] ) {
		return new WC_Product_Bundle( $id );
	}

	return 'variable' === $row['type'] ? new WC_Product_Variable( $id ) : new WC_Product_Simple( $id );
}

/**
 * Write a container's own bundle settings and its bundled items.
 *
 * @param WC_Product_Bundle    $bundle Bundle container.
 * @param array<string, mixed> $config The row's `bundle` entry.
 * @return bool True when the bundled items had to be rewritten.
 */
function alondra_demo_set_bundle_config( WC_Product_Bundle $bundle, array $config ): bool {
	// The sizes are cast to string because that is what comes back out of post meta, and Product
	// Bundles compares props with !==: an int here reads as a change on every later run.
	$bundle->set_virtual_bundle( ! empty( $config['virtual'] ) );
	$bundle->set_min_bundle_size( isset( $config['min_size'] ) ? (string) $config['min_size'] : '' );
	$bundle->set_max_bundle_size( isset( $config['max_size'] ) ? (string) $config['max_size'] : '' );
	// Both setters fall back to the default on an unknown value, so the defaults can be passed through
	// them rather than branched around. Product Bundles then re-validates the group mode on save and
	// silently resets it to Grouped unless the container is virtual and carries no price of its own.
	$bundle->set_group_mode( (string) ( $config['group_mode'] ?? 'parent' ) );
	$bundle->set_layout( (string) ( $config['layout'] ?? 'default' ) );

	$items = [];

	foreach ( array_values( $config['items'] ) as $menu_order => $item ) {
		$items[] = [
			'bundled_item_id' => 0,
			'product_id'      => (int) $item['product_id'],
			'menu_order'      => $menu_order,
			'meta_data'       => $item['meta'],
		];
	}

	// set_bundled_data_items() marks the list dirty whatever it is handed and matches rows by bundled
	// item id, which this seeder cannot supply: handing it the stored configuration again would delete
	// every row and insert it back under a new id, so an unchanged list is never handed over at all.
	if ( alondra_demo_bundle_items_stored( $bundle, $items ) ) {
		return false;
	}

	$bundle->set_bundled_data_items( $items );

	return true;
}

/**
 * Whether the container already holds exactly these bundled items.
 *
 * Product Bundles sanitizes meta on the way in, so both sides are compared as strings; only the keys
 * the seeder sets are looked at, and the rest keep Product Bundles' own defaults.
 *
 * @param WC_Product_Bundle                $bundle Bundle container.
 * @param array<int, array<string, mixed>> $items  Desired bundled items.
 * @return bool
 */
function alondra_demo_bundle_items_stored( WC_Product_Bundle $bundle, array $items ): bool {
	$stored = [];

	foreach ( $bundle->get_bundled_data_items( 'edit' ) as $stored_item ) {
		$stored[ (int) $stored_item->get_product_id() ] = $stored_item;
	}

	if ( count( $stored ) !== count( $items ) ) {
		return false;
	}

	foreach ( $items as $item ) {
		$product_id = (int) $item['product_id'];

		if ( ! isset( $stored[ $product_id ] ) || (int) $stored[ $product_id ]->get_menu_order() !== (int) $item['menu_order'] ) {
			return false;
		}

		foreach ( $item['meta_data'] as $key => $value ) {
			if ( (string) $stored[ $product_id ]->get_meta( $key ) !== (string) $value ) {
				return false;
			}
		}
	}

	return true;
}

/**
 * Wire the two relationships that are Linked Products rather than bundles of their own: the sampler
 * pack as an up-sell on the Yirgacheffe bag, and the grinder and dripper as its bundle-sells.
 *
 * The up-sell points at the sampler rather than at the brewing kit because an up-sell is a better
 * version of the product being viewed: the sampler holds this bag plus a cheaper second one, both
 * discounted, while the kit is a static container whose parts are hidden and carry no price.
 */
function alondra_demo_seed_linked_products(): void {
	$reason = 'the up-sell and bundle-sell wiring has nothing to hang on';
	$host   = wc_get_product( alondra_demo_product_id( 'YRG-250', $reason ) );

	if ( ! $host instanceof WC_Product ) {
		alondra_demo_abort( 'The demo catalog product "YRG-250" could not be read, so ' . $reason . '.' );
	}

	$upsells        = [ alondra_demo_product_id( 'BDL-SMPL', $reason ) ];
	$bundle_sells   = [ alondra_demo_product_id( 'EQP-GRD', $reason ), alondra_demo_product_id( 'EQP-DRP', $reason ) ];
	$sells_title    = 'Frequently bought with this bag';
	$sells_discount = 10.0;
	$changed        = false;

	if ( $host->get_upsell_ids( 'edit' ) !== $upsells ) {
		$host->set_upsell_ids( $upsells );
		$changed = true;
	}

	// Bundle-sells are plain post meta rather than CRUD props, so writing them leaves no trace in
	// get_changes() and the stored values have to be read back before anything is written. The ids
	// getter hands back the raw meta, an empty string, when the product carries none, and it drops
	// entries rather than reindexing, so the cast and the array_values() are both load bearing.
	$stored_sells = (array) WC_PB_BS_Product::get_bundle_sell_ids( $host, 'edit' );

	if ( array_values( $stored_sells ) !== $bundle_sells ) {
		$host->update_meta_data( '_wc_pb_bundle_sell_ids', $bundle_sells );
		$changed = true;
	}

	if ( WC_PB_BS_Product::get_bundle_sells_title( $host, 'edit' ) !== $sells_title ) {
		$host->update_meta_data( '_wc_pb_bundle_sells_title', $sells_title );
		$changed = true;
	}

	// Read straight from the meta rather than through get_bundle_sells_discount(), which answers an
	// empty string whenever the store is not on the 'filters' discount method and would then rewrite
	// the same value on every run. The admin stores it as a float, so the comparison is one too.
	if ( (float) $host->get_meta( '_wc_pb_bundle_sells_discount', true, 'edit' ) !== $sells_discount ) {
		$host->update_meta_data( '_wc_pb_bundle_sells_discount', $sells_discount );
		$changed = true;
	}

	if ( $changed ) {
		$host->save();
	}

	alondra_demo_report( 'linked', $host->get_slug(), $changed ? 'updated' : 'unchanged' );
}

/**
 * Resolve a seeded SKU to its product id.
 *
 * @param string $sku    Seeded SKU.
 * @param string $reason What is lost when it is missing, completing "... is missing, so ".
 * @return int
 */
function alondra_demo_product_id( string $sku, string $reason ): int {
	$product_id = (int) wc_get_product_id_by_sku( $sku );

	if ( 0 === $product_id ) {
		alondra_demo_abort( sprintf( 'The demo catalog SKU "%s" is missing, so %s.', $sku, $reason ) );
	}

	return $product_id;
}

/**
 * Write a variation's props.
 *
 * @param array<string, mixed> $row     A row of alondra_demo_products().
 * @param bool                 $created Whether the post was created by this run.
 */
function alondra_demo_seed_variation( array $row, bool $created ): void {
	$variation = new WC_Product_Variation( (int) $row['id'] );

	$result = $variation->set_props(
		[
			'parent_id'     => (int) $row['parent'],
			'status'        => 'publish',
			'sku'           => $row['sku'],
			'regular_price' => $row['regular_price'],
			'weight'        => $row['weight'],
			'length'        => $row['length'],
			'width'         => $row['width'],
			'height'        => $row['height'],
			'menu_order'    => (int) $row['menu_order'],
			'attributes'    => $row['attributes'],
		]
	);

	if ( is_wp_error( $result ) ) {
		alondra_demo_abort( sprintf( 'WooCommerce rejected the demo variation "%s": %s', $row['sku'], $result->get_error_message() ) );
	}

	$changed = $variation->get_changes();
	$variation->save();

	alondra_demo_report( 'variation', $row['sku'] . ' (' . $row['id'] . ')', alondra_demo_action( $created, ! empty( $changed ), false ) );
}

/**
 * Give a variable product the local attribute its variations vary on.
 *
 * @param WC_Product           $product   Variable product.
 * @param array<string, mixed> $attribute Attribute name and options.
 */
function alondra_demo_set_variation_attribute( WC_Product $product, array $attribute ): void {
	$existing = $product->get_attributes();
	$current  = $existing[ sanitize_title( $attribute['name'] ) ] ?? null;

	// WC_Data::set_prop() compares with !==, which for the WC_Product_Attribute objects read back
	// from the database is identity, so re-setting an unchanged attribute always reads as a change
	// and every run would report the product as updated.
	if ( $current instanceof WC_Product_Attribute && $current->get_options() === $attribute['options'] ) {
		return;
	}

	$variation_attribute = new WC_Product_Attribute();
	$variation_attribute->set_name( $attribute['name'] );
	$variation_attribute->set_options( $attribute['options'] );
	$variation_attribute->set_position( 0 );
	$variation_attribute->set_visible( true );
	$variation_attribute->set_variation( true );

	$product->set_attributes( [ $variation_attribute ] );
}

/**
 * Translate term slugs into term ids.
 *
 * @param array<int, string> $slugs    Term slugs.
 * @param array<string, int> $term_ids Term ids keyed by slug.
 * @return array<int, int>
 */
function alondra_demo_term_ids( array $slugs, array $term_ids ): array {
	$ids = [];

	foreach ( $slugs as $slug ) {
		$ids[] = $term_ids[ $slug ];
	}

	return $ids;
}

/**
 * The word the report prints for an entity.
 *
 * @param bool $created    The post was inserted by this run.
 * @param bool $changed    Props differed from what was stored.
 * @param bool $sideloaded An image was sideloaded by this run.
 * @return string
 */
function alondra_demo_action( bool $created, bool $changed, bool $sideloaded ): string {
	$action = $created ? 'created' : ( $changed ? 'updated' : 'unchanged' );

	return $sideloaded ? $action . ' (image sideloaded)' : $action;
}

/**
 * Alondra's tiered pricing service, the one door that owns writing a pricing group.
 *
 * @return TieredPricingService
 */
function alondra_demo_pricing_service(): TieredPricingService {
	if ( ! class_exists( Container::class ) ) {
		alondra_demo_abort( 'The Alondra plugin is not active, so the demo pricing groups cannot be created. Activate it and run scripts/setup again.' );
	}

	return Container::instance()->get( TieredPricingService::class );
}

/**
 * Whether a pricing group with this exact title is already stored.
 *
 * The listing search is a substring match, so the exact comparison is what makes resolving by title
 * safe against a title that happens to contain another.
 *
 * @param TieredPricingService $service Tiered pricing service.
 * @param string                 $title   Group title.
 * @return bool
 */
function alondra_demo_pricing_group_exists( TieredPricingService $service, string $title ): bool {
	$results = $service->get_paged_results( $title, null, 1, 100, 'id', 'asc' );

	foreach ( $results['items'] as $group ) {
		if ( $group->title === $title ) {
			return true;
		}
	}

	return false;
}

/**
 * Build the tiers of one group from its quantity-keyed price list.
 *
 * @param array<int, float> $prices Unit price keyed by the quantity its range opens at.
 * @return array<int, Tier>
 */
function alondra_demo_tiers( array $prices ): array {
	$quantities = array_keys( $prices );
	$tiers      = [];

	foreach ( $quantities as $index => $from ) {
		$to      = isset( $quantities[ $index + 1 ] ) ? $quantities[ $index + 1 ] - 1 : Tier::MAX_UNITS;
		$tiers[] = new Tier( 0, 0, $from, $to, true, $prices[ $from ] );
	}

	return $tiers;
}

/**
 * Create the wholesale pricing groups through Alondra's own service.
 */
function alondra_demo_seed_pricing(): void {
	$service = alondra_demo_pricing_service();

	foreach ( alondra_demo_pricing_groups() as $group ) {
		if ( alondra_demo_pricing_group_exists( $service, $group['title'] ) ) {
			alondra_demo_report( 'pricing', $group['title'], 'unchanged' );
			continue;
		}

		$product_id = alondra_demo_product_id( $group['sku'], sprintf( 'the pricing group "%s" would target nothing', $group['title'] ) );

		// The permissive side of every relationship, which is what this plugin matches on whatever
		// is stored -- so the rule behaves the same with or without an add-on that reads it.
		$rule                                       = new Rule();
		$rule->products                             = [ $product_id ];
		$rule->roles_rel                            = Rule::RELATIONSHIP_ANY;
		$rule->cats_rel                             = Rule::RELATIONSHIP_ANY;
		$rule->tags_rel                             = Rule::RELATIONSHIP_ANY;
		$rule->tags_with_cats_rel                   = Rule::RELATIONSHIP_OR;
		$rule->prods_cats_tags_with_roles_users_rel = Rule::RELATIONSHIP_OR;

		$entity = new TieredPricing(
			0,
			$group['title'],
			TieredPricing::MIN_PRIORITY,
			TieredPricing::STATUS_PUBLISH,
			gmdate( 'Y-m-d H:i:s' ),
			alondra_demo_tiers( $group['tiers'] ),
			[ $rule ]
		);

		$saved = $service->save( $entity );

		if ( is_wp_error( $saved ) ) {
			alondra_demo_abort( sprintf( 'Alondra rejected the pricing group "%s": %s', $group['title'], $saved->get_error_message() ) );
		}

		alondra_demo_report( 'pricing', $group['title'], 'created' );
	}
}

/**
 * Seed the demo catalog.
 */
function alondra_demo_seed(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		alondra_demo_abort( 'WooCommerce is not active, so the demo catalog cannot be created. Activate WooCommerce and run scripts/setup again.' );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	alondra_demo_line( 'Seeding the demo store catalog...' );

	$term_ids = alondra_demo_seed_terms();
	alondra_demo_seed_user();

	// scripts/setup installs Product Bundles but leaves it inactive, since this plugin does not price
	// bundles: the packs are reported as skipped rather than aborting a run that would otherwise seed
	// the whole catalog, and a store where Product Bundles is active gets them. The skip is loud, and it
	// is the seeder's own line that says so -- not the absence of one.
	$has_bundles = class_exists( 'WC_Product_Bundle' );
	$rows        = alondra_demo_products();

	if ( $has_bundles ) {
		$rows = array_merge( $rows, alondra_demo_bundles() );
	} else {
		alondra_demo_report( 'bundle', 'demo packs (1011-1015)', 'skipped (Product Bundles is not active)' );
	}

	// Every pinned post is created before the first image is sideloaded: an explicit ID moves the
	// posts table AUTO_INCREMENT to max + 1, so an attachment created in between would eat the
	// next reserved id and the assertion in alondra_demo_reserve_post() would abort the run.
	$created = [];
	foreach ( $rows as $row ) {
		$created[ $row['id'] ] = alondra_demo_reserve_post( $row );
	}

	foreach ( $rows as $row ) {
		if ( 'variation' === $row['type'] ) {
			alondra_demo_seed_variation( $row, $created[ $row['id'] ] );
			continue;
		}

		alondra_demo_seed_product( $row, $term_ids, $created[ $row['id'] ] );
	}

	// Nothing on the save path syncs a variable product off its children, so its price range stays
	// empty until it is asked for explicitly. A pass of its own, after the children are written.
	foreach ( $rows as $row ) {
		if ( 'variable' === $row['type'] ) {
			WC_Product_Variable::sync( (int) $row['id'] );
		}
	}

	// After the packs, since the up-sell points at one of them.
	if ( $has_bundles ) {
		alondra_demo_seed_linked_products();
	} else {
		alondra_demo_report( 'linked', 'yirgacheffe-filter-roast', 'skipped (Product Bundles is not active)' );
	}

	// Last, so every rule resolves against a SKU this run has already written. The E2E suite clears
	// these three tables, so a store the suite has run against needs this seeder again to get them back.
	alondra_demo_seed_pricing();

	if ( defined( 'WP_CLI' ) ) {
		WP_CLI::success( 'Demo store catalog ready.' );

		return;
	}

	alondra_demo_line( 'Demo store catalog ready.' );
}

alondra_demo_seed();
