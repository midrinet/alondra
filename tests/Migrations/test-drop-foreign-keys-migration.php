<?php
/**
 * DropForeignKeysMigration Tests
 *
 * The migration issues DDL straight at the database and reads the constraint names back out of
 * SHOW CREATE TABLE, so a mocked wpdb could only assert the strings it was handed. These run it
 * against the live test database instead.
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Migrations;

use Midrinet\Alondra\Application\Service\TieredPricingService;
use Midrinet\Alondra\Infrastructure\Migration\DropForeignKeysMigration;
use Midrinet\Alondra\Infrastructure\DI\Container;
use WP_UnitTestCase;

/**
 * Integration tests covering the removal of the child tables' foreign keys.
 */
class DropForeignKeysMigrationTest extends WP_UnitTestCase {

	/**
	 * Live wpdb for the test database.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Prefixed child tables the migration works on.
	 *
	 * @var string[]
	 */
	private $children = [];

	/**
	 * Setup values before each test case
	 */
	public function set_up() {
		global $wpdb;
		parent::set_up();

		/*
		 * WP_UnitTestCase rewrites CREATE TABLE into CREATE TEMPORARY TABLE so its transaction can roll
		 * everything back. Temporary tables take no foreign keys at all, which is the whole subject here.
		 * Cleanup is by DROP in tear_down instead -- DDL implicitly commits, so the rollback was never
		 * going to undo it.
		 */
		remove_filter( 'query', [ $this, '_create_temporary_tables' ] );
		remove_filter( 'query', [ $this, '_drop_temporary_tables' ] );

		$this->wpdb     = $wpdb;
		$this->children = [ "{$wpdb->prefix}alondra_tiers", "{$wpdb->prefix}alondra_rules" ];

		$this->drop_tables();
		Container::instance()->get( TieredPricingService::class )->setup();
		$this->add_child_foreign_keys();
	}

	/**
	 * Clean up after each test case
	 */
	public function tear_down() {
		$this->drop_tables();
		parent::tear_down();
	}

	/**
	 * Put the tables back for whatever runs next.
	 *
	 * tear_down() drops them so each case starts from a known schema, which would otherwise leave the
	 * suite -- and the database it runs against -- with no plugin tables at all once this class finishes.
	 * Static, because the per-test transaction is long closed by the time this runs and DDL has to stick.
	 *
	 * setup() is reached with the tables absent, never over existing ones: these CREATE TABLE statements
	 * are a single line, which dbDelta reads as one field, so running it against a table that is already
	 * there emits ALTERs and prints their errors into the suite output.
	 */
	public static function tear_down_after_class() {
		Container::instance()->get( TieredPricingService::class )->setup();

		parent::tear_down_after_class();
	}

	/**
	 * Drop the three tables, children first: FOREIGN_KEY_CHECKS is never disabled.
	 */
	private function drop_tables() {
		$wpdb = $this->wpdb;
		foreach ( array_merge( $this->children, [ "{$wpdb->prefix}alondra_tiered_pricing" ] ) as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- test fixture teardown, no core API for it.
			$wpdb->query( (string) $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
		}
	}

	/**
	 * Put the child tables into the state the migration exists to undo.
	 *
	 * Setup stopped issuing these constraints, so an install carrying them is no longer something the
	 * plugin's own schema code can produce -- only a database left behind by an earlier release.
	 */
	private function add_child_foreign_keys() {
		$wpdb   = $this->wpdb;
		$parent = "{$wpdb->prefix}alondra_tiered_pricing";

		foreach ( $this->children as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- test fixture, no core API for it.
			$wpdb->query(
				(string) $wpdb->prepare(
					'ALTER TABLE %i ADD CONSTRAINT %i FOREIGN KEY (%i) REFERENCES %i (%i) ON DELETE CASCADE',
					$table,
					"{$table}_tiered_pricing_id_fk",
					'tiered_pricing_id',
					$parent,
					'id'
				)
			);
		}
	}

	private function get_instance() {
		return new DropForeignKeysMigration();
	}

	/**
	 * Names of every foreign key on a table.
	 *
	 * Read from information_schema, which the migration deliberately does not use: an assertion built out of
	 * the same SHOW CREATE TABLE parsing it is meant to be checking would pass on a regex that matches nothing.
	 *
	 * @param string $table Prefixed table name.
	 * @return string[]
	 */
	private function foreign_key_names( $table ) {
		$wpdb = $this->wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema lookup, no core API for it and nothing to cache.
		$found = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = %s AND CONSTRAINT_TYPE = %s',
				$table,
				'FOREIGN KEY'
			)
		);
		return array_map( 'strval', (array) $found );
	}

	/**
	 * Names of every index on a table.
	 *
	 * @param string $table Prefixed table name.
	 * @return string[]
	 */
	private function index_names( $table ) {
		$wpdb = $this->wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema lookup, no core API for it and nothing to cache.
		$found = $wpdb->get_col( (string) $wpdb->prepare( 'SHOW INDEX FROM %i', $table ), 2 );
		return array_map( 'strval', (array) $found );
	}

	/**
	 * Drop every foreign key on the two child tables and re-add them without a name.
	 *
	 * What an install predating the derived name looks like: MariaDB names these `1`, MySQL
	 * wp_alondra_tiers_ibfk_1. The migration reads the name back rather than rebuilding it precisely
	 * because it cannot know which.
	 */
	private function rename_foreign_keys_to_whatever_the_server_picks() {
		$wpdb   = $this->wpdb;
		$parent = "{$wpdb->prefix}alondra_tiered_pricing";

		foreach ( $this->children as $table ) {
			foreach ( $this->foreign_key_names( $table ) as $name ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- test fixture, no core API for it.
				$wpdb->query( (string) $wpdb->prepare( 'ALTER TABLE %i DROP FOREIGN KEY %i', $table, $name ) );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- test fixture, no core API for it.
			$wpdb->query(
				(string) $wpdb->prepare(
					'ALTER TABLE %i ADD FOREIGN KEY (%i) REFERENCES %i (%i) ON DELETE CASCADE',
					$table,
					'tiered_pricing_id',
					$parent,
					'id'
				)
			);
		}
	}

	/**
	 * Leave each child with no index of its own on the join column, only the one the constraint carries.
	 *
	 * What an install predating the explicit KEY line looks like: the server auto-creates an index for a
	 * foreign key that has none and names it after the constraint. Whether that index outlives the drop is
	 * the server's business -- MariaDB 12.2 keeps it -- which is why the migration adds its own rather than
	 * betting either way.
	 *
	 * @return void
	 */
	private function leave_the_column_indexed_only_by_the_constraint() {
		$wpdb   = $this->wpdb;
		$parent = "{$wpdb->prefix}alondra_tiered_pricing";

		foreach ( $this->children as $table ) {
			foreach ( $this->foreign_key_names( $table ) as $name ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- test fixture, no core API for it.
				$wpdb->query( (string) $wpdb->prepare( 'ALTER TABLE %i DROP FOREIGN KEY %i', $table, $name ) );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- test fixture, no core API for it.
			$wpdb->query( (string) $wpdb->prepare( 'ALTER TABLE %i DROP INDEX %i', $table, 'tiered_pricing_id' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- test fixture, no core API for it.
			$wpdb->query(
				(string) $wpdb->prepare(
					'ALTER TABLE %i ADD CONSTRAINT %i FOREIGN KEY (%i) REFERENCES %i (%i) ON DELETE CASCADE',
					$table,
					"{$table}_tiered_pricing_id_fk",
					'tiered_pricing_id',
					$parent,
					'id'
				)
			);

			$this->assertNotContains(
				'tiered_pricing_id',
				$this->index_names( $table ),
				'Fixture left an index of the column\'s own name behind, so the run below proves nothing.'
			);
		}
	}

	/**
	 * Assert the fixture is in the state the migration is supposed to find.
	 */
	private function assert_foreign_keys_are_present() {
		foreach ( $this->children as $table ) {
			$this->assertNotEmpty(
				$this->foreign_key_names( $table ),
				"Precondition: {$table} has to carry a foreign key for the drop to prove anything."
			);
		}
	}

	/**
	 * The migration's whole job. Both children, not just the first one the loop reaches.
	 */
	public function testExecuteDropsTheForeignKeyOnBothChildren() {
		$this->assert_foreign_keys_are_present();

		$this->get_instance()->execute();

		foreach ( $this->children as $table ) {
			$this->assertSame( [], $this->foreign_key_names( $table ) );
		}
	}

	/**
	 * The name is read out of the DDL rather than rebuilt because existing installs carry whatever their
	 * server generated. A migration matching on the derived name finds nothing on those and leaves the
	 * constraint in place for good, since the cursor never re-runs an id.
	 */
	public function testExecuteDropsAForeignKeyTheServerNamed() {
		$this->rename_foreign_keys_to_whatever_the_server_picks();
		$this->assert_foreign_keys_are_present();

		$this->get_instance()->execute();

		foreach ( $this->children as $table ) {
			$this->assertSame( [], $this->foreign_key_names( $table ) );
		}
	}

	/**
	 * The index goes in before the constraint comes out, so a request that died between the two cannot leave
	 * the column unindexed. Asserted on the statements issued rather than on the end state, which is the same
	 * either way here: MariaDB keeps the constraint's own index when the constraint goes.
	 */
	public function testExecuteIndexesTheColumnBeforeDroppingTheConstraint() {
		$this->leave_the_column_indexed_only_by_the_constraint();

		$statements = [];
		$recorder   = function ( string $query ) use ( &$statements ): string {
			$statements[] = $query;
			return $query;
		};

		add_filter( 'query', $recorder );
		$this->get_instance()->execute();
		remove_filter( 'query', $recorder );

		foreach ( $this->children as $table ) {
			$steps = [];
			foreach ( $statements as $sql ) {
				if ( false === strpos( $sql, "`{$table}`" ) ) {
					continue;
				}
				if ( 1 === preg_match( '/\bADD INDEX\b/i', $sql ) ) {
					$steps[] = 'index';
				}
				if ( 1 === preg_match( '/\bDROP FOREIGN KEY\b/i', $sql ) ) {
					$steps[] = 'drop';
				}
			}

			$this->assertSame( [ 'index', 'drop' ], $steps, "{$table} was not indexed before its constraint was dropped." );
		}
	}

	/**
	 * The case the added index exists for. On an install whose only index on the column is the one the
	 * server built for the constraint, dropping the constraint takes the index with it and every query the
	 * repository makes on the column falls back to a full scan.
	 */
	public function testExecuteIndexesTheColumnWhenOnlyTheConstraintCoveredIt() {
		$this->leave_the_column_indexed_only_by_the_constraint();

		$this->get_instance()->execute();

		foreach ( $this->children as $table ) {
			$this->assertSame( [], $this->foreign_key_names( $table ) );
			$this->assertContains( 'tiered_pricing_id', $this->index_names( $table ) );
		}
	}

	/**
	 * A migration has to tolerate being re-entered: the cursor advances only once execute() returns, so a
	 * request that died between the two tables replays the first one on the next attempt. A second run must
	 * match nothing rather than raise a database error, which wpdb echoes while WP_DEBUG is on.
	 */
	public function testExecuteIsSafeToRunTwice() {
		$migration = $this->get_instance();
		$migration->execute();

		ob_start();
		$migration->execute();
		$this->assertSame( '', (string) ob_get_clean(), 'The second run raised a database error.' );

		foreach ( $this->children as $table ) {
			$this->assertSame( [], $this->foreign_key_names( $table ) );
			$this->assertContains( 'tiered_pricing_id', $this->index_names( $table ) );
		}
	}

	/**
	 * A missing table, a denied privilege and a drop-in that could not answer are indistinguishable from
	 * here, and none of them means the constraint is gone. Returning quietly would advance the cursor and
	 * strand the install with a constraint no later release can reach.
	 */
	public function testExecuteThrowsWhenTheSchemaCannotBeRead() {
		$this->drop_tables();

		// SHOW CREATE TABLE against a table that is not there is the error being provoked, and wpdb echoes
		// database errors while WP_DEBUG is on.
		$this->wpdb->suppress_errors( true );

		$this->expectException( \RuntimeException::class );

		$this->get_instance()->execute();
	}
}
