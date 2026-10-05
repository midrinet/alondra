<?php
/**
 * The foreign-key removal Migration
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Infrastructure/Migration
 */

namespace Midrinet\Alondra\Infrastructure\Migration;

use Midrinet\Alondra\Infrastructure\DI\Container;

/**
 * Drops the ON DELETE CASCADE constraints the two child tables used to carry.
 *
 * The constraint name is read out of SHOW CREATE TABLE rather than constructed: existing installs carry
 * whatever the server generated, which differs between MariaDB and MySQL. SHOW CREATE TABLE rather than
 * information_schema because a drop-in database layer such as HyperDB routes an information_schema query
 * to the wrong server.
 */
class DropForeignKeysMigration implements Migration {

	/**
	 * Referencing column the constraints are declared on, which is also the name of the index that replaces them
	 */
	private const COLUMN = 'tiered_pricing_id';

	private function wpdb(): \wpdb {
		return Container::instance()->get( \wpdb::class );
	}

	/**
	 * Drop the constraints, keeping the join column indexed.
	 *
	 * Safe to re-enter, as the interface requires: a second run reads a DDL that no longer carries what the
	 * first one dropped and matches nothing.
	 *
	 * @return void
	 * @throws \RuntimeException When the schema cannot be read or an ALTER is refused.
	 */
	public function execute(): void {
		$wpdb = $this->wpdb();

		$ref_table = "{$wpdb->prefix}alondra_tiered_pricing";

		foreach ( [ 'alondra_tiers', 'alondra_rules' ] as $name ) {
			$table = "{$wpdb->prefix}{$name}";

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema lookup, no core API for it and a cached answer would be worse than none.
			$ddl = $wpdb->get_var( (string) $wpdb->prepare( 'SHOW CREATE TABLE %i', $table ), 1 );

			// A missing table, a denied privilege and a drop-in that could not answer are indistinguishable
			// here, and the cursor never re-runs an id, so reading any of them as "already clean" would be
			// permanent. Throwing leaves the retry to the runner's lock.
			if ( null === $ddl ) {
				throw new \RuntimeException( 'Could not read the schema of ' . esc_html( $table ) . '.' );
			}

			$this->keep_column_indexed( $table, $ddl );
			$this->drop_foreign_keys( $table, $ref_table, $ddl );
		}
	}

	/**
	 * Add an index on the referencing column unless one already covers it.
	 *
	 * Runs before the drop so the column is covered at every point in between, rather than relying on whether
	 * a given server keeps the index it created for the constraint.
	 *
	 * @param string $table Prefixed table name.
	 * @param string $ddl   Table's SHOW CREATE TABLE output.
	 * @return void
	 * @throws \RuntimeException When the ALTER is refused.
	 */
	private function keep_column_indexed( string $table, string $ddl ): void {
		$wpdb = $this->wpdb();

		if ( 1 === preg_match( '/KEY\s+`' . preg_quote( self::COLUMN, '/' ) . '`/i', $ddl ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema change, no core API for it and nothing to cache.
		$done = $wpdb->query(
			(string) $wpdb->prepare( 'ALTER TABLE %i ADD INDEX %i (%i)', $table, self::COLUMN, self::COLUMN )
		);

		if ( false === $done ) {
			throw new \RuntimeException( 'Could not index ' . esc_html( self::COLUMN ) . ' on ' . esc_html( $table ) . '.' );
		}
	}

	/**
	 * Drop every constraint on the referencing column.
	 *
	 * Uses preg_match_all rather than preg_match: an install can carry more than one, and a DROP FOREIGN KEY IF
	 * EXISTS or a DROP CONSTRAINT that would spare the lookup is not portable across the servers this runs on.
	 *
	 * @param string $table     Prefixed table name.
	 * @param string $ref_table Prefixed referenced table name.
	 * @param string $ddl       Table's SHOW CREATE TABLE output.
	 * @return void
	 * @throws \RuntimeException When an ALTER is refused.
	 */
	private function drop_foreign_keys( string $table, string $ref_table, string $ddl ): void {
		$wpdb = $this->wpdb();

		// Whitespace around REFERENCES is not guaranteed: MariaDB echoes a space, other servers none.
		$shape = sprintf(
			'/CONSTRAINT\s+`([^`]+)`\s+FOREIGN KEY\s*\(`%s`\)\s*REFERENCES\s*`%s`\s*\(`id`\)/i',
			preg_quote( self::COLUMN, '/' ),
			preg_quote( $ref_table, '/' )
		);

		preg_match_all( $shape, $ddl, $matches );

		foreach ( $matches[1] as $constraint ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema change, no core API for it and nothing to cache.
			$done = $wpdb->query(
				(string) $wpdb->prepare( 'ALTER TABLE %i DROP FOREIGN KEY %i', $table, $constraint )
			);

			if ( false === $done ) {
				throw new \RuntimeException( 'Could not drop ' . esc_html( $constraint ) . ' from ' . esc_html( $table ) . '.' );
			}
		}
	}
}
