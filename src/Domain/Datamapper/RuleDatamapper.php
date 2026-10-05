<?php
/**
 * Rule datamapper
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Domain/Datamapper
 */

namespace Midrinet\Alondra\Domain\Datamapper;

use Midrinet\Alondra\Domain\Entity\Rule;

/**
 * Rule datamapper
 *
 * @extends DatabaseDatamapper<Rule>
 */
class RuleDatamapper extends DatabaseDatamapper {

	/**
	 * Get table name
	 *
	 * @return string
	 */
	public function table() {
		return parent::get_table( parent::TABLE_RULES );
	}

	/**
	 * Column name for Rule tiered_pricing_id
	 *
	 * @return string
	 */
	public function col_tiered_pricing_id() {
		return self::COL_TIERED_PRICING_ID;
	}

	/**
	 * Column name for Rule tag
	 *
	 * @return string
	 */
	public function col_tag() {
		return parent::COL_TAG_ID;
	}

	/**
	 * Column name for Rule category
	 *
	 * @return string
	 */
	public function col_category() {
		return parent::COL_CAT_ID;
	}

	/**
	 * Column name for Rule product
	 *
	 * @return string
	 */
	public function col_product() {
		return parent::COL_PRODUCT_ID;
	}

	/**
	 * Column name for Rule user
	 *
	 * @return string
	 */
	public function col_user() {
		return parent::COL_USER_ID;
	}

	/**
	 * Column name for Rule role
	 *
	 * @return string
	 */
	public function col_role() {
		return parent::COL_ROLE;
	}

	/**
	 * Column name for Rule bundle_product
	 *
	 * @return string
	 */
	public function col_bundle_product() {
		return parent::COL_BUNDLE_PRODUCT;
	}

	/**
	 * Column name for Rule roles_rel
	 *
	 * @return string
	 */
	public function col_roles_rel() {
		return self::COL_ROLES_REL;
	}

	/**
	 * Column name for Rule cats_rel
	 *
	 * @return string
	 */
	public function col_cats_rel() {
		return self::COL_CATS_REL;
	}

	/**
	 * Column name for Rule tags_rel
	 *
	 * @return string
	 */
	public function col_tags_rel() {
		return self::COL_TAGS_REL;
	}

	/**
	 * Column name for Rule tags_with_cats_rel
	 *
	 * @return string
	 */
	public function col_tags_with_cats_rel() {
		return self::COL_TAGS_WITH_CATS_REL;
	}

	/**
	 * Column name for Rule prods_cats_tags_with_roles_users_rel
	 *
	 * @return string
	 */
	public function col_prods_cats_tags_with_roles_users_rel() {
		return self::COL_PRODS_CATS_TAGS_WITH_ROLES_USERS_REL;
	}

	/**
	 * Find rules by tiered_pricing_id
	 *
	 * @param int                      $tiered_pricing_id Tiered pricing id.
	 * @param array<int|string, mixed> $match_any Match any of the following tags, categories, products, users, roles. Must be an array of arrays, each array containing the following keys:
	 * - col_tag() => int[]
	 * - col_category() => int[]
	 * - col_product() => int[]
	 * - col_user() => int[]
	 * - col_role() => string[].
	 * @return Rule[]
	 */
	public function find_by_tiered_pricing( $tiered_pricing_id, $match_any = [] ) {
		$where = $this->get_where_for_serialized( $match_any );
		$wpdb  = $this->wpdb();
		return $this->map_all(
			$wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE %i = %d',
					$this->table(),
					$this->col_tiered_pricing_id(),
					(int) $tiered_pricing_id
				) . ( empty( $where ) ? '' : " $where" )
			) ?? []
		);
	}

	/**
	 * Get entity data for insert or update using wpdb format.
	 *
	 * @param Rule $entity Entity to get data for.
	 * @return array<string, mixed>
	 */
	private function get_data_for_save( $entity ) {
		// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$data = [
			$this->col_tiered_pricing_id()  => $entity->tiered_pricing_id,
			$this->col_roles_rel()          => $entity->roles_rel,
			$this->col_cats_rel()           => $entity->cats_rel,
			$this->col_tags_rel()           => $entity->tags_rel,
			$this->col_tags_with_cats_rel() => $entity->tags_with_cats_rel,
			$this->col_prods_cats_tags_with_roles_users_rel() => $entity->prods_cats_tags_with_roles_users_rel,
			$this->col_tag()                => serialize( $entity->tags ),
			$this->col_category()           => serialize( $entity->categories ),
			$this->col_product()            => serialize( $entity->products ),
			$this->col_user()               => serialize( $entity->users ),
			$this->col_role()               => serialize( $entity->roles ),
			$this->col_bundle_product()     => serialize( $entity->bundle_products ),
		];
		// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		return $data;
	}

	/**
	 * Save entity. If entity has ID, update, otherwise insert.
	 *
	 * @param Rule $entity Entity to save.
	 * @return Rule|null Saved entity or null if failed.
	 */
	public function save( $entity ) {
		$result = null;
		$data   = $this->get_data_for_save( $entity );

		// If entity has ID, update, otherwise insert.
		if ( empty( $entity->id ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- own table, no core API for it and nothing caches these rows.
			$result = $this->wpdb()->insert(
				$this->table(),
				$data,
				[ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
			);
			if ( ! empty( $result ) ) {
				$entity->id = (int) $this->wpdb()->insert_id;
			}
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, no core API for it and nothing caches these rows.
			$result = $this->wpdb()->update(
				$this->table(),
				$data,
				[ $this->col_id() => $entity->id ],
				[ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ],
				[ '%d' ]
			);
		}

		// update() returns affected rows: 0 means the row already held these values, not an error.
		return false === $result ? null : $entity;
	}

	/**
	 * Delete entity
	 *
	 * @param Rule|int $entity Entity ID or instance to delete.
	 * @return bool
	 */
	public function delete( $entity ) {
		$entity_id = $entity instanceof Rule ? $entity->id : $entity;
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
	 * @return Rule
	 */
	protected function map_array( $data, $with_relationships ) {
		// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
		/** @var array<int> $tags */
		$tags = empty( $data[ $this->col_tag() ] ) ? [] : unserialize( (string) $data[ $this->col_tag() ] );
		/** @var array<int> $categories */
		$categories = empty( $data[ $this->col_category() ] ) ? [] : unserialize( (string) $data[ $this->col_category() ] );
		/** @var array<int> $products */
		$products = empty( $data[ $this->col_product() ] ) ? [] : unserialize( (string) $data[ $this->col_product() ] );
		/** @var array<int> $users */
		$users = empty( $data[ $this->col_user() ] ) ? [] : unserialize( (string) $data[ $this->col_user() ] );
		/** @var array<string> $roles */
		$roles = empty( $data[ $this->col_role() ] ) ? [] : unserialize( (string) $data[ $this->col_role() ] );
		/** @var array<string> $bundle_products */
		$bundle_products = empty( $data[ $this->col_bundle_product() ] ) ? [] : unserialize( (string) $data[ $this->col_bundle_product() ] );
		return new Rule(
			(int) $data[ $this->col_id() ],
			(int) $data[ $this->col_tiered_pricing_id() ],
			(string) $data[ $this->col_roles_rel() ],
			(string) $data[ $this->col_cats_rel() ],
			(string) $data[ $this->col_tags_rel() ],
			(string) $data[ $this->col_tags_with_cats_rel() ],
			(string) $data[ $this->col_prods_cats_tags_with_roles_users_rel() ],
			$tags,
			$categories,
			$products,
			$users,
			$roles,
			$bundle_products
		);
		// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
	}

	/**
	 * Map object to entity
	 *
	 * @param object $data Data to map.
	 * @param bool   $with_relationships Whether to map relationships.
	 * @return Rule
	 */
	protected function map_object( $data, $with_relationships ) {
		// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
		/** @var array<int> $tags */
		$tags = empty( $data->{ $this->col_tag() } ) ? [] : unserialize( (string) $data->{ $this->col_tag() } );
		/** @var array<int> $categories */
		$categories = empty( $data->{ $this->col_category() } ) ? [] : unserialize( (string) $data->{ $this->col_category() } );
		/** @var array<int> $products */
		$products = empty( $data->{ $this->col_product() } ) ? [] : unserialize( (string) $data->{ $this->col_product() } );
		/** @var array<int> $users */
		$users = empty( $data->{ $this->col_user() } ) ? [] : unserialize( (string) $data->{ $this->col_user() } );
		/** @var array<string> $roles */
		$roles = empty( $data->{ $this->col_role() } ) ? [] : unserialize( (string) $data->{ $this->col_role() } );
		/** @var array<string> $bundle_products */
		$bundle_products = empty( $data->{ $this->col_bundle_product() } ) ? [] : unserialize( (string) $data->{ $this->col_bundle_product() } );
		return new Rule(
			(int) $data->{ $this->col_id() },
			(int) $data->{ $this->col_tiered_pricing_id() },
			(string) $data->{ $this->col_roles_rel() },
			(string) $data->{ $this->col_cats_rel() },
			(string) $data->{ $this->col_tags_rel() },
			(string) $data->{ $this->col_tags_with_cats_rel() },
			(string) $data->{ $this->col_prods_cats_tags_with_roles_users_rel() },
			$tags,
			$categories,
			$products,
			$users,
			$roles,
			$bundle_products
		);
		// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
	}

	/**
	 * Unimplemented. No relationships for this entity.
	 *
	 * @param Rule $entity Entity to set relationships.
	 * @return Rule
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
			. "%i text,\n"
			. "%i text,\n"
			. "%i text,\n"
			. "%i text,\n"
			. "%i text,\n"
			. "%i text,\n"
			. "%i varchar(3) NOT NULL,\n"
			. "%i varchar(3) NOT NULL,\n"
			. "%i varchar(3) NOT NULL,\n"
			. "%i varchar(3) NOT NULL,\n"
			. "%i varchar(3) NOT NULL,\n"
			. "PRIMARY KEY  (%i),\n"
			. "KEY %i (%i)\n)",
			$this->table(),
			$this->col_id(),
			$this->col_tiered_pricing_id(),
			$this->col_product(),
			$this->col_tag(),
			$this->col_category(),
			$this->col_user(),
			$this->col_role(),
			$this->col_bundle_product(),
			$this->col_roles_rel(),
			$this->col_tags_rel(),
			$this->col_cats_rel(),
			$this->col_tags_with_cats_rel(),
			$this->col_prods_cats_tags_with_roles_users_rel(),
			$this->col_id(),
			$this->col_tiered_pricing_id(),
			$this->col_tiered_pricing_id()
		) . " {$wpdb->get_charset_collate()}";
		$this->upgrade_wrapper()->db_delta( $sql );

		return $this->is_table_up_to_date( $sql );
	}
}
