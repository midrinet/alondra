<?php
/**
 * Migration chains through the container
 *
 * An add-on appends its chain by binding a runner subclass through `alondra_di_definitions`. These build the
 * real container, with and without that listener, and run it against real options.
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Migrations;

use Midrinet\Alondra\Infrastructure\DI\Container;
use Midrinet\Alondra\Infrastructure\Migration\MigrationRunner;
use Midrinet\Alondra\Tests\Support\Addon_Migration_Runner;
use Midrinet\Alondra\Tests\Support\Addon_Test_Migration;
use Midrinet\Alondra\Tests\Support\Container_Seam;
use WP_UnitTestCase;

require_once __DIR__ . '/../Support/trait-container-seam.php';
require_once __DIR__ . '/../Support/class-addon-migration-runner.php';

/**
 * Tests a second chain bound the way an add-on binds it.
 */
class MigrationChainsDiTest extends WP_UnitTestCase {

	use Container_Seam;

	/**
	 * Free's head, which the bootstrap's own migration already reached.
	 *
	 * @var int
	 */
	private $head;

	public function set_up() {
		parent::set_up();

		delete_transient( MigrationRunner::MIGRATION_LOCK );
		delete_option( MigrationRunner::PREF_MIGRATION_FAILURE );
		delete_option( Addon_Migration_Runner::ADDON_CURSOR );

		$this->head = max( array_keys( MigrationRunner::MIGRATIONS ) );
		update_option( MigrationRunner::PREF_MIGRATION_ID, $this->head );

		Addon_Test_Migration::$runs = 0;
	}

	/**
	 * Build the real container, so the definitions filter is applied exactly as on plugins_loaded.
	 *
	 * @return MigrationRunner
	 */
	private function build_runner() {
		$this->reset_container();

		return Container::build( \dirname( __DIR__, 2 ) . '/alondra.php' )->get( MigrationRunner::class );
	}

	public function test_an_addon_chain_bound_through_the_definitions_filter_runs_on_its_own_cursor() {
		add_filter(
			'alondra_di_definitions',
			function ( array $definitions ) {
				$definitions[ MigrationRunner::class ]      = Addon_Migration_Runner::class;
				$definitions[ Addon_Test_Migration::class ] = Addon_Test_Migration::class;
				return $definitions;
			}
		);

		$runner = $this->build_runner();

		$this->assertInstanceOf( Addon_Migration_Runner::class, $runner );
		$this->assertTrue( $runner->has_pending(), 'Free at its head must not hide the add-on chain.' );

		$runner->run();

		$this->assertSame( 1, Addon_Test_Migration::$runs );
		$this->assertSame( 1, (int) get_option( Addon_Migration_Runner::ADDON_CURSOR ) );
		$this->assertSame( $this->head, (int) get_option( MigrationRunner::PREF_MIGRATION_ID ), 'Free\'s cursor is not the add-on\'s.' );
		$this->assertTrue( $runner->is_applied( 1, Addon_Migration_Runner::ADDON_CHAIN ) );
		$this->assertFalse( $runner->has_pending() );
		$this->assertEmpty( get_transient( MigrationRunner::MIGRATION_LOCK ) );
	}

	public function test_free_runs_only_its_own_chain_with_no_listener() {
		$runner = $this->build_runner();

		$this->assertSame( MigrationRunner::class, \get_class( $runner ) );
		$this->assertFalse( $runner->has_pending() );
		$this->assertTrue( $runner->is_applied( MigrationRunner::DROP_FOREIGN_KEYS ) );
		$this->assertFalse( $runner->is_applied( 1, Addon_Migration_Runner::ADDON_CHAIN ) );

		$runner->run();

		$this->assertSame( 0, Addon_Test_Migration::$runs );
		$this->assertSame( $this->head, (int) get_option( MigrationRunner::PREF_MIGRATION_ID ) );
		$this->assertFalse( get_option( Addon_Migration_Runner::ADDON_CURSOR ) );
	}
}
