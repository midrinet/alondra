<?php
/**
 * Plugin status task
 *
 * @package Alondra/Helper
 */

namespace Midrinet\Alondra\Helper\Task;

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

/**
 * Read-only probe of the plugin's DB footprint. The instrument E2E tests use to
 * assert data presence/removal across deactivate / uninstall (cleanup on/off) /
 * reactivate. Reports every `wp_alondra_%` table (catch-all, so a partial drop
 * is visible), both plugin options, and the tiered-pricing row count.
 *
 * The `alondra` / `alondra_version` option keys are hardcoded because this
 * companion plugin does not import the product's classes (separate plugin);
 * the base Task already hardcodes the `alondra_*` table prefixes the same way.
 */
class PluginStatusTask extends Task {

	/**
	 * Execute the task. Emits its own JSON response and exits, so the caller
	 * reads the data instead of the generic envelope.
	 *
	 * @param array<string, mixed> $args Unused.
	 */
	public function execute( array $args = [] ): void {
		global $wpdb;

		// Every existing wp_alondra_% table, not just the three by name — a
		// partial cleanup then shows up as a non-empty list instead of hiding.
		$tables = $wpdb->get_col(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'alondra_' ) . '%' )
		);

		$known        = [ $this->get_tiered_pricing_table_name(), $this->get_tiers_table_name(), $this->get_rules_table_name() ];
		$tables_exist = count( array_intersect( $known, $tables ) ) === count( $known );

		$tp_table = $this->get_tiered_pricing_table_name();
		$rows     = \in_array( $tp_table, $tables, true )
			? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $tp_table ) )
			: 0;

		wp_send_json_success(
			[
				'tables'                => array_values( $tables ),
				'tables_exist'          => $tables_exist,
				'option_exists'         => false !== get_option( 'alondra', false ),
				'version_option_exists' => false !== get_option( 'alondra_version', false ),
				'rows'                  => $rows,
			]
		);
	}
}
