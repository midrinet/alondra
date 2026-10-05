<?php
/**
 * Task class
 * 
 * @package Alondra/Helper
 */

namespace Midrinet\Alondra\Helper\Task;

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * Task class
 */
class CreateSampleTieredPricingTask extends Task {

	/**
	 * Execute the task
	 * 
	 * @throws \Exception If the task fails
	 */
	public function execute( array $args = [] ): void {
		global $wpdb;

		// Resolved up front so a store that was never seeded fails here, naming what is missing,
		// rather than writing a pricing group whose rules point at nothing.
		$on_sale    = $this->product_id_by_sku( 'CDL-KG' );
		$child      = $this->product_id_by_sku( 'YRG-250' );
		$variation  = $this->product_id_by_sku( 'CSC-KG' );
		$tag        = $this->term_id_by_slug( 'single-origin', 'product_tag' );
		$parent_cat = $this->term_id_by_slug( 'equipment', 'product_cat' );
		$sub_cat    = $this->term_id_by_slug( 'grinders', 'product_cat' );
		$admin      = $this->user_id_by_login( 'admin' );

		// Create sample tiered pricing.
		$result = $wpdb->insert(
			$this->get_tiered_pricing_table_name(),
			[
				'title'        => 'Sample Tiered Pricing',
				'priority'     => 1,
				'date_updated' => '2025-01-19 14:24:50',
				'status'       => 'publish',
			]
		);

		if ( false === $result ) {
			throw new \Exception( 'Failed to create sample tiered pricing', 500 );
		}

		// Get the ID of the newly created tiered pricing.
		$id = $wpdb->insert_id;

		// Create sample rules.
		$rules = [
			[
				'tiered_pricing_id'                    => $id,
				'product_id'                           => maybe_serialize( [ $on_sale ] ),
				'tag_id'                               => maybe_serialize( [ $tag ] ),
				'cat_id'                               => maybe_serialize( [] ),
				'user_id'                              => maybe_serialize( [ $admin ] ),
				'role'                                 => maybe_serialize( [ 'administrator' ] ),
				'roles_rel'                            => 'ANY',
				'tags_rel'                             => 'ANY',
				'cats_rel'                             => 'ANY',
				'tags_with_cats_rel'                   => 'OR',
				'prods_cats_tags_with_roles_users_rel' => 'OR',
			],
			[
				'tiered_pricing_id'                    => $id,
				'product_id'                           => maybe_serialize( [ $child, $variation ] ),
				'tag_id'                               => maybe_serialize( [] ),
				'cat_id'                               => maybe_serialize( [ $parent_cat, $sub_cat ] ),
				'user_id'                              => maybe_serialize( [] ),
				'role'                                 => maybe_serialize( [ 'author', 'shop_manager' ] ),
				'roles_rel'                            => 'ANY',
				'tags_rel'                             => 'ANY',
				'cats_rel'                             => 'ANY',
				'tags_with_cats_rel'                   => 'OR',
				'prods_cats_tags_with_roles_users_rel' => 'OR',
			],
		];

		// wpdb::$field_types maps the column names `user_id` and `cat_id` to %d, so without an
		// explicit format the serialized arrays those two hold are cast to the integer 0.
		$rule_format = [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ];

		foreach ( $rules as $rule ) {
			$result = $wpdb->insert( $this->get_rules_table_name(), $rule, $rule_format );

			if ( false === $result ) {
				throw new \Exception( 'Failed to create sample rules', 500 );
			}
		}

		// Create sample tiers.
		$tiers = [
			[
				'tiered_pricing_id' => $id,
				'min_units'         => 1,
				'max_units'         => 10,
				'is_fixed'          => 1,
				'value'             => 50.0,
			],
			[
				'tiered_pricing_id' => $id,
				'min_units'         => 11,
				'max_units'         => 4294967295,
				'is_fixed'          => 1,
				'value'             => 60.0,
			],
		];

		foreach ( $tiers as $tier ) {
			$result = $wpdb->insert( $this->get_tiers_table_name(), $tier );

			if ( false === $result ) {
				throw new \Exception( 'Failed to create sample tiers', 500 );
			}
		}

		$this->flush_alondra_cache();
	}

	/**
	 * Resolve a seeded product or variation by its SKU.
	 *
	 * @param string $sku Product SKU.
	 * @throws \Exception If the demo catalog has not been seeded.
	 * @return int
	 */
	private function product_id_by_sku( string $sku ): int {
		$product_id = (int) wc_get_product_id_by_sku( $sku );

		if ( 0 === $product_id ) {
			throw new \Exception( "Demo catalog SKU \"$sku\" not found; seed the store with scripts/setup", 500 );
		}

		return $product_id;
	}

	/**
	 * Resolve a seeded term by its slug.
	 *
	 * @param string $slug     Term slug.
	 * @param string $taxonomy Taxonomy the term belongs to.
	 * @throws \Exception If the demo catalog has not been seeded.
	 * @return int
	 */
	private function term_id_by_slug( string $slug, string $taxonomy ): int {
		$term = get_term_by( 'slug', $slug, $taxonomy );

		if ( ! $term instanceof \WP_Term ) {
			throw new \Exception( "Demo catalog $taxonomy term \"$slug\" not found; seed the store with scripts/setup", 500 );
		}

		return $term->term_id;
	}

	/**
	 * Resolve a user by their login.
	 *
	 * @param string $login User login.
	 * @throws \Exception If the user does not exist.
	 * @return int
	 */
	private function user_id_by_login( string $login ): int {
		$user = get_user_by( 'login', $login );

		if ( ! $user instanceof \WP_User ) {
			throw new \Exception( "User \"$login\" not found", 500 );
		}

		return $user->ID;
	}
}
