<?php
/**
 * PricingLayoutViewFactory Tests
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Views;

use Midrinet\Alondra\Application\Dto\SimpleTierDto;
use Midrinet\Alondra\Application\Service\PreferencesService;
use Midrinet\Alondra\Application\Service\TieredPricingService;
use Midrinet\Alondra\Infrastructure\Controller\AssetController;
use Midrinet\Alondra\Infrastructure\Controller\TieredPricingController;
use Midrinet\Alondra\Infrastructure\DI\Container;
use Midrinet\Alondra\Infrastructure\View\Front\PricingLayoutView;
use Midrinet\Alondra\Infrastructure\View\Front\PricingLayoutViewFactory;
use Midrinet\Alondra\Infrastructure\View\Front\TableView;
use Midrinet\Alondra\Tests\Support\Abstract_Layout_View;
use Midrinet\Alondra\Tests\Support\Container_Seam;
use WP_UnitTestCase;

require_once __DIR__ . '/../Support/class-abstract-layout-view.php';
require_once __DIR__ . '/../Support/trait-container-seam.php';

/**
 * A layout id resolves to its registered view, anything else to the table, and the stored id is never rewritten.
 */
class PricingLayoutViewFactoryTest extends WP_UnitTestCase {

	use Container_Seam;

	public function tear_down() {
		remove_all_filters( 'alondra_pricing_layout_views' );
		delete_option( PreferencesService::PREF_OPTION );
		parent::tear_down();
	}

	private function register( string $id, string $class_name ): void {
		add_filter(
			'alondra_pricing_layout_views',
			function ( $views ) use ( $id, $class_name ) {
				$views[ $id ] = $class_name;
				return $views;
			}
		);
	}

	public function test_default_id_is_the_table() {
		$this->assertSame( TableView::class, \get_class( ( new PricingLayoutViewFactory() )->create( 'table' ) ) );
	}

	public function test_unknown_id_falls_back_to_the_table() {
		$this->assertSame( TableView::class, \get_class( ( new PricingLayoutViewFactory() )->create( 'pills' ) ) );
	}

	public function test_registered_subclass_resolves() {
		$pills = new class() extends PricingLayoutView {
			public function render( array $tiers, string $is_clickable_class ): string {
				return 'pills';
			}
		};
		$this->register( 'pills', \get_class( $pills ) );

		$this->assertInstanceOf( \get_class( $pills ), ( new PricingLayoutViewFactory() )->create( 'pills' ) );
	}

	/**
	 * @dataProvider provide_invalid_entries
	 *
	 * @param mixed $entry Filter entry.
	 */
	public function test_entry_that_is_not_a_layout_view_is_ignored( $entry ) {
		add_filter(
			'alondra_pricing_layout_views',
			function ( $views ) use ( $entry ) {
				$views['pills'] = $entry;
				return $views;
			}
		);

		$this->assertSame( TableView::class, \get_class( ( new PricingLayoutViewFactory() )->create( 'pills' ) ) );
	}

	public function provide_invalid_entries(): array {
		return [
			'unrelated class' => [ \stdClass::class ],
			'missing class'   => [ 'Nope\\Missing' ],
			'the base itself' => [ PricingLayoutView::class ],
			'not a string'    => [ 42 ],
			'an instance'     => [ new TableView() ],
		];
	}

	public function test_an_abstract_view_falls_back_to_the_table() {
		$this->register( 'pills', Abstract_Layout_View::class );

		$this->assertSame( TableView::class, \get_class( ( new PricingLayoutViewFactory() )->create( 'pills' ) ) );
	}

	public function test_a_view_needing_constructor_arguments_falls_back_to_the_table() {
		$needy = new class( 'x' ) extends PricingLayoutView {
			public function __construct( string $required ) {
			}

			public function render( array $tiers, string $is_clickable_class ): string {
				return 'needy';
			}
		};
		$this->register( 'pills', \get_class( $needy ) );

		$this->assertSame( TableView::class, \get_class( ( new PricingLayoutViewFactory() )->create( 'pills' ) ) );
	}

	public function test_filter_returning_non_array_falls_back_to_the_table() {
		add_filter( 'alondra_pricing_layout_views', '__return_false' );

		$this->assertSame( TableView::class, \get_class( ( new PricingLayoutViewFactory() )->create( 'table' ) ) );
	}

	public function test_each_call_gives_a_fresh_instance() {
		$factory = new PricingLayoutViewFactory();

		$this->assertNotSame( $factory->create( 'table' ), $factory->create( 'table' ) );
	}

	public function test_stored_pills_renders_the_table_and_keeps_the_option() {
		$stored = [
			PreferencesService::PREF_PRICING_LAYOUT => 'pills',
			PreferencesService::PREF_COLOR          => '#123456',
		];
		update_option( PreferencesService::PREF_OPTION, $stored );

		$tiers   = [ new SimpleTierDto( 1, 9, 10, 12 ) ];
		$service = $this->createStub( TieredPricingService::class );
		$service->method( 'get_tiers' )->willReturn( $tiers );

		$container = $this->createStub( Container::class );
		$container->method( 'get' )->willReturnMap(
			[
				[ TieredPricingService::class, $service ],
				[ PreferencesService::class, new PreferencesService() ],
				[ PricingLayoutViewFactory::class, new PricingLayoutViewFactory() ],
			]
		);
		$this->install_container( $container );

		$controller = new class() extends TieredPricingController {
			public function render_tiers( \WC_Product $product ): string {
				return $this->tiers_html( $product );
			}
		};

		$this->assertSame(
			( new TableView() )->render( $tiers, AssetController::IS_CLICKABLE_CLASS ),
			$controller->render_tiers( new \WC_Product_Simple() )
		);
		$this->assertSame( $stored, get_option( PreferencesService::PREF_OPTION ) );
	}
}
