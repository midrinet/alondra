<?php
/**
 * Activate plugin task
 *
 * @package Alondra/Helper
 */

namespace Midrinet\Alondra\Helper\Task;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * Activates a plugin programmatically (page-free) so tests can restore the
 * plugin to active in teardown regardless of browser state. Defaults to the
 * live Alondra plugin.
 */
class ActivatePluginTask extends Task {

	private const DEFAULT_PLUGIN = 'alondra/alondra.php';

	/**
	 * Execute the task
	 *
	 * @param array<string, mixed> $args Webhook request args. Optional `plugin`
	 *                                   is the plugin basename to activate.
	 * @throws \Exception If activation fails.
	 */
	public function execute( array $args = [] ): void {
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin = isset( $args['plugin'] )
			? sanitize_text_field( wp_unslash( $args['plugin'] ) )
			: self::DEFAULT_PLUGIN;

		if ( is_plugin_active( $plugin ) ) {
			return;
		}

		$result = activate_plugin( $plugin );
		if ( is_wp_error( $result ) ) {
			throw new \Exception( $result->get_error_message(), 500 );
		}
	}
}
