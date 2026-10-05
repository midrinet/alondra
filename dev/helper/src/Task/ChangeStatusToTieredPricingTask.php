<?php
/**
 * Task class
 * 
 * @package Midrinet/Alondra
 */

namespace Midrinet\Alondra\Helper\Task;

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * Change status to tiered pricing given the ID and the new status (trash, publish or draft).
 */
class ChangeStatusToTieredPricingTask extends Task {

	/**
	 * Execute the task
	 * 
	 * @throws \Exception If the task fails
	 */
	public function execute( array $args = [] ): void {
		$id         = (int) $args['id'];
		$new_status = $args['status'];
		
		global $wpdb;
		// Update the status for the given tiered pricing ID.
		$result = $wpdb->update(
			$this->get_tiered_pricing_table_name(),
			[ 'status' => $new_status ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);

		if ( ! (int) $result ) {
			throw new \Exception( 'Failed to update the status for the given tiered pricing ID.', 500 );
		}

		$this->flush_alondra_cache();
	}
}
