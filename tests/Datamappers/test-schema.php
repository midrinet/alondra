<?php
/**
 * Schema Tests
 *
 * Every other test under tests/Datamappers mocks the UpgradeWrapper and asserts the SQL
 * string, so none of them can tell a dbDelta-parseable CREATE TABLE from an unparseable one.
 * This case runs the real datamappers against the real test database instead.
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Datamappers;

use Midrinet\Alondra\Domain\Datamapper\RuleDatamapper;
use Midrinet\Alondra\Domain\Datamapper\TierDatamapper;
use Midrinet\Alondra\Domain\Datamapper\TieredPricingDatamapper;
use Midrinet\Alondra\Domain\Entity\Rule;
use Midrinet\Alondra\Infrastructure\DI\Container;
use Midrinet\Alondra\Infrastructure\Wp\UpgradeWrapper;
use Midrinet\Alondra\Tests\Support\Container_Seam;
use WP_UnitTestCase;

require_once __DIR__ . '/../Support/trait-container-seam.php';

/**
 * Integration tests covering the three CREATE TABLE statements against a live database.
 */
class SchemaTest extends WP_UnitTestCase {

	use Container_Seam;

	/**
	 * Live wpdb for the test database.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Tiered pricing datamapper under test.
	 *
	 * @var TieredPricingDatamapper
	 */
	private $tiered_pricing;

	/**
	 * Tier datamapper under test.
	 *
	 * @var TierDatamapper
	 */
	private $tier;

	/**
	 * Rule datamapper under test.
	 *
	 * @var RuleDatamapper
	 */
	private $rule;

	/**
	 * Executing SQL handed to dbDelta by each set_up() call, in call order.
	 *
	 * set_up() also asks dbDelta for a verification dry run; that call is not recorded here.
	 *
	 * @var string[]
	 */
	private $captured = [];

	/**
	 * Whether the recorder swallows the executing dbDelta call instead of creating the table.
	 *
	 * @var bool
	 */
	private $suppress_create = false;

	/**
	 * Whether the recorder hands the verification dry run one column more than it executed.
	 *
	 * @var bool
	 */
	private $declare_extra_column = false;

	/**
	 * Setup values before each test case
	 */
	public function set_up() {
		global $wpdb;
		parent::set_up();

		/*
		 * WP_UnitTestCase rewrites CREATE TABLE into CREATE TEMPORARY TABLE so its transaction
		 * can roll everything back. Temporary tables are invisible to SHOW TABLES, which is what
		 * is_table_up_to_date() asks first, so the rewrite has to go. Cleanup is by DROP in
		 * tear_down instead of the rollback -- DDL implicitly commits, so the rollback was never
		 * going to undo it.
		 */
		remove_filter( 'query', [ $this, '_create_temporary_tables' ] );
		remove_filter( 'query', [ $this, '_drop_temporary_tables' ] );

		$this->wpdb                 = $wpdb;
		$this->captured             = [];
		$this->suppress_create      = false;
		$this->declare_extra_column = false;

		$recorder = $this->createMock( UpgradeWrapper::class );
		$recorder->method( 'db_delta' )->willReturnCallback(
			function ( $sql, $execute = true ) {
				$sql = (string) $sql;
				if ( $execute ) {
					$this->captured[] = $sql;
					if ( $this->suppress_create ) {
						return [];
					}
				} elseif ( $this->declare_extra_column ) {
					$sql = $this->with_probe_column( $sql );
				}
				return ( new UpgradeWrapper() )->db_delta( $sql, $execute );
			}
		);

		// Same wiring as Container::build(), with the wrapper swapped for the recorder. The
		// datamapper bindings are closures so they can hand back the instances built below.
		$this->install_container(
			new Container(
				[
					\wpdb::class                   => fn() => $wpdb,
					UpgradeWrapper::class          => fn() => $recorder,
					TieredPricingDatamapper::class => fn() => $this->tiered_pricing,
					TierDatamapper::class          => fn() => $this->tier,
					RuleDatamapper::class          => fn() => $this->rule,
				]
			)
		);
		$this->tiered_pricing = new TieredPricingDatamapper();
		$this->tier           = new TierDatamapper();
		$this->rule           = new RuleDatamapper();

		// DDL implicitly commits, so a previous case's tables outlived its rollback.
		$this->drop_tables();
	}

	/**
	 * Clean up after each test case
	 */
	public function tear_down() {
		$this->drop_tables();
		parent::tear_down();
	}

	/**
	 * Drop the three tables.
	 */
	private function drop_tables() {
		$wpdb = $this->wpdb;
		foreach ( [ $this->tier->table(), $this->rule->table(), $this->tiered_pricing->table() ] as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- test fixture teardown, no core API for it.
			$wpdb->query( (string) $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
		}
	}

	/**
	 * Run the three set_up() methods in the order TieredPricingRepo::setup_database() uses.
	 */
	private function set_up_tables() {
		$this->tiered_pricing->set_up();
		$this->tier->set_up();
		$this->rule->set_up();
	}

	/**
	 * A CREATE TABLE statement with one column the real one does not declare.
	 *
	 * @param string $sql CREATE TABLE statement.
	 * @return string
	 */
	private function with_probe_column( $sql ) {
		return str_replace( 'PRIMARY KEY', "alondra_probe varchar(1) NULL,\nPRIMARY KEY", $sql );
	}

	/**
	 * Changes dbDelta proposes for a statement, ignoring the cosmetic phantom "Created table".
	 *
	 * WordPress up to 6.8 keys $cqueries on the unbackticked table name but $for_update on the
	 * backticked one, so the unset that clears an up-to-date table misses the second and the
	 * return value keeps a "Created table" entry for a table it did not create. Nothing in the
	 * plugin reads that value, and the table's existence is asserted separately.
	 *
	 * @param string $sql CREATE TABLE statement.
	 * @return string[]
	 */
	private function dry_run( $sql ) {
		$changes = ( new UpgradeWrapper() )->db_delta( $sql, false );
		return array_values(
			array_filter(
				array_map( 'strval', $changes ),
				static function ( $change ) {
					return 0 !== strpos( $change, 'Created table' );
				}
			)
		);
	}

	/**
	 * Whether a table exists in the test database.
	 *
	 * @param string $table Prefixed table name.
	 * @return bool
	 */
	private function table_exists( $table ) {
		$wpdb = $this->wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema lookup, no core API for it and nothing to cache.
		return $table === $wpdb->get_var( (string) $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	/**
	 * Names of every column on a table.
	 *
	 * @param string $table Prefixed table name.
	 * @return string[]
	 */
	private function column_names( $table ) {
		$wpdb = $this->wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema lookup, no core API for it and nothing to cache.
		$found = $wpdb->get_col( (string) $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) );
		return array_map( 'strval', (array) $found );
	}

	/**
	 * Test case for the bundle-scoped rule target column against a live table.
	 *
	 * The mocked set_up() cases assert the SQL string, so they would pass on a column dbDelta never
	 * created. This reads the column back off the real table and round-trips a pair through the live
	 * datamapper.
	 */
	public function testSetUp_rulesTable_storesBundleScopedTargets() {
		$this->set_up_tables();

		$column = $this->rule->col_bundle_product();
		$this->assertContains( $column, $this->column_names( $this->rule->table() ), "{$this->rule->table()} has no {$column} column." );

		$this->rule->save( new Rule( 0, 7, 'ANY', 'ANY', 'ANY', 'OR', 'OR', [], [], [ 15 ], [], [], [ '61:15' ] ) );

		$rules = $this->rule->find_by_tiered_pricing( 7 );
		$this->assertCount( 1, $rules );
		$this->assertSame( [ '61:15' ], $rules[0]->bundle_products );
	}

	/**
	 * Test case for a rules table from before the column: re-running set_up() adds it and keeps the rows.
	 *
	 * This is all AddBundleColumnMigration does, so it is the migration's real-database proof.
	 */
	public function testSetUp_rulesTableWithoutBundleColumn_addsItAndKeepsRows() {
		$wpdb = $this->wpdb;
		$this->set_up_tables();
		$this->rule->save( new Rule( 0, 7, 'ANY', 'ANY', 'ANY', 'OR', 'OR', [], [], [ 15 ] ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- test fixture: the table as a pre-migration install has it.
		$wpdb->query( (string) $wpdb->prepare( 'ALTER TABLE %i DROP COLUMN %i', $this->rule->table(), $this->rule->col_bundle_product() ) );
		$this->assertNotContains( $this->rule->col_bundle_product(), $this->column_names( $this->rule->table() ) );

		$this->assertTrue( $this->rule->set_up() );

		$this->assertContains( $this->rule->col_bundle_product(), $this->column_names( $this->rule->table() ) );
		$rules = $this->rule->find_by_tiered_pricing( 7 );
		$this->assertCount( 1, $rules );
		$this->assertSame( [ 15 ], $rules[0]->products );
		$this->assertSame( [], $rules[0]->bundle_products );
	}

	/**
	 * Test case for the dbDelta dry run being clean for each table.
	 *
	 * This is the acceptance criterion the mocked set_up() tests cannot reach: it fails for a
	 * single-line CREATE TABLE, and it fails for uppercase or width-less column types.
	 */
	public function testSetUp_dbDeltaDryRun_proposesNoChanges() {
		$this->set_up_tables();

		$this->assertCount( 3, $this->captured );
		foreach ( $this->captured as $sql ) {
			$this->assertSame( [], $this->dry_run( $sql ), "dbDelta proposed changes for: {$sql}" );
		}
	}

	/**
	 * Test case guarding the dry run above against a false pass.
	 *
	 * A dry run of an unparseable statement is clean too, because dbDelta sees no columns to
	 * compare. Adding a column dbDelta must notice separates the two: against the old single-line
	 * form the probe column goes unreported, the reformatted one reports Added column.
	 */
	public function testSetUp_dbDeltaDryRunWithExtraColumn_proposesAddedColumn() {
		$this->set_up_tables();

		$this->assertCount( 3, $this->captured );
		foreach ( $this->captured as $sql ) {
			$probed = $this->with_probe_column( $sql );
			$this->assertStringContainsString(
				'Added column',
				implode( ' ', $this->dry_run( $probed ) ),
				"dbDelta did not notice a missing column in: {$probed}"
			);
		}
	}

	/**
	 * Test case for set_up() reporting success on a fresh table and on an already-correct one.
	 *
	 * The second run is what the "Created table" filter in is_table_up_to_date() exists for: on
	 * WordPress 6.8, the supported floor, a dry run against an up-to-date table still returns a
	 * phantom "Created table" entry. PHPUnit runs against the test library's WordPress, which is
	 * past that, so the 6.8 shape is verified out of band by transplanting the 6.8 dbDelta.
	 */
	public function testSetUp_freshAndUpToDateTables_returnTrue() {
		$this->assertTrue( $this->tiered_pricing->set_up() );
		$this->assertTrue( $this->tier->set_up() );
		$this->assertTrue( $this->rule->set_up() );

		$this->assertTrue( $this->tiered_pricing->set_up() );
		$this->assertTrue( $this->tier->set_up() );
		$this->assertTrue( $this->rule->set_up() );
	}

	/**
	 * Test case for a column the declaration carries and the live table does not.
	 *
	 * This is the drift SHOW TABLES LIKE could not see -- the table exists under the right name,
	 * so the old check reported success. The recorder hands the verification dry run one column
	 * more than the CREATE it executed, which is what a release adding a column that dbDelta
	 * could not apply looks like from set_up()'s side.
	 */
	public function testSetUp_declaredColumnMissingFromTable_returnsFalse() {
		$this->set_up_tables();

		$this->declare_extra_column = true;

		$this->assertFalse( $this->tiered_pricing->set_up() );
		$this->assertFalse( $this->tier->set_up() );
		$this->assertFalse( $this->rule->set_up() );
	}

	/**
	 * Test case for a table that was never created.
	 *
	 * Filtering the phantom costs the dry run its only signal for an absent table -- on 6.8 an
	 * up-to-date table returns the same single entry -- so the existence check in front of it is
	 * what still catches this. The recorder swallows the executing call to leave nothing behind.
	 */
	public function testSetUp_tableNotCreated_returnsFalse() {
		$this->suppress_create = true;

		$this->assertFalse( $this->tiered_pricing->set_up() );
		$this->assertFalse( $this->table_exists( $this->tiered_pricing->table() ) );
	}
}
