<?php
/**
 * Tier datamapper
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Domain/Datamapper
 */

namespace Midrinet\Alondra\Domain\Datamapper;

use Midrinet\Alondra\Domain\Entity\Tier;

/**
 * Tier datamapper
 *
 * @extends DatabaseDatamapper<Tier>
 */
class TierDatamapper extends DatabaseDatamapper {

	/**
	 * Get table name
	 *
	 * @return string
	 */
	public function table() {
		return parent::get_table( parent::TABLE_TIERS );
	}

	/**
	 * Column name for Tier tiered_pricing_id attribute
	 *
	 * @return string
	 */
	public function col_tiered_pricing_id() {
		return parent::COL_TIERED_PRICING_ID;
	}

	/**
	 * Column name for Tier min_units attribute
	 *
	 * @return string
	 */
	public function col_min_units() {
		return parent::COL_MIN_UNITS;
	}

	/**
	 * Column name for Tier max_units attribute
	 *
	 * @return string
	 */
	public function col_max_units() {
		return parent::COL_MAX_UNITS;
	}

	/**
	 * Column name for Tier is_fixed attribute
	 *
	 * @return string
	 */
	public function col_is_fixed() {
		return parent::COL_IS_FIXED;
	}

	/**
	 * Column name for Tier value attribute
	 *
	 * @return string
	 */
	public function col_value() {
		return parent::COL_VALUE;
	}

	/**
	 * Save entity. If entity has ID, update, otherwise insert.
	 *
	 * @param Tier $entity Entity to save.
	 * @return Tier|null
	 */
	public function save( $entity ) {
		$result = null;

		if ( empty( $entity->id ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, no core API for it and nothing caches these rows.
			$result = $this->wpdb()->insert(
				$this->table(),
				[
					$this->col_tiered_pricing_id() => $entity->tiered_pricing_id,
					$this->col_min_units()         => $entity->min_units,
					$this->col_max_units()         => $entity->max_units,
					$this->col_is_fixed()          => $entity->is_fixed,
					$this->col_value()             => $entity->value,
				],
				[ '%d', '%d', '%d', '%d', '%f' ]
			);
			if ( $result ) {
				$entity->id = (int) $this->wpdb()->insert_id;
			}
		} else {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, no core API for it and nothing caches these rows.
			$result = $this->wpdb()->update(
				$this->table(),
				[
					$this->col_tiered_pricing_id() => $entity->tiered_pricing_id,
					$this->col_min_units()         => $entity->min_units,
					$this->col_max_units()         => $entity->max_units,
					$this->col_is_fixed()          => $entity->is_fixed,
					$this->col_value()             => $entity->value,
				],
				[ $this->col_id() => $entity->id ],
				[ '%d', '%d', '%d', '%d', '%f' ],
				[ '%d' ]
			);
		}
		// update() returns affected rows: 0 means the row already held these values, not an error.
		return false === $result ? null : $entity;
	}

	/**
	 * Delete entity
	 *
	 * @param Tier|int $entity Entity ID or instance to delete.
	 * @return bool
	 */
	public function delete( $entity ) {
		$entity_id = $entity instanceof Tier ? $entity->id : $entity;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, no core API for it and nothing caches these rows.
		return (bool) $this->wpdb()->delete(
			$this->table(),
			[
				$this->col_id() => $entity_id,
			],
			[ '%d' ]
		);
	}

	/**
	 * Map array to entity
	 *
	 * @param array<string, scalar|null> $data Data to map.
	 * @param bool                       $with_relationships Whether to map relationships.
	 * @return Tier
	 */
	protected function map_array( $data, $with_relationships ) {
		return new Tier(
			(int) $data[ $this->col_id() ],
			(int) $data[ $this->col_tiered_pricing_id() ],
			(int) $data[ $this->col_min_units() ],
			(int) $data[ $this->col_max_units() ],
			(bool) $data[ $this->col_is_fixed() ],
			(float) $data[ $this->col_value() ]
		);
	}

	/**
	 * Map object to entity
	 *
	 * @param object $data Data to map.
	 * @param bool   $with_relationships Whether to map relationships.
	 * @return Tier
	 */
	protected function map_object( $data, $with_relationships ) {
		return new Tier(
			(int) $data->{ $this->col_id() },
			(int) $data->{ $this->col_tiered_pricing_id() },
			(int) $data->{ $this->col_min_units() },
			(int) $data->{ $this->col_max_units() },
			(bool) $data->{ $this->col_is_fixed() },
			(float) $data->{ $this->col_value() }
		);
	}

	/**
	 * Find tiers by tiered pricing id
	 *
	 * @param int $tiered_pricing_id Tier pricing id.
	 * @return Tier[]
	 */
	public function find_by_tiered_pricing( $tiered_pricing_id ) {
		$wpdb     = $this->wpdb();
		$entities = $this->map_all(
			$wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE %i = %d',
					$this->table(),
					$this->col_tiered_pricing_id(),
					(int) $tiered_pricing_id
				)
			) ?? []
		);
		return $entities;
	}

	/**
	 * Unimplemented. Set relationships for entity
	 *
	 * @param Tier $entity Entity to set relationships.
	 * @return Tier
	 */
	public function set_relationships( $entity ) {
		return $entity;
	}

	/**
	 * Set up table in database
	 *
	 * @return bool
	 */
	public function set_up() {
		$wpdb = $this->wpdb();

		// The index is declared here because find_by_tiered_pricing() filters on that column.
		$sql = $wpdb->prepare(
			// One column per line and lowercase types with display widths: dbDelta splits on newlines and compares types case-sensitively until WP 6.9.
			"CREATE TABLE %i (\n"
			. "%i bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "%i bigint(20) unsigned NOT NULL,\n"
			. "%i int(10) unsigned NOT NULL,\n"
			. "%i int(10) unsigned NOT NULL,\n"
			. "%i tinyint(1) NOT NULL,\n"
			. "%i float NOT NULL,\n"
			. "PRIMARY KEY  (%i),\n"
			. "KEY %i (%i)\n)",
			$this->table(),
			$this->col_id(),
			$this->col_tiered_pricing_id(),
			$this->col_min_units(),
			$this->col_max_units(),
			$this->col_is_fixed(),
			$this->col_value(),
			$this->col_id(),
			$this->col_tiered_pricing_id(),
			$this->col_tiered_pricing_id()
		) . " {$wpdb->get_charset_collate()}";
		$this->upgrade_wrapper()->db_delta( $sql );

		return $this->is_table_up_to_date( $sql );
	}
}
