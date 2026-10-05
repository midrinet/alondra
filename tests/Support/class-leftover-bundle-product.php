<?php
/**
 * Leftover bundle product
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Support;

use WC_Product;

/**
 * A product reporting type 'bundle' without being one of Product Bundles' own.
 *
 * WC_Product_Factory instantiates whatever `woocommerce_product_class` resolves to, and every
 * product class declares its type by overriding get_type() exactly like this.
 */
class Leftover_Bundle_Product extends WC_Product {

	/**
	 * @return string
	 */
	public function get_type() {
		return 'bundle';
	}
}
