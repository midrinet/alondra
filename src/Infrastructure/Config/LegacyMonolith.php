<?php
/**
 * Alondra 1.x detection
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Infrastructure/Config
 */

namespace Midrinet\Alondra\Infrastructure\Config;

use Midrinet\Alondra\Infrastructure\View\Admin\NoticeView;

/**
 * Alondra 1.x shares this plugin's namespace, Freemius product and hook names, so the two cannot run together.
 */
class LegacyMonolith {

	public const FOLDER = 'alondra-pro/';

	public const MIGRATION_URL = 'https://alondra.midri.net/blog/migrate-to-alondra-plus';

	/**
	 * Whether Alondra 1.x is active on this site or network-wide.
	 *
	 * Read from the active plugins lists because 1.x sorts after this plugin and is not loaded yet when
	 * this plugin's main file runs.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		$plugins = array_merge(
			(array) get_option( 'active_plugins', [] ),
			array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) )
		);

		foreach ( $plugins as $plugin ) {
			if ( \is_string( $plugin ) && 0 === strpos( $plugin, self::FOLDER ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Print an admin notice explaining why the plugin is doing nothing.
	 *
	 * @return void
	 */
	public static function print_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$message = \sprintf(
			// translators: %s: URL of the migration instructions.
			__( 'Alondra 2.0 cannot run while Alondra 1.x is active, so it is doing nothing. Deactivate Alondra 1.x to start using it. <a href="%s" target="_blank" rel="noopener noreferrer">How to migrate from Alondra 1.x</a>', 'alondra' ),
			esc_url( self::MIGRATION_URL )
		);

		echo ( new NoticeView() )->render( $message, 'notice-error', false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- NoticeView escapes.
	}
}
