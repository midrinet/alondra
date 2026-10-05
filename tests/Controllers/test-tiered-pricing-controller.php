<?php
/**
 * TieredPricingController Tests
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Controllers;

use Midrinet\Alondra\Application\Dto\TieredPricingDto;
use Midrinet\Alondra\Application\Service\PreferencesService;
use Midrinet\Alondra\Application\Service\TieredPricingService;
use Midrinet\Alondra\Domain\Entity\Rule;
use Midrinet\Alondra\Domain\Entity\Tier;
use Midrinet\Alondra\Domain\Entity\TieredPricing;
use Midrinet\Alondra\Infrastructure\Config\PluginInfo;
use Midrinet\Alondra\Infrastructure\Controller\AssetController;
use Midrinet\Alondra\Infrastructure\Controller\TieredPricingController;
use Midrinet\Alondra\Infrastructure\DI\Container;
use Midrinet\Alondra\Infrastructure\View\ListTable;
use Midrinet\Alondra\Infrastructure\View\TieredPricingListTableAdapter;
use Midrinet\Alondra\Infrastructure\View\Front\PricingLayoutView;
use Midrinet\Alondra\Infrastructure\View\Front\PricingLayoutViewFactory;
use Midrinet\Alondra\Tests\Support\Bundle_Fixture;
use Midrinet\Alondra\Tests\Support\Container_Seam;
use Midrinet\Alondra\Tests\Support\Leftover_Bundle_Product;
use WC_Cart;
use WC_Product_Simple;
use WC_Product_Variable;
use WP_UnitTestCase;

require_once __DIR__ . '/../Support/trait-bundle-fixture.php';
require_once __DIR__ . '/../Support/trait-container-seam.php';
require_once __DIR__ . '/../Support/class-leftover-bundle-product.php';

/**
 * Covers the nonce gate on the actions that change a single item. Without it
 * a link is enough to trash or delete a rule on behalf of a logged in shop
 * manager.
 */
class TieredPricingControllerTest extends WP_UnitTestCase {

	use Container_Seam;
	use Bundle_Fixture;

	private const ITEM_ID         = 5;
	private const REWRITTEN_PRICE = 'rewritten by Alondra';
	private const TIERS_MARKER    = 'alondra-tiers';

	private $pricing_service;

	/**
	 * Controllers a case hooked, unhooked again in tear_down().
	 *
	 * @var object[]
	 */
	private array $hooked = [];

	public function tear_down() {
		$this->unhook_all();
		unset( $_REQUEST['action'], $_REQUEST['id'], $_REQUEST['_wpnonce'] );
		// Without Product Bundles the bundle tables do not exist and the cases that write them skip.
		if ( class_exists( 'WC_Product_Bundle' ) ) {
			$this->delete_bundles();
		}
		parent::tear_down();
	}

	/**
	 * Every action that changes one item, with the service call it must make.
	 *
	 * @return array<string, array{string, string, array<int, mixed>}>
	 */
	public function provide_single_item_actions() {
		return [
			'delete'  => [ TieredPricingController::ACTION_DELETE, 'delete', [ self::ITEM_ID ] ],
			'draft'   => [ TieredPricingController::ACTION_DRAFT, 'set_status', [ self::ITEM_ID, TieredPricing::STATUS_DRAFT ] ],
			'trash'   => [ TieredPricingController::ACTION_TRASH, 'set_status', [ self::ITEM_ID, TieredPricing::STATUS_TRASH ] ],
			'untrash' => [ TieredPricingController::ACTION_UNTRASH, 'set_status', [ self::ITEM_ID, TieredPricing::STATUS_DRAFT ] ],
		];
	}

	/**
	 * @dataProvider provide_single_item_actions
	 * @param string             $action Requested action.
	 * @param string             $method Service method it must call.
	 * @param array<int, mixed>  $args   Arguments it must be called with.
	 */
	public function test_single_item_action_runs_with_a_valid_nonce( $action, $method, $args ) {
		$this->request( $action, wp_create_nonce( \sprintf( TieredPricingController::ROW_ACTION_NONCE, $action ) ) );

		$controller = $this->get_instance();
		$this->pricing_service->expects( $this->once() )->method( $method )->with( ...$args )->willReturn( 1 );

		$this->run_current_action( $controller );
	}

	/**
	 * @dataProvider provide_single_item_actions
	 * @param string            $action Requested action.
	 * @param string            $method Service method it must not call.
	 * @param array<int, mixed> $args   Unused.
	 */
	public function test_single_item_action_is_ignored_without_a_nonce( $action, $method, $args ) {
		$this->request( $action, null );

		$controller = $this->get_instance();
		$this->pricing_service->expects( $this->never() )->method( $method );

		$this->run_current_action( $controller );
	}

	/**
	 * @dataProvider provide_single_item_actions
	 * @param string            $action Requested action.
	 * @param string            $method Service method it must not call.
	 * @param array<int, mixed> $args   Unused.
	 */
	public function test_single_item_action_is_ignored_with_an_invalid_nonce( $action, $method, $args ) {
		$this->request( $action, 'not-a-nonce' );

		$controller = $this->get_instance();
		$this->pricing_service->expects( $this->never() )->method( $method );

		$this->run_current_action( $controller );
	}

	/**
	 * A nonce is only good for the action it was created for.
	 *
	 * @dataProvider provide_single_item_actions
	 * @param string            $action Requested action.
	 * @param string            $method Service method it must not call.
	 * @param array<int, mixed> $args   Unused.
	 */
	public function test_single_item_action_is_ignored_with_a_nonce_for_another_action( $action, $method, $args ) {
		$this->request( $action, wp_create_nonce( \sprintf( TieredPricingController::ROW_ACTION_NONCE, 'somethingelse' ) ) );

		$controller = $this->get_instance();
		$this->pricing_service->expects( $this->never() )->method( $method );

		$this->run_current_action( $controller );
	}

	/**
	 * Product Bundles prices bundled children itself, so a tier applied to a child line stacks a
	 * second discount on top of the bundle's own. The three cart item keys are all the detection
	 * this needs, which is why the case runs with or without Product Bundles installed.
	 */
	public function test_bundled_child_line_is_left_at_its_own_price() {
		$child = new WC_Product_Simple();
		$child->set_regular_price( '10' );
		$child->set_price( '10' );

		$cart = $this->make_cart(
			[
				'child' => [
					'data'            => $child,
					'quantity'        => 2,
					'bundled_by'      => 'container_cart_key',
					'bundled_item_id' => 7,
					'stamp'           => [ 7 => [ 'quantity' => 2 ] ],
				],
			]
		);

		$this->make_controller_with_tiered_price( 7.0 )->set_tiered_prices( $cart );

		$this->assertSame( '10', $child->get_price( 'edit' ) );
		$this->assertSame( '', $child->get_sale_price( 'edit' ) );
	}

	/**
	 * A child of a static bundle used to survive only because Product Bundles zeroes it through a
	 * `woocommerce_product_get_price` filter at priority 98. Reading the prices in 'edit' context
	 * bypasses every filter, so this fails if Alondra writes the tier -- whatever Product Bundles
	 * does with its own hooks.
	 */
	public function test_child_of_a_static_bundle_leaves_the_cart_total_alone() {
		$this->skip_without_bundles();

		$child           = $this->make_child_product( '10' );
		$bundle          = $this->make_bundle( [ $child->get_id() ], false, '25' );
		$bundled_items   = $bundle->get_bundled_items();
		$bundled_item_id = (int) key( $bundled_items );

		$this->assertFalse( reset( $bundled_items )->is_priced_individually() );

		$cart   = $this->make_cart(
			[
				'child' => [
					'data'            => $child,
					'quantity'        => 2,
					'bundled_by'      => 'container_cart_key',
					'bundled_item_id' => $bundled_item_id,
					'stamp'           => [ $bundled_item_id => [ 'quantity' => 2 ] ],
				],
			]
		);
		$before = $this->cart_total( $cart );

		$this->make_controller_with_tiered_price( 7.0 )->set_tiered_prices( $cart );

		$this->assertSame( $before, $this->cart_total( $cart ) );
	}

	/**
	 * A per-item bundle puts all of its money on the children and leaves the container at no price
	 * of its own, unmasked. A tier written there is charged on top of every child.
	 */
	public function test_per_item_bundle_container_is_not_repriced() {
		$this->skip_without_bundles();

		$children = [ $this->make_child_product( '10' )->get_id(), $this->make_child_product( '20' )->get_id() ];
		$bundle   = $this->make_bundle( $children, true );

		$this->assertTrue( (bool) $bundle->contains( 'priced_individually' ) );

		$price = $bundle->get_price( 'edit' );
		$sale  = $bundle->get_sale_price( 'edit' );

		$this->make_controller_with_tiered_price( 30.0 )->set_tiered_prices( $this->make_cart_with_product( $bundle ) );

		$this->assertSame( $price, $bundle->get_price( 'edit' ) );
		$this->assertSame( $sale, $bundle->get_sale_price( 'edit' ) );
	}

	/**
	 * A static bundle carries the whole price on the container and nothing on the children, so it
	 * takes a tier exactly like a simple product. Deliberate, not a leftover of the skip above.
	 */
	public function test_static_bundle_container_is_still_repriced() {
		$this->skip_without_bundles();

		$bundle = $this->make_bundle( [ $this->make_child_product( '10' )->get_id() ], false, '25' );

		$this->make_controller_with_tiered_price( 20.0 )->set_tiered_prices( $this->make_cart_with_product( $bundle ) );

		$this->assertSame( '20', $bundle->get_price( 'edit' ) );
		$this->assertSame( '20', $bundle->get_sale_price( 'edit' ) );
		$this->assertSame( '25', $bundle->get_regular_price( 'edit' ) );
	}

	/**
	 * Product Bundles composes a per-item bundle's price out of its children. Alondra rebuilding that
	 * HTML from the container's own price quoted `0,00 EUR - 30,00 EUR` where Product Bundles alone
	 * rendered `40,00 EUR` -- and on 8.5.11 the bundle's own live total is hidden by default, so the
	 * fabricated figure was the only price on the page.
	 *
	 * A static bundle is the other half of the same gate: its cart line is tiered and its tier table
	 * is rendered, so leaving its price html alone quoted the undiscounted price above a table
	 * promising the discount the cart went on to charge.
	 *
	 * @dataProvider provide_bundle_kinds
	 * @param bool $priced_individually Whether the bundle's children carry their own prices.
	 */
	public function test_bundle_price_html_follows_the_per_item_gate( $priced_individually ) {
		$this->skip_without_bundles();

		$bundle = $this->make_bundle_of_kind( $priced_individually );
		$html   = '<span class="woocommerce-Price-amount">40,00</span>';

		$this->assertSame(
			$priced_individually ? $html : self::REWRITTEN_PRICE,
			$this->make_display_controller()->set_product_price( $html, $bundle )
		);
	}

	/**
	 * @return array<string, array{bool}>
	 */
	public function provide_bundle_kinds() {
		return [
			'per-item bundle' => [ true ],
			'static bundle'   => [ false ],
		];
	}

	/**
	 * The table strikes the tiered price through the container's regular price and quotes the
	 * percentage between them. On a per-item bundle both come off a container that carries no price,
	 * so the table has nothing true to say.
	 */
	public function test_no_tier_table_is_rendered_on_a_per_item_bundle() {
		$this->skip_without_bundles();

		$this->assertStringNotContainsString(
			self::TIERS_MARKER,
			$this->render_tiers_for( $this->make_bundle_of_kind( true ) )
		);
	}

	/**
	 * A static bundle carries its whole price on the container, so the table reads it exactly as it
	 * reads a simple product's. Retained deliberately, matching the cart.
	 */
	public function test_tier_table_is_still_rendered_on_a_static_bundle() {
		$this->skip_without_bundles();

		$this->assertStringContainsString(
			self::TIERS_MARKER,
			$this->render_tiers_for( $this->make_bundle_of_kind( false ) )
		);
	}

	/**
	 * Both guards are additive: nothing that is not a bundle notices them. Runs without Product
	 * Bundles too, which is the install this has to stay byte-for-byte unchanged on.
	 */
	public function test_a_simple_product_is_untouched_by_the_bundle_guards() {
		$product = new WC_Product_Simple();
		$product->set_regular_price( '10' );
		$product->set_price( '10' );

		$this->assertSame( self::REWRITTEN_PRICE, $this->make_display_controller()->set_product_price( '<span>10</span>', $product ) );
		$this->assertStringContainsString( self::TIERS_MARKER, $this->render_tiers_for( $product ) );
	}

	/**
	 * The overwhelming majority of installs never have Product Bundles, and this plugin ships these
	 * guards to every one of them. Nothing on the price path may reach a symbol of it: a `wc_pb_*`
	 * call would fatal this case outright, and re-reading class_exists() with autoloading off after
	 * the cart and display paths have run says no line of them resolved a PB class.
	 *
	 * The bundled child line is here rather than left to the case above because that is the branch
	 * most likely to be "simplified" into wc_pb_maybe_is_bundled_cart_item() -- an undefined
	 * function on every cart page of an install without the plugin.
	 */
	public function test_the_price_path_reaches_no_product_bundles_symbol() {
		if ( class_exists( 'WC_Product_Bundle' ) ) {
			$this->markTestSkipped( 'Product Bundles is loaded, and its absence is what this case asserts (unset ALONDRA_LOAD_PB).' );
		}

		$product = new WC_Product_Simple();
		$product->set_regular_price( '40' );
		$product->set_price( '40' );

		$child = new WC_Product_Simple();
		$child->set_regular_price( '10' );
		$child->set_price( '10' );

		$cart = $this->make_cart(
			[
				'plain' => [
					'data'     => $product,
					'quantity' => 3,
				],
				'child' => [
					'data'            => $child,
					'quantity'        => 2,
					'bundled_by'      => 'container_cart_key',
					'bundled_item_id' => 7,
					'stamp'           => [ 7 => [ 'quantity' => 2 ] ],
				],
			]
		);

		$this->make_controller_with_tiered_price( 32.0 )->set_tiered_prices( $cart );

		$this->assertSame( '32', $product->get_price( 'edit' ) );
		$this->assertSame( '10', $child->get_price( 'edit' ) );

		$this->assertSame( self::REWRITTEN_PRICE, $this->make_display_controller()->set_product_price( '<span>40</span>', $product ) );
		$this->assertStringContainsString( self::TIERS_MARKER, $this->render_tiers_for( $product ) );

		$this->assertFalse( class_exists( 'WC_Product_Bundle', false ), 'the price path pulled in a Product Bundles class' );
		$this->assertFalse( function_exists( 'wc_pb_maybe_is_bundled_cart_item' ), 'the price path pulled in a Product Bundles function' );
	}

	/**
	 * A product reporting type 'bundle' that is not one of Product Bundles' own has no contains(),
	 * so `is_per_item_bundle()` has to ask instanceof before it calls one. Replace that instanceof
	 * with a bare is_type( 'bundle' ) and this case dies on the undefined method, on both the cart
	 * and the product page.
	 *
	 * With the plugin deactivated WC_Product_Factory falls back to WC_Product_Simple, which reports
	 * 'simple' -- so the object that reaches the guard comes from the `woocommerce_product_class`
	 * filter or the deprecated $product_type property instead, both of which yield an arbitrary
	 * WC_Product subclass reporting 'bundle'. Leftover_Bundle_Product declares its type the way
	 * every WooCommerce product class does, WC_Product_Simple included.
	 *
	 * Nothing here depends on whether Product Bundles is loaded: the object is not one either way.
	 */
	public function test_a_bundle_typed_product_that_is_not_a_bundle_is_priced_like_a_simple_one() {
		$product = new Leftover_Bundle_Product();
		$product->set_regular_price( '30' );
		$product->set_price( '30' );

		$this->assertSame( 'bundle', $product->get_type() );
		$this->assertFalse( $product instanceof \WC_Product_Bundle );

		$this->make_controller_with_tiered_price( 21.0 )->set_tiered_prices( $this->make_cart_with_product( $product ) );

		$this->assertSame( '21', $product->get_price( 'edit' ) );
		$this->assertSame( '21', $product->get_sale_price( 'edit' ) );
		$this->assertSame( '30', $product->get_regular_price( 'edit' ) );
		$this->assertStringContainsString( self::TIERS_MARKER, $this->render_tiers_for( $product ) );
	}

	/**
	 * set_product_price()'s early return widened from is_type( 'variable' ) to also cover a per-item
	 * bundle, and nothing else holds the original half of it: a variable product still leaves with
	 * the HTML it arrived with, and still renders no table of its own.
	 */
	public function test_a_variable_product_still_bypasses_the_simple_price_rewrite() {
		$product = new WC_Product_Variable();
		$html    = '<span>10,00 - 20,00</span>';

		$this->assertSame( $html, $this->make_display_controller()->set_product_price( $html, $product ) );
		$this->assertStringNotContainsString( self::TIERS_MARKER, $this->render_tiers_for( $product ) );
	}

	/**
	 * The cart leaves bundled lines to Product Bundles, so the plain surfaces leave its rendering of a
	 * bundled item alone too. The display stub rewrites every price it is asked about, which is what a
	 * plain rule on the child does.
	 */
	public function test_the_plain_surfaces_leave_a_bundled_item_to_product_bundles() {
		$this->skip_without_bundles();

		$child           = $this->make_child_product( '10' );
		$bundle          = $this->make_bundle( [ $child->get_id() ], true );
		$variable_bundle = $this->make_variable_bundle();

		$own          = $this->render_bundled( $bundle );
		$own_variable = $this->render_bundled( $variable_bundle );
		$this->assertNotSame( '', $own['price_html'] );
		$this->assertNotSame( '', $own_variable['price_html'] );
		$this->assertCount( 2, $own_variable['variations'] );

		$this->hook_display_controller();

		$this->assertSame(
			[
				'simple'   => $own,
				'variable' => $own_variable,
			],
			[
				'simple'   => $this->render_bundled( $bundle ),
				'variable' => $this->render_bundled( $variable_bundle ),
			]
		);
	}

	/**
	 * Bundle-sells render through a runtime bundle carrying the host's id and are sold as standalone lines,
	 * so a plain rule keeps pricing them; and the same product standalone is tiered as always.
	 */
	public function test_a_plain_rule_still_prices_a_bundle_sell_and_the_standalone_product() {
		$this->skip_without_bundles();

		$host = $this->make_child_product( '25' );
		$sell = $this->make_child_product( '10' );

		$runtime = new \WC_Product_Bundle( $host );
		$runtime->set_bundled_data_items(
			[
				[
					'bundle_id'  => $host->get_id(),
					'product_id' => $sell->get_id(),
					'meta_data'  => [
						'quantity_min'         => 1,
						'quantity_max'         => 1,
						'priced_individually'  => 'yes',
						'shipped_individually' => 'yes',
						'optional'             => 'yes',
						'discount'             => null,
					],
				],
			]
		);

		$this->hook_display_controller();

		$this->assertSame( self::REWRITTEN_PRICE, $this->render_bundled( $runtime )['price_html'], 'the bundle-sell item' );
		$this->assertSame( self::REWRITTEN_PRICE, wc_get_product( $sell->get_id() )->get_price_html(), 'the standalone product' );
	}

	/**
	 * What a bundle's first item renders under Product Bundles' catalog filters: its price html, and for a
	 * variable item each variation's price html.
	 *
	 * @param  \WC_Product_Bundle $bundle The bundle.
	 * @return array{price_html: string, variations: string[]}
	 */
	private function render_bundled( $bundle ) {
		$items = $bundle->get_bundled_items();
		$item  = reset( $items );
		$this->assertInstanceOf( \WC_Bundled_Item::class, $item );

		\WC_PB_Product_Prices::add_price_filters( $item );
		try {
			$product = $item->get_product();
			return [
				'price_html' => $product->get_price_html(),
				'variations' => $product instanceof WC_Product_Variable ? wp_list_pluck( $product->get_available_variations(), 'price_html' ) : [],
			];
		} finally {
			\WC_PB_Product_Prices::remove_price_filters();
		}
	}

	/**
	 * A per-item bundle holding one variable product with two differently priced variations.
	 *
	 * @return \WC_Product_Bundle
	 */
	private function make_variable_bundle() {
		$parent = new WC_Product_Variable();
		$parent->set_name( 'Alondra variable child' );
		$parent->save();

		$sizes = [
			'small' => '10',
			'large' => '20',
		];
		foreach ( $sizes as $size => $price ) {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $parent->get_id() );
			$variation->set_attributes( [ 'size' => $size ] );
			$variation->set_regular_price( $price );
			$variation->save();
		}
		WC_Product_Variable::sync( $parent->get_id() );

		return $this->make_bundle( [ $parent->get_id() ], true );
	}

	/**
	 * Hook a display controller to the plain surfaces at the priorities the plugin uses.
	 *
	 * @return void
	 */
	private function hook_display_controller() {
		$controller     = $this->make_display_controller();
		$this->hooked[] = $controller;
		add_filter( 'woocommerce_get_price_html', [ $controller, 'set_product_price' ], 99, 2 );
		add_filter( 'woocommerce_variable_price_html', [ $controller, 'set_variable_price' ], 99, 2 );
		add_filter( 'woocommerce_available_variation', [ $controller, 'render_variation_tiers' ], 99, 3 );
	}

	/**
	 * Remove every callback of the controllers a case hooked.
	 *
	 * @return void
	 */
	private function unhook_all() {
		global $wp_filter;

		foreach ( $wp_filter as $hook => $filter ) {
			foreach ( $filter->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					if ( is_array( $callback['function'] ) && in_array( $callback['function'][0], $this->hooked, true ) ) {
						remove_filter( $hook, $callback['function'], $priority );
					}
				}
			}
		}
		$this->hooked = [];
	}

	/**
	 * Build a controller over stubs of every service the display path reaches for: a pricing service
	 * that rewrites whatever price it is handed, and a layout factory standing in for the tier table.
	 *
	 * @return TieredPricingController
	 */
	private function make_display_controller() {
		$service = $this->createStub( TieredPricingService::class );
		$service->method( 'get_price' )->willReturn( self::REWRITTEN_PRICE );
		$service->method( 'get_variable_price' )->willReturn( self::REWRITTEN_PRICE );
		$service->method( 'get_tiers' )->willReturn( [] );

		$preferences = $this->createStub( PreferencesService::class );

		$factory = $this->createStub( PricingLayoutViewFactory::class );
		$factory->method( 'create' )->willReturn(
			new class() extends PricingLayoutView {
				public function render( array $tiers, string $is_clickable_class ): string {
					return 'alondra-tiers';
				}
			}
		);

		$container = $this->createStub( Container::class );
		$container->method( 'get' )->willReturnCallback(
			function ( $key ) use ( $service, $preferences, $factory ) {
				$stubs = [
					PreferencesService::class       => $preferences,
					PricingLayoutViewFactory::class => $factory,
				];

				return isset( $stubs[ $key ] ) ? $stubs[ $key ] : $service;
			}
		);

		$this->install_container( $container );

		return new TieredPricingController();
	}

	/**
	 * Capture what render_product_tiers() echoes for a product, which it reads off the global.
	 *
	 * @param  \WC_Product $subject The product to render for.
	 * @return string
	 */
	private function render_tiers_for( $subject ) {
		global $product;

		$previous = $product;
		$product  = $subject; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the hook reads the global.

		ob_start();
		$this->make_display_controller()->render_product_tiers();
		$html = (string) ob_get_clean();

		$product = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored.

		return $html;
	}

	/**
	 * Build a one-child bundle of either kind.
	 *
	 * @param  bool $priced_individually Whether the child carries its own price.
	 * @return \WC_Product_Bundle
	 */
	private function make_bundle_of_kind( $priced_individually ) {
		return $this->make_bundle( [ $this->make_child_product( '10' )->get_id() ], $priced_individually, '25' );
	}

	/**
	 * Build a controller whose TieredPricingService always resolves to $tiered_price.
	 *
	 * @param  float $tiered_price The value get_tiered_price() should return.
	 * @return TieredPricingController
	 */
	/**
	 * WooCommerce can total the cart twice in one request; the second pass must price from the same basis.
	 */
	public function test_a_second_cart_pass_prices_from_the_original_basis() {
		$product = new WC_Product_Simple();
		$product->set_regular_price( '100' );
		$product->set_price( '100' );
		$cart       = $this->make_cart_with_product( $product );
		$controller = $this->make_controller_pricing_from_basis( 0.9 );

		$controller->set_tiered_prices( $cart );
		$controller->set_tiered_prices( $cart );

		$this->assertSame( '90', $product->get_price( 'edit' ) );

		$readded = new WC_Product_Simple();
		$readded->set_price( '50' );
		$controller->set_tiered_prices( $this->make_cart_with_product( $readded ) );

		$this->assertSame( '45', $readded->get_price( 'edit' ) );
	}

	/**
	 * A declined tier leaves the basis, and the add-on prices go on top of it once.
	 */
	public function test_a_second_cart_pass_adds_the_addons_once() {
		$product = new WC_Product_Simple();
		$product->set_regular_price( '10' );
		$product->set_price( '10' );
		$cart       = $this->make_cart(
			[
				'item' => [
					'data'     => $product,
					'quantity' => 1,
					'addons'   => [ [ 'price' => 2 ] ],
				],
			]
		);
		$controller = $this->make_controller_pricing_from_basis( 1.0 );

		$controller->set_tiered_prices( $cart );
		$controller->set_tiered_prices( $cart );

		$this->assertSame( '12', $product->get_price( 'edit' ) );
	}

	/**
	 * A controller whose service prices every line at a share of the basis it is handed.
	 *
	 * @param float $share Share of the basis.
	 * @return TieredPricingController
	 */
	private function make_controller_pricing_from_basis( float $share ) {
		$service = $this->createStub( TieredPricingService::class );
		$service->method( 'get_tiered_price' )->willReturnCallback(
			function ( $product_id, $quantity, $user_id, $basis ) use ( $share ) {
				return $basis * $share;
			}
		);
		$container = $this->createStub( Container::class );
		$container->method( 'get' )->willReturn( $service );
		$this->install_container( $container );

		return new TieredPricingController();
	}

	private function make_controller_with_tiered_price( $tiered_price ) {
		$service = $this->createStub( TieredPricingService::class );
		$service->method( 'get_tiered_price' )->willReturn( $tiered_price );

		$container = $this->createStub( Container::class );
		$container->method( 'get' )->willReturn( $service );

		$this->install_container( $container );

		return new TieredPricingController();
	}

	/**
	 * Build a WC_Cart stub whose single line item wraps $product.
	 *
	 * @param  \WC_Product $product The cart line item's product.
	 * @return WC_Cart
	 */
	private function make_cart_with_product( $product ) {
		return $this->make_cart(
			[
				'item' => [
					'data'     => $product,
					'quantity' => 1,
				],
			]
		);
	}

	/**
	 * Build a WC_Cart stub over hand-written cart line items.
	 *
	 * @param  array<string, array<string, mixed>> $items The lines get_cart() should return.
	 * @return WC_Cart
	 */
	private function make_cart( array $items ) {
		$cart = $this->createMock( WC_Cart::class );
		$cart->method( 'get_cart' )->willReturn( $items );

		return $cart;
	}

	/**
	 * Sum of the cart lines read straight off the stored prices, with no price filter in the way.
	 *
	 * @param  WC_Cart $cart The cart to add up.
	 * @return float
	 */
	private function cart_total( $cart ) {
		$total = 0.0;
		foreach ( $cart->get_cart() as $cart_item ) {
			$total += (float) $cart_item['quantity'] * (float) $cart_item['data']->get_price( 'edit' );
		}

		return $total;
	}

	/**
	 * Create a saved simple product to bundle.
	 *
	 * @param  string $price Regular price.
	 * @return WC_Product_Simple
	 */
	private function make_child_product( $price ) {
		$product = new WC_Product_Simple();
		$product->set_name( 'Alondra test child' );
		$product->set_regular_price( $price );
		$product->set_price( $price );
		$product->save();

		return $product;
	}

	/**
	 * A stored group holding values the editor renders no control for: priority 9, a percent tier and
	 * ALL/AND on every relationship column, and a bundle-scoped pair. They can only have come from an
	 * add-on that does render them.
	 *
	 * @return TieredPricing
	 */
	private function stored_group() {
		$entity          = new TieredPricing( 7, 'Stored', 9, 'publish', '2024-01-01 00:00:00' );
		$entity->tiers[] = new Tier( 3, 7, 2, Tier::MAX_UNITS, false, 10.0 );
		$entity->rules[] = new Rule( 4, 7, 'ALL', 'ALL', 'ALL', 'AND', 'AND', [], [], [ 15 ], [], [], [ '61:15' ] );

		return $entity;
	}

	/**
	 * Build the entity create_from_request() makes of a body applied over what is stored.
	 *
	 * @param  array<string, mixed>  $payload Request body.
	 * @param  TieredPricing|null   $stored The group as it stands on disk.
	 * @return TieredPricing
	 */
	private function create_from_payload( array $payload, $stored = null ) {
		$service = $this->createMock( TieredPricingService::class );
		$service->method( 'get' )->willReturn( $stored );

		$container = $this->createStub( Container::class );
		$container->method( 'get' )->willReturn( $service );

		$request = new \WP_REST_Request( 'POST', '/alondra/v1/tiered-pricing' );
		$request->set_body( (string) wp_json_encode( $payload ) );

		$method = new \ReflectionMethod( TieredPricingController::class, 'create_from_request' );
		$method->setAccessible( true );

		$this->install_container( $container );

		return $method->invoke( new TieredPricingController(), $request );
	}

	/**
	 * The scenario this exists for. This editor renders no priority, tier type or relationship control,
	 * so it sends a body without them -- and the same rows may have been written by a build that does,
	 * because a merchant moves between builds on one store. Rebuilding the entity from the request alone
	 * blanked every one of those columns, which is a paying customer's configuration gone.
	 */
	public function test_a_save_keeps_every_column_this_build_does_not_render() {
		$entity = $this->create_from_payload(
			[
				'id'     => 7,
				'title'  => 'Renamed',
				'status' => 'publish',
				'tiers'  => [
					[
						'id'          => 3,
						'minQuantity' => 2,
						'value'       => 10,
					],
				],
				'rules'  => [
					[
						'id'       => 4,
						'products' => [ 15, 16 ],
					],
				],
			],
			$this->stored_group()
		);

		$this->assertSame( 'Renamed', $entity->title );
		$this->assertSame( [ 15, 16 ], $entity->rules[0]->products );
		$this->assertSame( 9, $entity->priority, 'the stored priority must survive' );
		$this->assertFalse( $entity->tiers[0]->is_fixed, 'the stored percent tier must survive' );
		$this->assertSame( 'ALL', $entity->rules[0]->cats_rel );
		$this->assertSame( 'ALL', $entity->rules[0]->tags_rel );
		$this->assertSame( 'ALL', $entity->rules[0]->roles_rel );
		$this->assertSame( 'AND', $entity->rules[0]->tags_with_cats_rel );
		$this->assertSame( 'AND', $entity->rules[0]->prods_cats_tags_with_roles_users_rel );
	}

	/**
	 * The editor never sends bundle_products, so a rule updated in place keeps the pairs already stored,
	 * and a rule the payload creates starts without any.
	 */
	public function test_a_rule_updated_in_place_keeps_its_bundle_products() {
		$entity = $this->create_from_payload(
			[
				'id'    => 7,
				'rules' => [
					[
						'id'       => 4,
						'products' => [ 16 ],
					],
					[ 'products' => [ 17 ] ],
				],
			],
			$this->stored_group()
		);

		$this->assertSame( [ '61:15' ], $entity->rules[0]->bundle_products );
		$this->assertSame( [], $entity->rules[1]->bundle_products );
	}

	/**
	 * The same round trip against the real tables, through the REST route the editor calls.
	 */
	public function test_a_rest_update_that_omits_bundle_products_keeps_them_stored() {
		// A freshly installed test database has no WooCommerce capabilities on the administrator role yet.
		$user = self::factory()->user->create_and_get( [ 'role' => 'administrator' ] );
		$user->add_cap( 'manage_woocommerce' );
		wp_set_current_user( $user->ID );

		$service        = Container::instance()->get( TieredPricingService::class );
		$group          = new TieredPricing( 0, 'Bundle pairs', TieredPricing::MIN_PRIORITY, TieredPricing::STATUS_PUBLISH, gmdate( 'Y-m-d H:i:s' ) );
		$group->tiers[] = new Tier( 0, 0, 2, Tier::MAX_UNITS, true, 5.0 );
		$group->rules[] = new Rule( 0, 0, 'ANY', 'ANY', 'ANY', 'OR', 'OR', [], [], [ 15 ], [], [], [ '61:15' ] );
		$saved          = $service->save( $group );
		$this->assertInstanceOf( TieredPricing::class, $saved );

		$request = new \WP_REST_Request( 'PUT', '/alondra/v1/tiered-pricing' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body(
			(string) wp_json_encode(
				[
					'id'    => $saved->id,
					'title' => 'Renamed',
					'rules' => [
						[
							'id'       => $saved->rules[0]->id,
							'products' => [ 16 ],
						],
					],
				]
			)
		);
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );

		$stored = $service->get( $saved->id );
		$this->assertNotNull( $stored );
		$this->assertSame( [ 16 ], $stored->rules[0]->products );
		$this->assertSame( [ '61:15' ], $stored->rules[0]->bundle_products );
	}

	/**
	 * A group being created has nothing stored behind it, so it takes the defaults.
	 */
	public function test_a_create_uses_the_defaults() {
		$entity = $this->create_from_payload(
			[
				'title' => 'New',
				'tiers' => [
					[
						'minQuantity' => 1,
						'value'       => 5,
					],
				],
				'rules' => [ [ 'products' => [ 20 ] ] ],
			]
		);

		$this->assertSame( TieredPricing::MIN_PRIORITY, $entity->priority );
		$this->assertTrue( $entity->tiers[0]->is_fixed );
		$this->assertSame( Rule::RELATIONSHIP_ANY, $entity->rules[0]->cats_rel );
		$this->assertSame( Rule::RELATIONSHIP_OR, $entity->rules[0]->tags_with_cats_rel );
	}

	/**
	 * Omitting the lists entirely is not the same as sending empty ones: a body that says nothing about
	 * the children leaves them alone. Sending a list does make it authoritative.
	 */
	public function test_omitting_the_child_lists_keeps_them_and_sending_one_replaces_it() {
		$stored = $this->stored_group();

		$kept = $this->create_from_payload(
			[
				'id'    => 7,
				'title' => 'Renamed',
			],
			$stored 
		);
		$this->assertSame( $stored->tiers, $kept->tiers );
		$this->assertSame( $stored->rules, $kept->rules );

		$emptied = $this->create_from_payload(
			[
				'id'    => 7,
				'rules' => [],
			],
			$stored 
		);
		$this->assertSame( [], $emptied->rules );
	}

	/**
	 * An id belonging to another group is not an update: honouring it would merge that group's values
	 * in here and then have the datamapper move its row over.
	 */
	public function test_an_id_the_group_does_not_own_is_treated_as_a_new_child() {
		$entity = $this->create_from_payload(
			[
				'id'    => 7,
				'tiers' => [
					[
						'id'          => 999,
						'minQuantity' => 1,
						'value'       => 5,
					],
				],
				'rules' => [
					[
						'id'       => 999,
						'products' => [ 20 ],
					],
				],
			],
			$this->stored_group()
		);

		$this->assertSame( 0, $entity->tiers[0]->id );
		$this->assertSame( 0, $entity->rules[0]->id );
		$this->assertTrue( $entity->tiers[0]->is_fixed, 'the foreign percent tier must not leak in' );
		$this->assertSame( Rule::RELATIONSHIP_ANY, $entity->rules[0]->cats_rel );
	}

	/**
	 * Every key is optional at the boundary, because a client that omits one is asking to keep what is
	 * stored. Requiring them is what makes a trimmed editor unable to save at all.
	 */
	public function test_the_boundary_accepts_a_payload_that_omits_optional_keys() {
		$controller = new TieredPricingController();
		$args       = $controller->add_tiered_pricing_args();

		$this->assertTrue( (bool) \call_user_func( $args['rules']['validate_callback'], [ [ 'products' => [ 15 ] ] ], null, 'rules' ) );
		$this->assertTrue( (bool) \call_user_func( $args['tiers']['validate_callback'], [ [ 'minQuantity' => 1 ] ], null, 'tiers' ) );
		$this->assertFalse( (bool) \call_user_func( $args['rules']['validate_callback'], [ [ 'products' => 'nope' ] ], null, 'rules' ) );
		$this->assertFalse( $controller->update_tiered_pricing_args()['title']['required'] );
	}

	/**
	 * A subclass extends the save through the payload seams and the response through entity_dto(),
	 * without touching the route callback.
	 */
	public function test_a_subclass_seam_receives_the_payload_and_shapes_what_is_saved_and_returned() {
		$saved   = null;
		$service = $this->createMock( TieredPricingService::class );
		$service->method( 'get' )->willReturn( $this->stored_group() );
		$service->method( 'save' )->willReturnCallback(
			function ( $entity ) use ( &$saved ) {
				$saved = $entity;
				return $entity;
			}
		);
		$service->method( 'make_dto' )->willReturn( new TieredPricingDto( 7, 'Renamed' ) );

		$container = $this->createStub( Container::class );
		$container->method( 'get' )->willReturn( $service );
		$this->install_container( $container );

		$controller = new class() extends TieredPricingController {
			public array $tier_payloads = [];
			public array $rule_payloads = [];
			public ?Tier $tier          = null;
			public ?Rule $rule          = null;

			protected function tier_from_payload( array $payload, Tier $base, int $id ): Tier {
				$this->tier_payloads[] = $payload;
				$this->tier            = new Tier( $base->id, $id, 1, Tier::MAX_UNITS, (bool) $payload['type'], 3.0 );
				return $this->tier;
			}

			protected function rule_from_payload( array $payload, Rule $base, int $id ): Rule {
				$this->rule_payloads[] = $payload;
				$this->rule            = new Rule( $base->id, $id, $payload['rolesRel'] );
				return $this->rule;
			}

			protected function entity_dto( TieredPricing $entity ): array {
				return parent::entity_dto( $entity ) + [ 'priority' => $entity->priority ];
			}
		};

		$tier    = [
			'id'    => 3,
			'type'  => 0,
			'value' => 3,
		];
		$rule    = [
			'id'       => 4,
			'rolesRel' => 'ALL',
		];
		$request = new \WP_REST_Request( 'PUT', '/alondra/v1/tiered-pricing' );
		$request->set_body(
			(string) wp_json_encode(
				[
					'id'    => 7,
					'tiers' => [ $tier ],
					'rules' => [ $rule ],
				]
			)
		);

		$response = $controller->update_tiered_pricing( $request );

		$this->assertSame( [ $tier ], $controller->tier_payloads );
		$this->assertSame( [ $rule ], $controller->rule_payloads );
		$this->assertInstanceOf( TieredPricing::class, $saved );
		$this->assertSame( [ $controller->tier ], $saved->tiers );
		$this->assertSame( [ $controller->rule ], $saved->rules );
		$this->assertSame( 3, $saved->tiers[0]->id, 'the base handed to the seam is the stored tier' );
		$this->assertSame( 'Renamed', $response->get_data()['title'] );
		$this->assertSame( 9, $response->get_data()['priority'] );
	}

	/**
	 * The editor's first load goes through the same entity_dto() seam as the REST response.
	 */
	public function test_the_editor_receives_the_group_through_entity_dto() {
		$user = self::factory()->user->create_and_get( [ 'role' => 'administrator' ] );
		$user->add_cap( 'manage_woocommerce' );
		wp_set_current_user( $user->ID );

		$service = $this->createMock( TieredPricingService::class );
		$service->method( 'get' )->willReturn( $this->stored_group() );
		$service->method( 'make_dto' )->willReturn( new TieredPricingDto( 7, 'Stored' ) );

		$config    = Container::instance()->get( PluginInfo::class );
		$container = $this->createStub( Container::class );
		$container->method( 'get' )->willReturnMap(
			[
				[ TieredPricingService::class, $service ],
				[ PluginInfo::class, $config ],
			]
		);
		$this->install_container( $container );

		$controller = new class() extends TieredPricingController {
			protected function entity_dto( TieredPricing $entity ): array {
				return parent::entity_dto( $entity ) + [ 'priority' => $entity->priority ];
			}

			protected function show_edit_form() {
			}
		};

		wp_register_script( AssetController::HANDLE_ADMIN, 'admin.js', [], '1', true );
		$_GET['action'] = TieredPricingController::ACTION_EDIT;
		$_GET['id']     = '7';
		try {
			$controller->show_content( 'alondra_tiered_pricing' );
			$localized = (string) wp_scripts()->get_data( AssetController::HANDLE_ADMIN, 'data' );
		} finally {
			unset( $_GET['action'], $_GET['id'] );
			wp_deregister_script( AssetController::HANDLE_ADMIN );
		}

		$this->assertStringContainsString( '"entity":{"id":7,"title":"Stored"', $localized );
		$this->assertStringContainsString( '"priority":9', $localized );
	}

	private function request( string $action, ?string $nonce ) {
		$_REQUEST['action'] = $action;
		$_REQUEST['id']     = (string) self::ITEM_ID;
		if ( null === $nonce ) {
			unset( $_REQUEST['_wpnonce'] );
			return;
		}
		$_REQUEST['_wpnonce'] = $nonce;
	}

	private function get_instance() {
		$this->pricing_service = $this->createMock( TieredPricingService::class );

		$container = $this->createStub( Container::class );
		$container->method( 'get' )->willReturnMap(
			[
				[ TieredPricingService::class, $this->pricing_service ],
			]
		);

		$this->install_container( $container );

		return new class() extends TieredPricingController {
			public function run_current_action( ListTable $table ) {
				$this->manage_current_action( $table );
			}
		};
	}

	private function run_current_action( $controller ) {
		$adapter = $this->createMock( TieredPricingListTableAdapter::class );
		$adapter->method( 'get_args_for_constructor' )->willReturn(
			[
				'plural'   => 'tiered_pricings',
				'singular' => 'tiered_pricing',
				'ajax'     => false,
				'screen'   => 'toplevel_page_alondra',
			]
		);

		ob_start();
		$controller->run_current_action( new ListTable( $adapter ) );
		ob_get_clean();
	}
}
