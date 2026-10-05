<?php
/**
 * Bundle fixture
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Support;

use WC_Install;
use WC_Product_Bundle;

/**
 * Builds real Product Bundles fixtures and tears them down again.
 */
trait Bundle_Fixture {

	/**
	 * Skip the whole case when Product Bundles is not loaded.
	 *
	 * @return void
	 */
	protected function skip_without_bundles() {
		if ( ! class_exists( 'WC_Product_Bundle' ) ) {
			$this->markTestSkipped( 'WooCommerce Product Bundles is not loaded (set ALONDRA_LOAD_PB=1).' );
		}
	}

	/**
	 * Build a saved bundle out of the given child products.
	 *
	 * Pass $priced_individually false with a $regular_price for a static bundle, or true with no
	 * price for a per-item bundle.
	 *
	 * @param  int[]  $child_ids           Product ids to bundle.
	 * @param  bool   $priced_individually Whether the children carry their own prices.
	 * @param  string $regular_price       Container regular price.
	 * @return WC_Product_Bundle
	 */
	protected function make_bundle( array $child_ids, $priced_individually, $regular_price = '' ) {
		// _delete_all_data() drops the product_visibility terms between test classes, and the bundle data store writes 'outofstock' on every save.
		WC_Install::create_terms();

		$items = [];
		foreach ( array_values( $child_ids ) as $menu_order => $child_id ) {
			$items[] = [
				'bundled_item_id' => 0,
				'product_id'      => $child_id,
				'menu_order'      => $menu_order,
				'meta_data'       => [
					'quantity_min'         => 1,
					'quantity_max'         => 1,
					'quantity_default'     => 1,
					'priced_individually'  => $priced_individually ? 'yes' : 'no',
					'shipped_individually' => 'no',
					'optional'             => 'no',
					'discount'             => null,
				],
			];
		}

		$bundle = new WC_Product_Bundle();
		$bundle->set_name( 'Alondra test bundle' );
		$bundle->set_regular_price( $priced_individually ? '' : $regular_price );
		$bundle->set_price( $priced_individually ? '' : $regular_price );
		$bundle->set_bundled_data_items( $items );
		$bundle->save();

		// Bundled item ids are temporary until save(), and contains() memoises: only a re-read is trustworthy.
		return wc_get_product( $bundle->get_id() );
	}

	/**
	 * Drop the bundled item rows. Product Bundles leaves them behind when the bundle post goes.
	 *
	 * @return void
	 */
	protected function delete_bundles() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$wpdb->prefix}woocommerce_bundled_itemmeta" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}woocommerce_bundled_items" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
