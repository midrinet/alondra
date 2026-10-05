<?php
/**
 * Common methods for database datamappers
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Domain/Datamapper
 */

namespace Midrinet\Alondra\Domain\Datamapper;

use Midrinet\Alondra\Infrastructure\DI\Container;
use Midrinet\Alondra\Infrastructure\Wp\UpgradeWrapper;

/**
 * Common methods for database datamappers.
 * Also provides tables and columns constants to be used by child classes.
 *
 * @template T
 */
abstract class DatabaseDatamapper {

	// Tables. Public because uninstall.php has to name them without building the container.
	public const TABLE_RULES          = 'alondra_rules';
	public const TABLE_TIERS          = 'alondra_tiers';
	public const TABLE_TIERED_PRICING = 'alondra_tiered_pricing';

	/**
	 * Used in tables: rules, tiers, tiered_pricing
	 */
	protected const COL_ID = 'id';

	/**
	 * Used in tables: rule_categories, rule_products, rule_roles, rule_tags
	 */
	protected const COL_RULE_ID = 'rule_id';

	/**
	 * Used in tables: rule_categories
	 */
	protected const COL_CAT_ID = 'cat_id';

	/**
	 * Used in tables: rule_products
	 */
	protected const COL_PRODUCT_ID = 'product_id';

	/**
	 * Used in tables: rule_roles
	 */
	protected const COL_ROLE = 'role';

	/**
	 * Used in tables: rule_tags
	 */
	protected const COL_TAG_ID = 'tag_id';

	/**
	 * Used in tables: rule_users
	 */
	protected const COL_USER_ID = 'user_id';

	/**
	 * Used in tables: tiered_pricing
	 */
	protected const COL_TITLE        = 'title';
	protected const COL_PRIORITY     = 'priority';
	protected const COL_STATUS       = 'status';
	protected const COL_DATE_UPDATED = 'date_updated';

	/**
	 * Used in tables: tiers, rules
	 */
	protected const COL_TIERED_PRICING_ID = 'tiered_pricing_id';

	/**
	 * Used in tables: rules
	 */
	protected const COL_ROLES_REL                            = 'roles_rel';
	protected const COL_CATS_REL                             = 'cats_rel';
	protected const COL_TAGS_REL                             = 'tags_rel';
	protected const COL_TAGS_WITH_CATS_REL                   = 'tags_with_cats_rel';
	protected const COL_PRODS_CATS_TAGS_WITH_ROLES_USERS_REL = 'prods_cats_tags_with_roles_users_rel';

	/**
	 * Used in tables: rules
	 *
	 * Holds "<bundle_id>:<product_id>" pairs, not ids, hence no _id suffix. Storing the pair rather than
	 * Product Bundles' own bundled_item_id is deliberate: removing and re-adding a product to a bundle mints
	 * a new bundled item row, which would orphan every rule pointing at the old one.
	 */
	protected const COL_BUNDLE_PRODUCT = 'bundle_product';

	/**
	 * Used in tables: tiers
	 */
	protected const COL_MIN_UNITS = 'min_units';
	protected const COL_MAX_UNITS = 'max_units';
	protected const COL_IS_FIXED  = 'is_fixed';
	protected const COL_VALUE     = 'value';

	/**
	 * Get table name if any
	 *
	 * @return string
	 */
	abstract public function table();

	/**
	 * Set relationships for entity if any
	 *
	 * @param T $entity Entity to set relationships.
	 * @return T Entity with relationships if any.
	 */
	abstract public function set_relationships( $entity );

	/**
	 * Map array of data to entities
	 *
	 * @param array<array<string, mixed>|object> $data               Array of Data to map.
	 * @param bool                               $with_relationships Whether to map relationships.
	 * @return T[]
	 */
	public function map_all( $data, $with_relationships = false ) {
		$entities = [];
		if ( empty( $data ) || ! \is_array( $data ) ) {
			return $entities;
		}
		foreach ( $data as $entity_data ) {
			// map() is null only for an empty row, which a result set never holds.
			/** @var T $entity */
			$entity     = $this->map( $entity_data, $with_relationships );
			$entities[] = $entity;
		}
		return $entities;
	}

	protected function wpdb(): \wpdb {
		return Container::instance()->get( \wpdb::class );
	}

	protected function upgrade_wrapper(): UpgradeWrapper {
		return Container::instance()->get( UpgradeWrapper::class );
	}

	/**
	 * Get table name with prefix.
	 *
	 * @param string $table_name Table name. Use constans in this class.
	 * @return string
	 */
	protected function get_table( $table_name ) {
		return $this->wpdb()->prefix . $table_name;
	}

	/**
	 * Whether the live table matches the declared schema.
	 *
	 * Existence is asked of the server, shape is asked of dbDelta as a dry run ($execute = false).
	 * A table that is present but has drifted -- a column the declaration gained, a type that
	 * changed -- proposes a change and is reported as a failure, which a name lookup cannot see.
	 *
	 * The "Created table" filter is load-bearing, do not drop it. WordPress up to 6.8, the
	 * supported floor, keys $cqueries on the unbackticked table name but $for_update on the
	 * backticked one, so the unset that clears an up-to-date table misses the second and the
	 * return value keeps a cosmetic "Created table `wp_...`" for a table it did not create.
	 * Without the filter every up-to-date install on 6.8 reports drift. The entry is the same
	 * string on 7.1, where it appears only for a table that really is absent -- which is why the
	 * existence check stays in front: filtering the phantom costs the dry run its one signal for
	 * an absent table, so absence is established before the dry run is asked about shape.
	 *
	 * @param string $sql CREATE TABLE statement, as handed to dbDelta.
	 * @return bool
	 */
	protected function is_table_up_to_date( $sql ) {
		$wpdb = $this->wpdb();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema lookup, no core API for it and a cached answer would be worse than none.
		if ( $this->table() !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $this->table() ) ) ) ) {
			return false;
		}

		foreach ( $this->upgrade_wrapper()->db_delta( $sql, false ) as $change ) {
			if ( 0 !== strpos( $change, 'Created table' ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Map array to entity
	 *
	 * @param array<string, mixed> $data Data to map.
	 * @param bool                 $with_relationships Whether to map relationships.
	 * @return T
	 */
	abstract protected function map_array( $data, $with_relationships );

	/**
	 * Map object to entity
	 *
	 * @param object $data Data to map.
	 * @param bool   $with_relationships Whether to map relationships.
	 * @return T
	 */
	abstract protected function map_object( $data, $with_relationships );

	/**
	 * Map data to entity
	 *
	 * @param array<string, mixed>|object $data Data to map.
	 * @param bool                        $with_relationships Whether to map relationships.
	 * @return T|null
	 */
	public function map( $data, $with_relationships = false ) {
		if ( empty( $data ) ) {
			return null;
		}
		$entity = \is_array( $data ) ? $this->map_array( $data, $with_relationships ) : $this->map_object( $data, $with_relationships );
		if ( $with_relationships ) {
			$entity = $this->set_relationships( $entity );
		}
		return $entity;
	}

	/**
	 * Count entities
	 *
	 * @param string|null $sub_query Optional sub query to count. Must be prepared.
	 * @return int
	 */
	public function count( $sub_query = null ) {
		$wpdb      = $this->wpdb();
		$sub_query = $sub_query ?? '';
		$from      = empty( $sub_query ) ? $wpdb->prepare( '%i', $this->table() ) : "({$sub_query}) AS `sub_query`";
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $from is either a prepared identifier or a sub query the caller prepared.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $from" );
	}

	/**
	 * Get all entities
	 *
	 * @param bool $with_relationships Whether to map relationships.
	 *
	 * @return T[]
	 */
	public function all( $with_relationships = false ) {
		$wpdb = $this->wpdb();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, no core API for it and nothing caches these rows.
		$results = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i', $this->table() ) );
		return $this->map_all( $results ?? [], $with_relationships );
	}

	/**
	 * Helper to construct a prepared where clause for serialized columns.
	 *
	 * @param array<int|string, mixed> $match_any Match any of the following columns. Must be an array of arrays, each array containing the following structure:
	 * - column_name => array of values to match. Will be serialized before matching.
	 * - column_name => value to match. Will be compared as is.
	 * @return string
	 */
	protected function get_where_for_serialized( $match_any = [] ) {
		$wpdb  = $this->wpdb();
		$where = '';
		if ( ! empty( $match_any ) ) {
			$where = 'AND (';
			foreach ( $match_any as $col_name => $values ) {
				if ( empty( $values ) ) {
					continue;
				}

				if ( \is_array( $values ) ) {
					$where .= '(';
					foreach ( $values as $value ) {
							// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
							$where .= $wpdb->prepare( '%i LIKE %s', $col_name, "%{$wpdb->esc_like( serialize( $value ) )}%" );
							$where .= ' OR ';
					}
					$where  = rtrim( $where, ' OR ' );
					$where .= ')';
				} elseif ( \is_string( $values ) ) {
					$where .= $wpdb->prepare( '%i LIKE %s', $col_name, "%{$wpdb->esc_like( $values )}%" );
				} else {
					$where .= $wpdb->prepare( '%i = %s', $col_name, "%{$wpdb->esc_like( \is_scalar( $values ) ? (string) $values : '' )}%" );
				}
				$where .= ' OR ';
			}
			$where = rtrim( $where, ' OR ' );
			// Every column had an empty list: no clause rather than an invalid `AND ()`.
			$where = 'AND (' === $where ? '' : $where . ')';
		}
		return $where;
	}

	/**
	 * Find entity by ID
	 *
	 * @param int  $entity_id Entity ID to find.
	 * @param bool $with_relationships Whether to map relationships.
	 *
	 * @return T|null
	 */
	public function find( $entity_id, $with_relationships = false ) {
		$wpdb     = $this->wpdb();
		$entities = $this->map_all(
			$wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE %i = %d LIMIT 1',
					$this->table(),
					$this->col_id(),
					(int) $entity_id
				)
			) ?? []
		);
		if ( empty( $entities ) ) {
			return null;
		}
		return $with_relationships ? $this->set_relationships( $entities[0] ) : $entities[0];
	}

	/**
	 * Delete every row belonging to a Tiered Pricing
	 *
	 * @param int $id Tiered Pricing ID.
	 * @return int|false Number of deleted rows or false on error.
	 */
	public function delete_by_tiered_pricing( $id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, no core API for it and nothing caches these rows.
		return $this->wpdb()->delete(
			$this->table(),
			[
				self::COL_TIERED_PRICING_ID => (int) $id,
			],
			[ '%d' ]
		);
	}

	/**
	 * Column name id attribute
	 *
	 * @return string
	 */
	public function col_id() {
		return self::COL_ID;
	}
}
