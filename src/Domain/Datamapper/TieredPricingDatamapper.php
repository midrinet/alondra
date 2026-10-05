<?php
/**
 * Tiered pricing datamapper
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Domain/Datamapper
 */

namespace Midrinet\Alondra\Domain\Datamapper;

use Midrinet\Alondra\Domain\Entity\TieredPricing;
use Midrinet\Alondra\Infrastructure\DI\Container;

/**
 * Tiered pricing datamapper
 *
 * @extends DatabaseDatamapper<TieredPricing>
 */
class TieredPricingDatamapper extends DatabaseDatamapper {

	private function tier_datamapper(): TierDatamapper {
		return Container::instance()->get( TierDatamapper::class );
	}

	private function rule_datamapper(): RuleDatamapper {
		return Container::instance()->get( RuleDatamapper::class );
	}

	/**
	 * Get table name
	 *
	 * @return string
	 */
	public function table() {
		return parent::get_table( parent::TABLE_TIERED_PRICING );
	}

	/**
	 * Column name for TieredPricing title attribute
	 *
	 * @return string
	 */
	public function col_title() {
		return parent::COL_TITLE;
	}

	/**
	 * Column name for TieredPricing priority attribute
	 *
	 * @return string
	 */
	public function col_priority() {
		return parent::COL_PRIORITY;
	}

	/**
	 * Column name for TieredPricing status attribute
	 *
	 * @return string
	 */
	public function col_date_updated() {
		return parent::COL_DATE_UPDATED;
	}

	/**
	 * Column name for TieredPricing status attribute
	 *
	 * @return string
	 */
	public function col_status() {
		return parent::COL_STATUS;
	}

	/**
	 * Save entity. If entity has ID, update, otherwise insert.
	 *
	 * @param TieredPricing $entity Entity to save.
	 * @return TieredPricing|null
	 */
	public function save( $entity ) {
		$result = null;

		$entity->date_updated = gmdate( 'Y-m-d H:i:s' );

		if ( empty( $entity->id ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, no core API for it and nothing caches these rows.
			$result = $this->wpdb()->insert(
				$this->table(),
				[
					$this->col_title()        => $entity->title,
					$this->col_priority()     => $entity->priority,
					$this->col_status()       => $entity->status,
					$this->col_date_updated() => $entity->date_updated,
				],
				[ '%s', '%d', '%s', '%s' ]
			);
			if ( ! empty( $result ) ) {
				$entity->id = (int) $this->wpdb()->insert_id;
			}
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, no core API for it and nothing caches these rows.
			$result = $this->wpdb()->update(
				$this->table(),
				[
					$this->col_title()        => $entity->title,
					$this->col_priority()     => $entity->priority,
					$this->col_status()       => $entity->status,
					$this->col_date_updated() => $entity->date_updated,
				],
				[ $this->col_id() => $entity->id ],
				[ '%s', '%d', '%s', '%s' ],
				[ '%d' ]
			);
		}
		// update() returns affected rows: 0 means the row already held these values, not an error.
		if ( false === $result ) {
			return null;
		}
		foreach ( $entity->tiers as &$tier ) {
			$tier->tiered_pricing_id = $entity->id;
			// The datamapper hands back the same instance; null means the write failed and must
			// not replace the tier held in memory.
			$saved = $this->tier_datamapper()->save( $tier );
			if ( $saved ) {
				$tier = $saved;
			}
		}
		foreach ( $entity->rules as &$rule ) {
			$rule->tiered_pricing_id = $entity->id;
			$saved                   = $this->rule_datamapper()->save( $rule );
			if ( $saved ) {
				$rule = $saved;
			}
		}
		return $entity;
	}

	/**
	 * Delete entity
	 *
	 * @param TieredPricing|int $entity Entity ID or instance to delete.
	 * @return bool
	 */
	public function delete( $entity ) {
		$entity_id = $entity instanceof TieredPricing ? $entity->id : (int) $entity;
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
	 * @return TieredPricing
	 */
	protected function map_array( $data, $with_relationships ) {
		return new TieredPricing(
			(int) $data[ $this->col_id() ],
			(string) $data[ $this->col_title() ],
			(int) $data[ $this->col_priority() ],
			(string) $data[ $this->col_status() ],
			(string) $data[ $this->col_date_updated() ]
		);
	}

	/**
	 * Map object to entity
	 *
	 * @param object $data Data to map.
	 * @param bool   $with_relationships Whether to map relationships.
	 * @return TieredPricing
	 */
	protected function map_object( $data, $with_relationships ) {
		return new TieredPricing(
			(int) $data->{ $this->col_id() },
			$data->{ $this->col_title() },
			(int) $data->{ $this->col_priority() },
			$data->{ $this->col_status() },
			$data->{ $this->col_date_updated() }
		);
	}

	/**
	 * Set relationships for entity
	 *
	 * @param TieredPricing $entity Entity to set relationships.
	 * @return TieredPricing
	 */
	public function set_relationships( $entity ) {
		$this->set_tiers( $entity );
		$this->set_rules( $entity );
		return $entity;
	}

	/**
	 * Set rules for entity
	 *
	 * @param TieredPricing           $entity Entity to set relationships.
	 * @param array<int|string, mixed> $match_any Optional. Match any rules. Default empty array. See RuleDatamapper::find_by_tiered_pricing().
	 * @return TieredPricing
	 */
	public function set_rules( &$entity, $match_any = [] ) {
		$entity->rules = $this->rule_datamapper()->find_by_tiered_pricing( $entity->id, $match_any );
		return $entity;
	}

	/**
	 * Set tiers for entity
	 *
	 * @param TieredPricing $entity Entity to set relationships.
	 * @return TieredPricing
	 */
	public function set_tiers( &$entity ) {
		$entity->tiers = $this->tier_datamapper()->find_by_tiered_pricing( $entity->id );
		return $entity;
	}

	/**
	 * Set up table in database
	 *
	 * @return bool
	 */
	public function set_up() {
		$wpdb = $this->wpdb();
		$sql  = $wpdb->prepare(
			// One column per line and lowercase types with display widths: dbDelta splits on newlines and compares types case-sensitively until WP 6.9.
			"CREATE TABLE %i (\n"
			. "%i bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "%i varchar(255) NOT NULL,\n"
			. "%i int(10) unsigned NOT NULL,\n"
			. "%i datetime NOT NULL,\n"
			. "%i varchar(20) NOT NULL,\n"
			. "PRIMARY KEY  (%i)\n)",
			$this->table(),
			$this->col_id(),
			$this->col_title(),
			$this->col_priority(),
			$this->col_date_updated(),
			$this->col_status(),
			$this->col_id()
		) . " {$wpdb->get_charset_collate()}";
		$this->upgrade_wrapper()->db_delta( $sql );

		return $this->is_table_up_to_date( $sql );
	}

	/**
	 * Get all published entities matching any rules
	 *
	 * @param array<int|string, mixed> $match_any Match any rules. Default empty array. See RuleDatamapper::find_by_tiered_pricing().
	 * @param string|null              $status Optional. Status to match. Pass null for any status or use class constants in TieredPricing.
	 * @param string|string[]|null     $order_by Optional. Order by column(s), applied in the given order, or column => 'ASC'|'DESC'. Default null.
	 * @param string|null              $order Optional. Order direction, applied to every column in $order_by listed without one. Default null.
	 * @param int|null                 $limit Optional. Limit. Default null.
	 * @param int|null                 $offset Optional. Offset. Default null.
	 * @param bool                     $with_relationships Optional. Whether to include relationships. Default false.
	 *
	 * @return TieredPricing[]
	 */
	public function all_matching( $match_any = [], $status = null, $order_by = null, $order = null, $limit = null, $offset = null, $with_relationships = false ) {
		$limit_offset = ! empty( $limit ) ? $this->wpdb()->prepare( 'LIMIT %d', (int) $limit ) : '';
		if ( ! empty( $offset ) && ! empty( $limit_offset ) ) {
			$limit_offset .= $this->wpdb()->prepare( ' OFFSET %d', (int) $offset );
		}
		$order_dir     = $order ? ' ' . esc_sql( (string) $order ) : '';
		$order_columns = [];
		foreach ( empty( $order_by ) ? [] : (array) $order_by as $key => $col ) {
			$dir = $order_dir;
			if ( \is_string( $key ) ) {
				$dir = 'DESC' === strtoupper( (string) $col ) ? ' DESC' : ' ASC';
				$col = $key;
			}
			$order_columns[] = $this->wpdb()->prepare( '%i.%i', $this->table(), (string) $col ) . $dir;
		}
		$order_by = empty( $order_columns ) ? '' : 'ORDER BY ' . implode( ', ', $order_columns );

		$query = $this->get_all_matching_query( $match_any, $status ) . " $order_by $limit_offset";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $query is prepared.
		return $this->map_all( $this->wpdb()->get_results( $query ) ?? [], $with_relationships );
	}

	/**
	 * Count entities matching rules
	 *
	 * @param array<int|string, mixed> $match_any Match any rules. Default empty array. See RuleDatamapper::find_by_tiered_pricing().
	 * @param string|null              $status Optional. Status to match. Pass null for any status or use class constants in TieredPricing.
	 *
	 * @return int
	 */
	public function count_all_matching( $match_any = [], $status = null ) {
		return $this->count( $this->get_all_matching_query( $match_any, $status ) );
	}

	/**
	 * Helper to get a prepared query used in all_matching() and count_all_matching()
	 *
	 * @param array<int|string, mixed> $match_any Match any rules. Default empty array. See RuleDatamapper::find_by_tiered_pricing().
	 * @param string|null              $status Optional. Status to match. Pass null for any status or use class constants in TieredPricing.
	 * @return string
	 */
	protected function get_all_matching_query( $match_any = [], $status = null ) {
		$wpdb      = $this->wpdb();
		$from      = $wpdb->prepare( 'FROM %i', $this->table() );
		$status_in = '';
		foreach ( empty( $status ) ? [ TieredPricing::STATUS_PUBLISH, TieredPricing::STATUS_DRAFT ] : [ $status ] as $s ) {
			$prepared_s = $wpdb->prepare( '%s', $s );
			$status_in .= '' === $status_in ? $prepared_s : ",$prepared_s";
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $status_in is prepared above.
		$where            = $wpdb->prepare( "WHERE %i.%i IN ($status_in)", $this->table(), $this->col_status() ); // @phpstan-ignore argument.type
		$group_by         = '';
		$where_serialized = $this->get_where_for_serialized( $match_any );
		if ( ! empty( $where_serialized ) ) {
			$from    .= $wpdb->prepare(
				' LEFT JOIN %i ON %i.%i = %i.%i', 
				$this->rule_datamapper()->table(),
				$this->rule_datamapper()->table(),
				$this->rule_datamapper()->col_tiered_pricing_id(),
				$this->table(),
				$this->col_id() 
			);
			$where    = "$where $where_serialized";
			$group_by = $wpdb->prepare( 'GROUP BY %i.%i', $this->table(), $this->col_id() );
		}

		return $wpdb->prepare( 'SELECT %i.*', $this->table() ) . " $from $where $group_by";
	}
}
