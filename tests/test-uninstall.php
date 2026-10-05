<?php
/**
 * Uninstall routine Tests
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests;

use Midrinet\Alondra\Domain\Datamapper\DatabaseDatamapper;
use Midrinet\Alondra\Domain\Repository\TieredPricingRepo;
use Midrinet\Alondra\Infrastructure\Controller\ActivationController;
use Midrinet\Alondra\Infrastructure\Migration\MigrationRunner;
use Midrinet\Alondra\Application\Service\PreferencesService;
use Midrinet\Alondra\Infrastructure\DI\Container;
use WP_UnitTestCase;

/**
 * The uninstall routine removes the plugin options either way. The pricing tables stay by default, so a
 * later reinstall finds the data intact -- and the migration cursor stays with them, since it is what says
 * how far those tables have been migrated. The stored `uninstall_cleanup` preference opts out of both together,
 * but only for as far as the drops actually got.
 *
 * Everything here runs against the plugin's real tables -- there is no fixture to build and none to fake,
 * and the CREATE TEMPORARY TABLE rewrite would hide both the drops under test and the rebuild afterwards.
 * That means this class really drops them, and really commits the option writes with them, since DDL commits
 * the framework's transaction. Four other classes drop the same tables, so neither their presence on arrival
 * nor their absence can be assumed: set_up() rebuilds them through the plugin's own idempotent
 * `setup_database()`, and tear_down() does the same and puts the two schema options back, so nothing that
 * runs after this class inherits a database it emptied.
 */
class UninstallTest extends WP_UnitTestCase {

	/**
	 * Prefixed names of the three pricing tables.
	 *
	 * @var string[]
	 */
	private $tables = [];

	/**
	 * Option values that outlive the framework's transaction, keyed by option name.
	 *
	 * @var array<string, mixed>
	 */
	private $options = [];

	public function set_up() {
		parent::set_up();
		global $wpdb;

		// The test case rewrites CREATE TABLE into CREATE TEMPORARY TABLE and hides DROP TABLE, which would
		// make both the uninstall under test and the rebuild in tear_down() no-ops against the real tables.
		remove_filter( 'query', [ $this, '_create_temporary_tables' ] );
		remove_filter( 'query', [ $this, '_drop_temporary_tables' ] );

		foreach (
			[
				DatabaseDatamapper::TABLE_TIERED_PRICING,
				DatabaseDatamapper::TABLE_TIERS,
				DatabaseDatamapper::TABLE_RULES,
			] as $name
		) {
			$this->tables[] = $wpdb->prefix . $name;
		}

		$this->setup_database();

		foreach ( [ MigrationRunner::PREF_MIGRATION_ID, ActivationController::PREF_VERSION ] as $option ) {
			$this->options[ $option ] = get_option( $option );
		}
	}

	public function tear_down() {
		// Before parent::tear_down(): _restore_hooks() reinstates the temporary-table filters, which would
		// turn the rebuild into CREATE TEMPORARY TABLE and leave the real tables still missing.
		$this->setup_database();

		foreach ( $this->options as $option => $value ) {
			if ( false === $value ) {
				delete_option( $option );
			} else {
				update_option( $option, $value );
			}
		}

		$this->tables  = [];
		$this->options = [];

		parent::tear_down();
	}

	/**
	 * Create any missing pricing table, through the same re-enterable routine the plugin installs with.
	 *
	 * On a freshly built container rather than the bootstrap's: that one is shared for the whole run and
	 * has already cached whatever earlier cases resolved, so resolving the datamappers through it would
	 * depend on what ran first -- which is the ordering dependence this class is trying to stop having.
	 */
	private function setup_database(): void {
		$previous = Container::set_instance( null );
		try {
			Container::build( \dirname( __DIR__ ) . '/alondra.php' );
			( new TieredPricingRepo() )->setup_database();
		} finally {
			Container::set_instance( $previous );
		}
	}

	/**
	 * Seed everything the plugin persists, then run the uninstall routine.
	 *
	 * @param array<string, mixed> $prefs Preferences to store.
	 */
	private function uninstall( array $prefs = [ PreferencesService::PREF_DISABLE_STYLES => '1' ] ): void {
		update_option( PreferencesService::PREF_OPTION, $prefs );
		update_option( ActivationController::PREF_VERSION, '1.0.0' );
		update_option( TieredPricingRepo::CACHE_GENERATION, 1 );
		update_option( MigrationRunner::PREF_MIGRATION_ID, 1 );
		update_option(
			MigrationRunner::PREF_MIGRATION_FAILURE,
			[
				'id'      => 1,
				'message' => 'boom',
			]
		);

		if ( ! \defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			\define( 'WP_UNINSTALL_PLUGIN', 'alondra/alondra.php' );
		}
		// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.NotAbsolutePath
		require \dirname( __DIR__ ) . '/uninstall.php';
	}

	/**
	 * Whether a table is still present, asked of the server rather than of a cached schema.
	 *
	 * @param string $table Prefixed table name.
	 */
	private function table_exists( string $table ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	public function test_uninstall_keeps_the_tables_and_the_cursor_describing_them() {
		$this->uninstall();

		$this->assertFalse( get_option( PreferencesService::PREF_OPTION ) );
		$this->assertFalse( get_option( ActivationController::PREF_VERSION ) );
		$this->assertFalse( get_option( TieredPricingRepo::CACHE_GENERATION ) );

		// The cursor stays because the tables stay, and the two have to agree: it records how far the
		// surviving tables have been migrated. Cleared, it would tell the next install to replay every
		// migration over data that has already been through them, which no migration promises to survive
		// -- the contract only covers failing part-way through one before its id is recorded.
		$this->assertEquals( 1, get_option( MigrationRunner::PREF_MIGRATION_ID ) );

		// The failure record is the opposite case: it describes an attempt against an install that no
		// longer exists, so leaving it would greet the next one with a notice about a failure that never
		// happened to it.
		$this->assertFalse( get_option( MigrationRunner::PREF_MIGRATION_FAILURE ) );

		foreach ( $this->tables as $table ) {
			$this->assertTrue( $this->table_exists( $table ), "$table should have survived the uninstall" );
		}
	}

	public function test_uninstall_deletes_the_tables_and_the_cursor_when_the_preference_opts_in() {
		$this->uninstall( [ PreferencesService::PREF_UNINSTALL_CLEANUP => '1' ] );

		$this->assertFalse( get_option( PreferencesService::PREF_OPTION ) );
		$this->assertFalse( get_option( ActivationController::PREF_VERSION ) );
		$this->assertFalse( get_option( TieredPricingRepo::CACHE_GENERATION ) );
		$this->assertFalse( get_option( MigrationRunner::PREF_MIGRATION_FAILURE ) );

		// The cursor has to leave with the tables it describes: on its own it would tell the next install
		// its schema is already current when the site has no tables at all.
		$this->assertFalse( get_option( MigrationRunner::PREF_MIGRATION_ID ) );

		foreach ( $this->tables as $table ) {
			$this->assertFalse( $this->table_exists( $table ), "$table should have been dropped" );
		}
	}

	public function test_a_failed_drop_keeps_the_cursor() {
		global $wpdb;

		// Stand in for the reasons a real drop fails -- a database user without DROP, a foreign key from an
		// older restored dump still pointing at the parent -- without needing either. The `query` filter is
		// the same hook the test case itself rewrites DDL on, and a DROP naming a table that does not exist,
		// with no IF EXISTS, is the shortest statement wpdb reports as false.
		$sabotage = static function ( $query ) {
			return 0 === strpos( $query, 'DROP TABLE' ) ? 'DROP TABLE `alondra_no_such_table`' : $query;
		};
		add_filter( 'query', $sabotage );
		$wpdb->suppress_errors( true );

		$this->uninstall( [ PreferencesService::PREF_UNINSTALL_CLEANUP => '1' ] );

		$wpdb->suppress_errors( false );
		remove_filter( 'query', $sabotage );

		// The tables are still there with all their data, so the cursor saying how far they have been
		// migrated has to still be there too.
		$this->assertEquals( 1, get_option( MigrationRunner::PREF_MIGRATION_ID ) );

		foreach ( $this->tables as $table ) {
			$this->assertTrue( $this->table_exists( $table ), "$table should have survived a failed drop" );
		}

		// The options that do not describe the schema go regardless: they are the plugin's own settings,
		// not the shop's data.
		$this->assertFalse( get_option( ActivationController::PREF_VERSION ) );
		$this->assertFalse( get_option( TieredPricingRepo::CACHE_GENERATION ) );
	}

	/**
	 * @dataProvider provide_preferences_that_keep_the_tables
	 *
	 * @param array<string, mixed> $prefs Stored preferences.
	 */
	public function test_uninstall_keeps_the_tables_unless_the_preference_opts_in( array $prefs ) {
		$this->uninstall( $prefs );

		$this->assertEquals( 1, get_option( MigrationRunner::PREF_MIGRATION_ID ) );
		foreach ( $this->tables as $table ) {
			$this->assertTrue( $this->table_exists( $table ), "$table should have survived the uninstall" );
		}
	}

	/**
	 * @return array<string, array{array<string, mixed>}>
	 */
	public function provide_preferences_that_keep_the_tables() {
		return [
			'off'    => [ [ PreferencesService::PREF_UNINSTALL_CLEANUP => '0' ] ],
			'absent' => [ [] ],
		];
	}

	/**
	 * The retired filter is no longer consulted: a listener opting in drops nothing.
	 */
	public function test_uninstall_ignores_the_retired_filter() {
		$calls = 0;
		add_filter(
			'alondra_delete_data_on_uninstall',
			function () use ( &$calls ) {
				++$calls;
				return true;
			}
		);

		$this->uninstall( [ PreferencesService::PREF_UNINSTALL_CLEANUP => '0' ] );

		$this->assertSame( 0, $calls );
		foreach ( $this->tables as $table ) {
			$this->assertTrue( $this->table_exists( $table ), "$table should have survived the uninstall" );
		}
	}
}
