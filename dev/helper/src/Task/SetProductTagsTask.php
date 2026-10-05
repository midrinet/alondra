<?php
/**
 * Set product tags task
 *
 * @package Alondra/Helper
 */

namespace Midrinet\Alondra\Helper\Task;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * Assigns WooCommerce product_tag terms to a product, so tests can exercise
 * tag-based rule matching against a product without a tag in the seed catalog.
 */
class SetProductTagsTask extends Task {

	/**
	 * Execute the task
	 *
	 * @param array<string, mixed> $args Webhook request args. `product_id` is the product
	 *                                   post ID; `tags` is a JSON array of tag names.
	 * @throws \Exception If the arguments are missing/invalid or the assignment fails.
	 */
	public function execute( array $args = [] ): void {
		if ( ! isset( $args['product_id'] ) ) {
			throw new \Exception( 'Missing "product_id" argument', 400 );
		}

		$tags = $this->get_json_array_arg( $args, 'tags' );

		$result = wp_set_object_terms( (int) $args['product_id'], $tags, 'product_tag' );
		if ( is_wp_error( $result ) ) {
			throw new \Exception( 'Failed to set product tags: ' . $result->get_error_message(), 500 );
		}
	}
}
