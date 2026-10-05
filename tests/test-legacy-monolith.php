<?php
/**
 * Alondra 1.x guard Tests
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests;

use Midrinet\Alondra\Infrastructure\Config\LegacyMonolith;
use WP_UnitTestCase;

/**
 * Next to an active Alondra 1.x, alondra.php only declares HPOS compatibility and explains why it does nothing else.
 */
class LegacyMonolithTest extends WP_UnitTestCase {

	private const HOOKS = [ 'plugins_loaded', 'alondra_di_definitions', 'before_woocommerce_init' ];

	private function plugin_file(): string {
		return \dirname( __DIR__ ) . '/alondra.php';
	}

	/**
	 * Callback count per hook alondra.php may register on, the activation hook included.
	 *
	 * @return array<string, int>
	 */
	private function callback_counts(): array {
		global $wp_filter;

		$counts = [];
		foreach ( array_merge( self::HOOKS, [ 'activate_' . plugin_basename( $this->plugin_file() ) ] ) as $hook ) {
			$counts[ $hook ] = isset( $wp_filter[ $hook ] ) ? array_sum( array_map( 'count', $wp_filter[ $hook ]->callbacks ) ) : 0;
		}

		return $counts;
	}

	/**
	 * Include alondra.php again and assert it registered and fired nothing but the HPOS declaration and the notice.
	 *
	 * @return void
	 */
	private function assert_boots_nothing() {
		$before = $this->callback_counts();
		$loaded = did_action( 'alondra_loaded' );

		require $this->plugin_file(); // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- the plugin's own main file.

		++$before['before_woocommerce_init'];
		$this->assertSame( $before, $this->callback_counts() );
		$this->assertSame( $loaded, did_action( 'alondra_loaded' ) );
		$this->assertSame( 10, has_action( 'admin_notices', [ LegacyMonolith::class, 'print_notice' ] ) );
	}

	public function test_nothing_boots_while_1x_is_active_on_the_site() {
		update_option( 'active_plugins', [ 'woocommerce/woocommerce.php', 'alondra/alondra.php', 'alondra-pro/alondra.php' ] );

		$this->assert_boots_nothing();
	}

	public function test_nothing_boots_while_1x_is_network_active() {
		update_site_option( 'active_sitewide_plugins', [ 'alondra-pro/alondra.php' => time() ] );

		$this->assert_boots_nothing();
	}

	public function test_other_plugins_do_not_count_as_1x() {
		update_option( 'active_plugins', [ 'alondra/alondra.php', 'alondra-plus/alondra-plus.php', 'woocommerce/woocommerce.php' ] );

		$this->assertFalse( LegacyMonolith::is_active() );
	}

	public function test_the_notice_shows_to_users_who_can_activate_plugins() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		ob_start();
		LegacyMonolith::print_notice();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'notice-error', $html );
		$this->assertStringContainsString( 'Alondra 2.0 cannot run while Alondra 1.x is active', $html );
		$this->assertStringContainsString( 'href="' . LegacyMonolith::MIGRATION_URL . '"', $html );
	}

	public function test_the_notice_is_hidden_from_other_users() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'shop_manager' ] ) );

		ob_start();
		LegacyMonolith::print_notice();

		$this->assertSame( '', ob_get_clean() );
	}
}
