<?php
/**
 * Set product categories task
 *
 * @package Alondra/Helper
 */

namespace Midrinet\Alondra\Helper\Task;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * Assigns WooCommerce product_cat terms to a product, so tests can exercise
 * category-based rule matching against a product without the needed category
 * combination in the seed catalog.
 */
class SetProductCategoriesTask extends Task {

	/**
	 * Execute the task
	 *
	 * @param array<string, mixed> $args Webhook request args. `product_id` is the product
	 *                                   post ID; `categories` is a JSON array of category names.
	 * @throws \Exception If the arguments are missing/invalid or the assignment fails.
	 */
	public function execute( array $args = [] ): void {
		if ( ! isset( $args['product_id'] ) ) {
			throw new \Exception( 'Missing "product_id" argument', 400 );
		}

		$categories = $this->get_json_array_arg( $args, 'categories' );

		$result = wp_set_object_terms( (int) $args['product_id'], $categories, 'product_cat' );
		if ( is_wp_error( $result ) ) {
			throw new \Exception( 'Failed to set product categories: ' . $result->get_error_message(), 500 );
		}
	}
}
