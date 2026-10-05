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
class ClearDataTask extends Task {

	/**
	 * Execute the task
	 * 
	 * @throws \Exception If the task fails
	 */
	public function execute( array $args = [] ): void {
		global $wpdb;

		// DELETE, not TRUNCATE: InnoDB refuses to truncate a table another table references, so on a
		// database still carrying the child tables' foreign keys -- a restored pre-1.0.3 dump, a fixture
		// that has not run the migrations -- the parent survived the clear and nothing said so. Children
		// first, and loud on failure: a half-cleared database makes the next spec fail somewhere else.
		$tables = [
			$this->get_rules_table_name(),
			$this->get_tiers_table_name(),
			$this->get_tiered_pricing_table_name(),
		];

		foreach ( $tables as $table ) {
			if ( false === $wpdb->query( "DELETE FROM {$table}" ) ) {
				throw new \Exception( "Could not clear {$table}: {$wpdb->last_error}", 500 );
			}

			// DELETE leaves the counter where TRUNCATE would have reset it, and the specs address rows by
			// id -- a listing locator on value="1", a webhook on id=1 -- so a cleared table has to hand
			// out 1 again.
			if ( false === $wpdb->query( "ALTER TABLE {$table} AUTO_INCREMENT = 1" ) ) {
				throw new \Exception( "Could not reset the ids of {$table}: {$wpdb->last_error}", 500 );
			}
		}

		$this->flush_alondra_cache();
	}
}
