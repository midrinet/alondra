<?php
/**
 * Stubs for the WooCommerce Product Bundles symbols Alondra reads.
 *
 * PB is not on the analysis path, so PHPStan level 9 cannot see these otherwise.
 * Referenced from phpstan.neon via `scanFiles`; never analysed itself.
 *
 * @package Alondra
 */

/**
 * Product bundle.
 */
class WC_Product_Bundle extends WC_Product {

	/**
	 * Getter of bundle 'contains' properties.
	 *
	 * @param  string $key Property key.
	 * @return mixed
	 */
	public function contains( $key ) {}
}

/**
 * Bundled item.
 */
class WC_Bundled_Item {

	/**
	 * Parent bundle.
	 *
	 * @return WC_Product_Bundle|false
	 */
	public function get_bundle() {}
}

/**
 * Bundled product price filters.
 */
class WC_PB_Product_Prices {

	/**
	 * The bundled item altering a product's prices, if any.
	 *
	 * @param  WC_Product $product Product to check.
	 * @param  string     $context 'any' | 'catalog' | 'cart'.
	 * @return WC_Bundled_Item|false
	 */
	public static function get_filtered_bundled_item( $product, $context = 'any' ) {}
}
