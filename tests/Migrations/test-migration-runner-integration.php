<?php
/**
 * Migration runner integration Tests
 *
 * The rest of the suite drives the runner against doubles. This one drives the shipped registry through the
 * real container against the real database, which is the only way to assert the thing the subsystem exists
 * for: an install whose tables are not there gets them, without anybody activating the plugin.
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Migrations;

use Midrinet\Alondra\Application\Service\TieredPricingService;
use Midrinet\Alondra\Infrastructure\Migration\MigrationRunner;
use Midrinet\Alondra\Infrastructure\DI\Container;
use WP_UnitTestCase;

/**
 * Tests the shipped registry end to end.
 */
class MigrationRunnerIntegrationTest extends WP_UnitTestCase {

	/**
	 * Table basenames the shipped registry is responsible for creating, children before parents.
	 *
	 * Both child tables carry a foreign key into alondra_tiered_pricing and FOREIGN_KEY_CHECKS is never
	 * disabled here, so dropping the parent first is refused and leaves it behind.
	 *
	 * @var string[]
	 */
	private const TABLES = [ 'alondra_tiers', 'alondra_rules', 'alondra_tiered_pricing' ];

	public function set_up() {
		parent::set_up();

		// WP_UnitTestCase rewrites CREATE TABLE into CREATE TEMPORARY TABLE so its transaction can roll
		// back. Temporary tables are invisible to SHOW TABLES, which is what this test looks for, and take
		// no foreign keys. DDL implicitly commits anyway, so the rollback was never going to undo it --
		// tear_down() drops what this test created instead.
		remove_filter( 'query', [ $this, '_create_temporary_tables' ] );
		remove_filter( 'query', [ $this, '_drop_temporary_tables' ] );

		// The plugin's own controller runs a real migration on the bootstrap's `init`, before the first
		// test opens a transaction, so both survive one and both have to be cleared by hand.
		delete_transient( MigrationRunner::MIGRATION_LOCK );
		delete_option( MigrationRunner::PREF_MIGRATION_FAILURE );

		$this->drop_tables();
	}

	/**
	 * Put the database back the way the bootstrap left it.
	 *
	 * Has to be static, and has to run here rather than in tear_down(). This class removes the
	 * temporary-table filters, so its DDL is real -- and DDL implicitly commits, which promotes every
	 * option and transient write that happened earlier in the same transaction while the writes that come
	 * after it are still rolled back. A tear_down() cleaning up the lock is therefore undone by the very
	 * rollback it runs before, and the lock the run took stays in the database for the rest of the suite
	 * and beyond. This runs after the class's last transaction has closed, so its writes stick.
	 */
	public static function tear_down_after_class() {
		delete_transient( MigrationRunner::MIGRATION_LOCK );
		delete_option( MigrationRunner::PREF_MIGRATION_FAILURE );

		// The tables are left in place, so the cursor that describes them has to say so -- the same
		// agreement between the two that uninstall.php keeps. Only declared when they are actually
		// missing: dbDelta cannot diff these single-line statements, so running it over tables that
		// already exist buys nothing and prints three database errors into the suite's output.
		if ( self::TABLES !== self::existing_tables() ) {
			Container::instance()->get( TieredPricingService::class )->setup();
		}
		// The head of the registry, not a named id: every migration in it has run against these tables by
		// now, so naming only the first would under-report and send the next run back over the rest.
		Container::instance()->get( MigrationRunner::class )->set_migration_id( max( array_keys( MigrationRunner::MIGRATIONS ) ) );

		parent::tear_down_after_class();
	}

	/**
	 * Drop the plugin's tables, the way a site that never had them looks.
	 */
	private function drop_tables() {
		global $wpdb;

		foreach ( self::TABLES as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $table ) );
		}
	}

	/**
	 * Which of the plugin's tables the database actually has.
	 *
	 * @return string[]
	 */
	private static function existing_tables() {
		global $wpdb;

		$found = [];
		foreach ( self::TABLES as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . $table ) ) ) {
				$found[] = $table;
			}
		}

		return $found;
	}

	/**
	 * A real service off the real container, so the registry, the bindings and the cursor accessors are all
	 * the shipped ones.
	 *
	 * @return MigrationRunner
	 */
	private function get_instance() {
		return Container::instance()->get( MigrationRunner::class );
	}

	/**
	 * The property the port exists for. Nothing activates the plugin here: the cursor is what an install
	 * that has never migrated reads, and one run() has to leave the schema in place.
	 *
	 * This is also the repair path for an install whose activation-time setup() never landed -- a host
	 * without CREATE rights at the time, a fatal part-way through -- because that install carries no cursor
	 * either and reaches exactly this state on its next request.
	 */
	public function test_runner_creates_the_tables_on_an_install_that_has_none() {
		$runner = Container::instance()->get( MigrationRunner::class );
		$runner->set_migration_id( 0 );

		$this->assertSame( [], self::existing_tables(), 'Precondition: the database must start with none of them.' );

		$service = $this->get_instance();
		$this->assertTrue( $service->has_pending(), 'Precondition: a cursor of 0 leaves the table creation pending.' );

		$service->run();

		$this->assertSame( self::TABLES, self::existing_tables() );
		$this->assertTrue( $service->is_applied( MigrationRunner::CREATE_TABLES ) );
		$this->assertFalse( $service->has_pending() );
		$this->assertFalse( get_option( MigrationRunner::PREF_MIGRATION_FAILURE ), 'A clean run records no failure.' );
		$this->assertEmpty( get_transient( MigrationRunner::MIGRATION_LOCK ), 'Success releases the lock.' );
	}

	/**
	 * The steady state on every request after that one: the cursor is at the head, so the runner does no
	 * work at all and the tables it already created are left alone.
	 */
	public function test_a_second_run_is_a_no_op() {
		$runner = Container::instance()->get( MigrationRunner::class );
		$runner->set_migration_id( 0 );

		$service = $this->get_instance();
		$service->run();
		$service->run();

		$this->assertSame( self::TABLES, self::existing_tables() );
		$this->assertFalse( $service->has_pending() );
	}

	/**
	 * Columns of the plugin's rules table.
	 *
	 * @return string[]
	 */
	private static function rule_columns() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return array_map( 'strval', (array) $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $wpdb->prefix . 'alondra_rules' ) ) );
	}

	/**
	 * An older install: cursor 2 and a rules table without bundle_product. One run adds the column.
	 */
	public function test_an_install_at_cursor_two_gains_the_bundle_column() {
		global $wpdb;

		$runner = Container::instance()->get( MigrationRunner::class );
		$runner->set_migration_id( 0 );
		$this->get_instance()->run();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP COLUMN bundle_product', $wpdb->prefix . 'alondra_rules' ) );
		$runner->set_migration_id( MigrationRunner::DROP_FOREIGN_KEYS );
		$this->assertNotContains( 'bundle_product', self::rule_columns(), 'Precondition: the column must be missing.' );

		$service = $this->get_instance();
		$this->assertTrue( $service->has_pending() );

		$service->run();

		$this->assertContains( 'bundle_product', self::rule_columns() );
		$this->assertSame( MigrationRunner::IMPORT_SEED_FILTERS, (int) get_option( MigrationRunner::PREF_MIGRATION_ID ) );
		$this->assertFalse( get_option( MigrationRunner::PREF_MIGRATION_FAILURE ) );
	}

	/**
	 * An older install already at cursor 3 has the column, so no schema work runs over it.
	 */
	public function test_an_install_at_cursor_three_runs_no_schema_work() {
		$runner = Container::instance()->get( MigrationRunner::class );
		$runner->set_migration_id( 0 );
		$this->get_instance()->run();
		$runner->set_migration_id( MigrationRunner::ADD_BUNDLE_PRODUCT_COLUMN );

		$db_delta_calls = 0;
		$count          = function ( $queries ) use ( &$db_delta_calls ) {
			++$db_delta_calls;
			return $queries;
		};
		add_filter( 'dbdelta_queries', $count );

		$service = $this->get_instance();
		$service->run();

		remove_filter( 'dbdelta_queries', $count );

		$this->assertSame( 0, $db_delta_calls );
		$this->assertContains( 'bundle_product', self::rule_columns() );
		$this->assertSame( MigrationRunner::IMPORT_SEED_FILTERS, (int) get_option( MigrationRunner::PREF_MIGRATION_ID ) );
	}
}
