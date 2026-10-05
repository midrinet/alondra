<?php
/**
 * Custom methods for accessing WooCommerce products
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Domain/Repository
 */

namespace Midrinet\Alondra\Domain\Repository;

use Midrinet\Alondra\Domain\Datamapper\ProductResultDatamapper;
use Midrinet\Alondra\Domain\Entity\ProductResult;
use Midrinet\Alondra\Infrastructure\DI\Container;

/**
 * Custom methods for accessing WooCommerce products
 *
 * @since      1.0.0
 */
class ProductRepo {

	/**
	 * Get Products matching search term.
	 *
	 * @param string $search Optional. Substring to search for. Is compared against product title, ID and SKU.
	 * @param int[]  $exclude Optional. Array of product IDs to exclude from results.
	 * @param int    $limit Optional. Max number of results. Default 5.
	 * @return ProductResult[]
	 */
	public function get_products( $search = '', $exclude = [], $limit = 5 ) {
		$wpdb       = Container::instance()->get( \wpdb::class );
		$datamapper = Container::instance()->get( ProductResultDatamapper::class );
		$search     = sanitize_text_field( $search );

		// An empty search must not filter anything, so it degrades to the LIKE wildcard.
		$like = '' === $search ? '%' : '%' . $wpdb->esc_like( $search ) . '%';

		// FIND_IN_SET binds the whole exclude list as one value. NOT IN would need a
		// placeholder per id, and the count is only known at runtime, which is what
		// forces a query built by concatenation. An empty set excludes nothing.
		$excluded = implode( ',', array_map( 'absint', \is_array( $exclude ) ? $exclude : [] ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- reads posts for the rule editor's product search, which must not serve stale rows.
		$raw_results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT `ID` AS %i,
				`post_title` AS %i,
				`post_parent` AS %i,
				(SELECT `meta_value` FROM %i WHERE `meta_key` = %s AND `post_id` = `ID` LIMIT 1) AS %i,
				(SELECT 1 FROM %i WHERE `post_parent` = %i AND `post_type` = %s AND `post_status` NOT IN (%s,%s,%s,%s,%s) LIMIT 1) AS %i
				FROM %i
				WHERE `post_type` IN ('product', 'product_variation')
				AND `post_status` NOT IN (%s,%s,%s,%s,%s)
				AND (`post_title` LIKE %s OR `ID` = %d OR EXISTS(SELECT `post_id` FROM %i WHERE `meta_key` = %s AND `post_id` = `ID` AND `meta_value` LIKE %s LIMIT 1))
				AND NOT FIND_IN_SET(`ID`, %s)
				AND NOT FIND_IN_SET(`post_parent`, %s)
				LIMIT %d",
				$datamapper->col_id(),
				$datamapper->col_title(),
				$datamapper->col_parent_id(),
				$wpdb->postmeta,
				'_sku',
				$datamapper->col_sku(),
				$wpdb->posts,
				$datamapper->col_id(),
				'product_variation',
				'auto-draft',
				'spam',
				'future',
				'draft',
				'trash',
				$datamapper->col_is_variable(),
				$wpdb->posts,
				'auto-draft',
				'spam',
				'future',
				'draft',
				'trash',
				$like,
				(int) $search,
				$wpdb->postmeta,
				'_sku',
				$like,
				$excluded,
				$excluded,
				(int) $limit
			)
		);

		return $datamapper->map_all( $raw_results ?? [] );
	}
}
