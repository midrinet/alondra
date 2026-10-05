<?php
/**
 * Empty cart task
 *
 * @package Alondra/Helper
 */

namespace Midrinet\Alondra\Helper\Task;

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

/**
 * Empties every WooCommerce cart so test runs do not accumulate items.
 */
class EmptyCartTask extends Task {

	/**
	 * Execute the task
	 *
	 * @throws \Exception If the task fails
	 */
	public function execute( array $args = [] ): void {
		global $wpdb;
		// Persistent carts live in user meta and are restored on login; remove them for every user.
		$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '_woocommerce_persistent_cart_%'" );
		// Clear all active WooCommerce session carts.
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}woocommerce_sessions" );
	}
}
