<?php
/**
 * TieredPricingService Tests
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Services;

use Midrinet\Alondra\Application\Service\PreferencesService;
use Midrinet\Alondra\Application\Service\TieredPricingService;
use Midrinet\Alondra\Domain\Entity\Tier;
use Midrinet\Alondra\Domain\Entity\TieredPricing;
use Midrinet\Alondra\Domain\Repository\TieredPricingRepo;
use Midrinet\Alondra\Infrastructure\DI\Container;
use Midrinet\Alondra\Infrastructure\Migration\MigrationRunner;
use Midrinet\Alondra\Tests\Support\Container_Seam;
use WP_UnitTestCase;

require_once __DIR__ . '/../Support/trait-container-seam.php';

/**
 * Covers the reference price the tier table strikes through. A product may
 * already carry a native WooCommerce sale, and the shopper must see the full
 * saving against the original regular price rather than against the sale one.
 */
class TieredPricingServiceTest extends WP_UnitTestCase {

	use Container_Seam;

	/**
	 * Build a service whose repo matches one group with $tiers for any product.
	 *
	 * @param Tier ...$tiers Tiers of the group the repo should return.
	 * @return TieredPricingService
	 */
	private function make_service( Tier ...$tiers ) {
		$group = new TieredPricing( 1, 'Rule', 1, TieredPricing::STATUS_PUBLISH, '', $tiers );
		$repo  = $this->createStub( TieredPricingRepo::class );
		$repo->method( 'find_all_matching' )->willReturn( [ $group ] );
		$repo->method( 'find_matching' )->willReturn( $group );

		$migrations = $this->createStub( MigrationRunner::class );
		$migrations->method( 'is_applied' )->willReturn( true );

		// The service asks the container for both, so the stub has to answer by key rather than
		// hand the repo back for everything.
		$container = $this->createStub( Container::class );
		$container->method( 'get' )->willReturnMap(
			[
				[ TieredPricingRepo::class, $repo ],
				[ MigrationRunner::class, $migrations ],
				[ PreferencesService::class, new PreferencesService() ],
			]
		);

		$this->install_container( $container );

		return new TieredPricingService();
	}

	/**
	 * A product on native sale (regular 65, sale 55): the tier is struck through
	 * against 65, so the shopper sees the whole discount and not just the tier's
	 * share of it.
	 */
	public function test_tiers_carry_the_regular_price() {
		$tier  = new Tier( 1, 1, 1, Tier::MAX_UNITS, true, 15 );
		$tiers = $this->make_service( $tier )->get_tiers( 1, 0, 55.0, 65.0 );

		$this->assertCount( 1, $tiers );
		$this->assertSame( 65.0, $tiers[0]->regular_price );
		$this->assertSame( 15.0, $tiers[0]->price );
	}

	/**
	 * Omitting the regular price keeps the active price as the reference, so
	 * callers that do not care about the strikethrough are unaffected.
	 */
	public function test_tiers_fall_back_to_the_active_price() {
		$tier  = new Tier( 1, 1, 1, Tier::MAX_UNITS, true, 15 );
		$tiers = $this->make_service( $tier )->get_tiers( 1, 0, 55.0 );

		$this->assertSame( 55.0, $tiers[0]->regular_price );
	}

	/**
	 * The percent label sits next to the strikethrough, so it has to be derived from
	 * the same regular price. Against the active one a tier of 60 on a 65/55 sale
	 * yields 110, which the template renders as a negative "-10% off".
	 */
	public function test_percent_is_derived_from_the_regular_price() {
		$tier  = new Tier( 1, 1, 1, Tier::MAX_UNITS, true, 60 );
		$tiers = $this->make_service( $tier )->get_tiers( 1, 0, 55.0, 65.0 );

		$this->assertSame( 93.0, $tiers[0]->percent );
		$this->assertSame( '7%', $tiers[0]->get_formatted_percent() );
	}

	/**
	 * With no listener the cart takes the tier's fixed value.
	 */
	public function test_cart_takes_the_fixed_tier_price() {
		$tier = new Tier( 1, 1, 1, Tier::MAX_UNITS, true, 15 );

		$this->assertSame( 15.0, $this->make_service( $tier )->get_tiered_price( 1, 3, 0, 55.0 ) );
	}

	/**
	 * A listener reprices the cart and the tier table alike, and gets the tier and the active price.
	 */
	public function test_cart_and_table_agree_under_a_listener() {
		$low     = new Tier( 1, 1, 1, 9, true, 15 );
		$high    = new Tier( 2, 1, 10, Tier::MAX_UNITS, true, 10 );
		$service = $this->make_service( $low, $high );
		$seen    = [];
		add_filter(
			'alondra_tier_price',
			function ( $price, $tier, $basis ) use ( &$seen ) {
				$seen[] = [ $tier, $basis ];
				return $price / 2;
			},
			10,
			3
		);

		$this->assertSame( 7.5, $service->get_tiered_price( 1, 5, 0, 55.0 ) );
		$this->assertSame( 5.0, $service->get_tiered_price( 1, 10, 0, 55.0 ) );
		$this->assertSame( [ $low, 55.0 ], $seen[0] );

		$tiers = $service->get_tiers( 1, 0, 55.0, 65.0 );
		$this->assertSame( [ 7.5, 5.0 ], array_column( $tiers, 'price' ) );
		$this->assertSame( [ $high, 55.0 ], end( $seen ) );
	}

	/**
	 * Null declines the tier: the cart keeps its active price and the table drops the row.
	 */
	public function test_null_declines_the_tier() {
		$low     = new Tier( 1, 1, 1, 9, true, 15 );
		$high    = new Tier( 2, 1, 10, Tier::MAX_UNITS, true, 10 );
		$service = $this->make_service( $low, $high );
		add_filter(
			'alondra_tier_price',
			function ( $price, $tier ) use ( $low ) {
				return $tier === $low ? null : $price;
			},
			10,
			2
		);

		$this->assertSame( 55.0, $service->get_tiered_price( 1, 5, 0, 55.0 ) );
		$this->assertSame( 10.0, $service->get_tiered_price( 1, 10, 0, 55.0 ) );

		$tiers = $service->get_tiers( 1, 0, 55.0 );
		$this->assertCount( 1, $tiers );
		$this->assertSame( 10, $tiers[0]->min_units );
	}

	/**
	 * Filter output free cannot use as a price.
	 *
	 * @return array<string, array{mixed}>
	 */
	public function invalid_prices() {
		return [
			'numeric string' => [ '5' ],
			'negative'       => [ -1.0 ],
			'array'          => [ [ 5.0 ] ],
			'NAN'            => [ NAN ],
			'INF'            => [ INF ],
		];
	}

	/**
	 * Invalid output falls back to free's price in the cart and in the table.
	 *
	 * @dataProvider invalid_prices
	 *
	 * @param mixed $invalid Listener output.
	 */
	public function test_invalid_output_falls_back( $invalid ) {
		$service = $this->make_service( new Tier( 1, 1, 1, Tier::MAX_UNITS, true, 15 ) );
		add_filter(
			'alondra_tier_price',
			function () use ( $invalid ) {
				return $invalid;
			}
		);

		$this->assertSame( 15.0, $service->get_tiered_price( 1, 3, 0, 55.0 ) );
		$this->assertSame( 15.0, $service->get_tiers( 1, 0, 55.0 )[0]->price );
	}
}
