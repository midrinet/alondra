<?php
/**
 * ProductRepo Tests
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Repositories;

use Midrinet\Alondra\Domain\Repository\ProductRepo;
use WP_UnitTestCase;

class ProductRepoTest extends WP_UnitTestCase {

	public function test_a_product_without_a_sku_is_returned_with_an_empty_sku() {
		$product_id = self::factory()->post->create( [ 'post_type' => 'product' ] );

		$results = ( new ProductRepo() )->get_products( (string) $product_id );

		$this->assertCount( 1, $results, 'A product with no SKU must still be found by its ID.' );
		$this->assertSame( '', $results[0]->sku, 'A product with no SKU must map to an empty string, not null.' );
	}
}
