<?php
/**
 * The Tiered Pricing Repository
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Domain/Repository
 */

namespace Midrinet\Alondra\Domain\Repository;

use Midrinet\Alondra\Domain\Datamapper\RuleDatamapper;
use Midrinet\Alondra\Domain\Datamapper\TierDatamapper;
use Midrinet\Alondra\Domain\Datamapper\TieredPricingDatamapper;
use Midrinet\Alondra\Domain\Entity\Rule;
use Midrinet\Alondra\Domain\Entity\Tier;
use Midrinet\Alondra\Domain\Entity\TieredPricing;
use Midrinet\Alondra\Infrastructure\Config\PluginInfo;
use Midrinet\Alondra\Infrastructure\DI\Container;

/**
 * The Tiered Pricing Repository
 *
 * @since      1.0.0
 */
class TieredPricingRepo {

	public const CACHE_GROUP      = 'alondra';
	private const CACHE_TTL       = DAY_IN_SECONDS;
	public const CACHE_GENERATION = 'alondra_cache_generation';

	/**
	 * Whether lookups read and write the object cache. The `enable_cache` preference sets it.
	 */
	private bool $use_cache = true;

	/**
	 * Cache generation tokens memoized for the current request, keyed by blog id.
	 *
	 * Keyed because the option is per-site and so is every key built from it: one token
	 * for the whole request would outlive a switch_to_blog() and address entries no site
	 * ever wrote.
	 *
	 * @var array<int, string>
	 */
	private array $cache_generation = [];

	/**
	 * Sanitized `alondra_cache_key_context` values memoized for the current request, keyed by blog id.
	 *
	 * @var array<int, string>
	 */
	private array $cache_key_context = [];

	private function get_tiered_pricing_datamapper(): TieredPricingDatamapper {
		return Container::instance()->get( TieredPricingDatamapper::class );
	}

	private function get_tier_datamapper(): TierDatamapper {
		return Container::instance()->get( TierDatamapper::class );
	}

	private function get_rule_datamapper(): RuleDatamapper {
		return Container::instance()->get( RuleDatamapper::class );
	}

	/**
	 * Get Tiered Pricing results matching parameters
	 *
	 * @param string $search Substring to search for.
	 * @param string $status Status to filter by.
	 * @param int    $offset Record offset.
	 * @param int    $limit Page size.
	 * @param string $sort Sort column of the group table; anything else sorts by id.
	 * @param string $sort_dir Sort direction. Either 'asc' or 'desc'.
	 *
	 * @return TieredPricing[]
	 */
	public function list_matching_substring( $search, $status, $offset, $limit, $sort, $sort_dir ) {
		$mapper  = $this->get_tiered_pricing_datamapper();
		$id      = $mapper->col_id();
		$columns = [ $mapper->col_title(), $mapper->col_priority(), $mapper->col_date_updated(), $mapper->col_status() ];
		$dir     = 'desc' === strtolower( $sort_dir ) ? 'DESC' : 'ASC';

		// Sorted values repeat, so paging over ties would drop and duplicate rows between pages.
		// `id` ascending as the last key gives the sort a total order.
		$order_by = \in_array( $sort, $columns, true ) ? [
			$sort => $dir,
			$id   => 'ASC',
		] : [ $id => $dir ];

		$matching = empty( $search ) ? [] : [
			$this->get_tiered_pricing_datamapper()->col_title() => $search,
		];

		$results = $this->get_tiered_pricing_datamapper()->all_matching(
			$matching,
			$status,
			$order_by,
			null,
			$limit,
			$offset,
			false
		);

		return $results;
	}

	/**
	 * Count Tiered Pricing results matching parameters
	 *
	 * @param string $search Substring to search for.
	 * @param string $status Status to filter by.
	 *
	 * @return int
	 */
	public function count_matching_substring( $search, $status = null ) {
		$matching = empty( $search ) ? [] : [
			$this->get_tiered_pricing_datamapper()->col_title() => $search,
		];
		return $this->get_tiered_pricing_datamapper()->count_all_matching( $matching, $status );
	}

	/**
	 * Setup Database tables required for plugin to function.
	 *
	 * @throws \Exception Error creating database tables.
	 * @return void
	 */
	public function setup_database() {
		if ( ! $this->get_tiered_pricing_datamapper()->set_up() ) {
			throw new \Exception( esc_html__( 'Error creating database table for Tiered Pricing', 'alondra' ) );
		}
		if ( ! $this->get_tier_datamapper()->set_up() ) {
			throw new \Exception( esc_html__( 'Error creating database table for Tiers', 'alondra' ) );
		}
		if ( ! $this->get_rule_datamapper()->set_up() ) {
			throw new \Exception( esc_html__( 'Error creating database table for Rules', 'alondra' ) );
		}
	}

	/**
	 * Delete one or more Tiered Pricing by ID.
	 *
	 * @param int|int[] $id Tiered Pricing IDs.
	 * @return int|false Number of deleted Tiered Pricing or false if failed.
	 */
	public function delete( $id ) {
		if ( empty( $id ) ) {
			return false;
		}
		$ids     = \is_array( $id ) ? $id : [ $id ];
		$deleted = 0;
		foreach ( $ids as $id ) {
			if ( $this->get_tiered_pricing_datamapper()->delete( (int) $id ) ) {
				// Only once the parent is gone: a parent that survived must keep its children.
				$this->get_tier_datamapper()->delete_by_tiered_pricing( (int) $id );
				$this->get_rule_datamapper()->delete_by_tiered_pricing( (int) $id );
				++$deleted;
			}
		}
		if ( ! $deleted ) {
			return false;
		}
		$this->flush_cache();
		return $deleted;
	}

	/**
	 * Find matching items by ID or IDs.
	 *
	 * @param int|int[] $id Tiered Pricing IDs.
	 * @param bool      $with_relationships Whether to include relationships.
	 * @return TieredPricing[] Array of TieredPricing objects.
	 */
	public function find( $id, $with_relationships = false ) {
		$items = [];
		if ( ! empty( $id ) ) {
			$ids = \is_array( $id ) ? $id : [ $id ];
			foreach ( $ids as $id ) {
				$cache_key = $this->cache_key_entity( (int) $id, $with_relationships );
				$found     = false;
				$item      = $this->cache_get_one( $cache_key, $found );
				if ( ! $found ) {
					$item = $this->get_tiered_pricing_datamapper()->find( (int) $id, $with_relationships );
					$this->cache_set( $cache_key, $item );
				}
				if ( $item ) {
					$items[] = $item;
				}
			}
		}
		return $items;
	}

	/**
	 * Save Tiered Pricing.
	 *
	 * @param TieredPricing $item Tiered Pricing object.
	 * @return TieredPricing|null
	 */
	public function save( $item ) {
		if ( $item->id ) {
			$found        = false;
			$current_item = $this->cache_get_one( $this->cache_key_entity( $item->id, true ), $found );
			if ( ! $found ) {
				// Read only, never populated: the bump at the end of this method would orphan the entry immediately.
				$current_item = $this->get_tiered_pricing_datamapper()->find( $item->id, true );
			}
			if ( ! $current_item ) {
				// An id no row carries: the parent update would match nothing and the children would be orphaned.
				return null;
			}
			// Delete non existing tiers and rules.
			foreach ( $current_item->tiers as $tier ) {
				$found = false;
				foreach ( $item->tiers as $t ) {
					if ( $t->id === $tier->id ) {
						$found = true;
						break;
					}
				}
				if ( ! $found ) {
					$this->get_tier_datamapper()->delete( $tier );
				}
			}
			foreach ( $current_item->rules as $rule ) {
				$found = false;
				foreach ( $item->rules as $r ) {
					if ( $r->id === $rule->id ) {
						$found = true;
						break;
					}
				}
				if ( ! $found ) {
					$this->get_rule_datamapper()->delete( $rule );
				}
			}
		}
		$saved = $this->get_tiered_pricing_datamapper()->save( $item );
		// Bumped even when the save reports failure: the orphaned tiers and rules above
		// are already gone, so the cached tiers would be wrong either way.
		$this->flush_cache();
		return $saved;
	}

	/**
	 * Get all WP_Term for a Tiered Pricing Rule.
	 *
	 * @param Rule $rule Tiered Pricing Rule.
	 * @return \WP_Term[]
	 */
	public function get_rule_tags( Rule $rule ) {
		$ids = $rule->tags;
		if ( empty( $ids ) ) {
			return [];
		}
		$terms = get_terms(
			[
				'taxonomy'   => 'product_tag',
				'hide_empty' => false,
				'include'    => $ids,
			]
		);
		if ( ! \is_array( $terms ) ) {
			return [];
		}
		return $terms;
	}

	/**
	 * Get all WP_Term for a Tiered Pricing Rule.
	 *
	 * @param Rule $rule Tiered Pricing Rule.
	 * @return \WP_Term[]
	 */
	public function get_rule_categories( Rule $rule ) {
		$ids = $rule->categories;
		if ( empty( $ids ) ) {
			return [];
		}
		$terms = get_terms(
			[
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'include'    => $ids,
			]
		);
		if ( ! \is_array( $terms ) ) {
			return [];
		}
		return $terms;
	}

	/**
	 * Get all WP_User for a Tiered Pricing Rule.
	 *
	 * @param Rule $rule Tiered Pricing Rule.
	 * @return \WP_User[]
	 */
	public function get_rule_users( Rule $rule ) {
		$ids = $rule->users;
		if ( empty( $ids ) ) {
			return [];
		}
		$users = get_users(
			[
				'include' => $ids,
			]
		);
		if ( ! \is_array( $users ) ) {
			return [];
		}
		/** @var \WP_User[] $users */
		return $users;
	}

	/**
	 * Get all WP_Posts for a Tiered Pricing Rule.
	 *
	 * @param Rule $rule Tiered Pricing Rule.
	 * @return \WP_Post[]
	 */
	public function get_rule_products( Rule $rule ) {
		$ids = $rule->products;
		if ( empty( $ids ) ) {
			return [];
		}
		// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts, WordPressVIPMinimum.Performance.NoPaging.nopaging_nopaging
		$products = get_posts(
			[
				'post_type' => get_post_types(),
				'post__in'  => $ids,
				'nopaging'  => true,
			]
		);
		if ( ! \is_array( $products ) ) {
			return [];
		}
		return $products;
	}

	/**
	 * Find matching Tiered Pricing for a product, terms, user, roles and quantity.
	 *
	 * @param int          $product_id The product ID.
	 * @param int          $user_id The user ID.
	 * @param int          $quantity The quantity.
	 * @param array<mixed> $line Cart line context, passed through to the matching filters and folded into the cache key.
	 * @return TieredPricing|null
	 */
	public function find_matching( $product_id, $user_id, $quantity, array $line = [] ) {
		$cache_key = $this->cache_key_one( $product_id, $user_id, $quantity, $line );
		$found     = false;
		$cached    = $this->cache_get_one( $cache_key, $found );
		if ( $found ) {
			return $cached;
		}

		$result         = null;
		$categories_ids = $this->get_product_categories_ids( $product_id );
		$tags_ids       = $this->get_product_tags_ids( $product_id );
		$roles          = $this->get_user_roles( $user_id );

		$args  = $this->make_args_for_matching( $product_id, $categories_ids, $tags_ids, $user_id, $roles, $line );
		$facts = compact( 'product_id', 'user_id', 'quantity', 'line', 'roles', 'categories_ids', 'tags_ids' );

		$results = $this->get_tiered_pricing_datamapper()->all_matching( $args, TieredPricing::STATUS_PUBLISH, $this->matching_order_columns(), 'ASC' );
		/** @var TieredPricing[] $results *///phpcs:ignore
		foreach ( $results as &$item ) {
			$this->get_tiered_pricing_datamapper()->set_rules( $item, $args );
			$matches = $item->is_fulfilled( $product_id, $user_id, $roles, $tags_ids, $categories_ids );
			if ( ! $this->filter_matches( $matches, $item, $facts ) ) {
				continue;
			}
			if ( empty( $item->tiers ) ) {
				$this->get_tiered_pricing_datamapper()->set_tiers( $item );
			}
			if ( empty( $item->get_tier_for_quantity( $quantity ) ) ) {
				continue;
			}
			$result = $item;
			break;
		}

		// A null result is cached too: most products match no group, and that is the
		// lookup worth not repeating.
		$this->cache_set( $cache_key, $result );
		return $result;
	}

	/**
	 * Make args for matching.
	 *
	 * @param int          $product_id Product ID.
	 * @param int[]        $categories_ids Categories IDs.
	 * @param int[]        $tags_ids Tags IDs.
	 * @param int          $user_id The user ID.
	 * @param string[]     $roles The user roles.
	 * @param array<mixed> $line Cart line context.
	 * @return array<string, mixed>
	 */
	protected function make_args_for_matching( $product_id, $categories_ids, $tags_ids, $user_id, $roles, array $line = [] ) {
		$args = [];
		if ( $product_id > 0 ) {
			$args['product_id'] = [ $product_id ];
			$post_parent        = wp_get_post_parent_id( $product_id );
			if ( ! empty( $post_parent ) ) {
				$args['product_id'][] = (int) $post_parent;
			}
		}

		if ( ! empty( $categories_ids ) ) {
			$args['cat_id'] = $categories_ids;
		}
		if ( ! empty( $tags_ids ) ) {
			$args['tag_id'] = $tags_ids;
		}
		if ( $user_id > 0 ) {
			$args['user_id'] = [ $user_id ];
		}
		if ( ! empty( $roles ) ) {
			$args['role'] = $roles;
		}
		return $this->filter_matching_args( $args, $product_id, $user_id, $line );
	}

	/**
	 * Let an extension reshape the candidate query args.
	 *
	 * Filter output is untrusted: only keys naming a rule column the query matches on survive,
	 * because they reach the SQL as identifiers, and a value that is not a list of ids or
	 * strings, or an empty list, falls back to free's own value for that key.
	 *
	 * @param array<string, mixed> $args Free's own args.
	 * @param int                  $product_id Product ID.
	 * @param int                  $user_id User ID.
	 * @param array<mixed>         $line Cart line context.
	 * @return array<string, mixed>
	 */
	private function filter_matching_args( array $args, $product_id, $user_id, array $line ) {
		/**
		 * Filters the args that select candidate groups, keyed by rule column.
		 *
		 * An empty array selects every published group, as free's own empty args do.
		 *
		 * @since 2.0.0
		 *
		 * @param array<string, mixed> $args       Rule column => list of values to match any of.
		 * @param int                  $product_id Product ID.
		 * @param int                  $user_id    User ID.
		 * @param array<mixed>         $line       Cart line context, empty outside a cart.
		 */
		$filtered = apply_filters( 'alondra_matching_args', $args, (int) $product_id, (int) $user_id, $line );
		if ( ! \is_array( $filtered ) ) {
			return $args;
		}

		$rules   = $this->get_rule_datamapper();
		$columns = [ $rules->col_product(), $rules->col_category(), $rules->col_tag(), $rules->col_user(), $rules->col_role(), $rules->col_bundle_product() ];
		$result  = [];
		foreach ( $filtered as $column => $values ) {
			if ( ! \in_array( $column, $columns, true ) ) {
				continue;
			}
			if ( \is_array( $values ) && [] !== $values && [] === array_filter( $values, fn( $value ) => ! \is_int( $value ) && ! \is_string( $value ) ) ) {
				$result[ $column ] = $values;
			} elseif ( isset( $args[ $column ] ) ) {
				$result[ $column ] = $args[ $column ];
			}
		}
		return $result;
	}

	/**
	 * Columns candidate groups are ordered by, `id` always last so the order is total.
	 *
	 * Filter output is untrusted: only columns of the group table survive.
	 *
	 * @return string[]
	 */
	private function matching_order_columns() {
		$mapper = $this->get_tiered_pricing_datamapper();
		$id     = $mapper->col_id();

		/**
		 * Filters the columns candidate groups are ordered by, ascending. `id` is always appended.
		 *
		 * @since 2.0.0
		 *
		 * @param string[] $columns Group table columns. Default `[ 'id' ]`.
		 */
		$columns = apply_filters( 'alondra_matching_order_columns', [ $id ] );
		if ( ! \is_array( $columns ) ) {
			return [ $id ];
		}

		$allowed = [ $mapper->col_title(), $mapper->col_priority(), $mapper->col_date_updated(), $mapper->col_status() ];
		$columns = array_filter( $columns, fn( $column ) => \in_array( $column, $allowed, true ) );
		return array_merge( array_values( array_unique( $columns ) ), [ $id ] );
	}

	/**
	 * Let an extension overrule free's rule check for one candidate group.
	 *
	 * Runs only on a cache miss, once per candidate.
	 *
	 * @param bool                 $matches Free's own result.
	 * @param TieredPricing        $item Candidate group, rules loaded.
	 * @param array<string, mixed> $facts Lookup facts.
	 * @return bool
	 */
	private function filter_matches( $matches, TieredPricing $item, array $facts ) {
		/**
		 * Filters whether a candidate group matches, after free's own rule check.
		 *
		 * A group that matches still needs a tier for the quantity in find_matching(). Its tiers
		 * are loaded after this filter, and only when still empty, so a listener may set them.
		 *
		 * @since 2.0.0
		 *
		 * @param bool                 $matches Whether the group's rules are fulfilled.
		 * @param TieredPricing        $item    Candidate group, rules loaded, tiers maybe not.
		 * @param array<string, mixed> $facts   product_id, user_id, quantity (null from find_all_matching()),
		 *                                      line, roles, categories_ids and tags_ids.
		 */
		$filtered = apply_filters( 'alondra_tiered_pricing_matches', $matches, $item, $facts );
		return \is_bool( $filtered ) ? $filtered : $matches;
	}

	/**
	 * Find matching Tiers for a product, terms, user and roles.
	 *
	 * @param int          $product_id The product ID.
	 * @param int          $user_id The user ID.
	 * @param array<mixed> $line Cart line context, passed through to the matching filters and folded into the cache key.
	 * @return TieredPricing[]
	 */
	public function find_all_matching( $product_id, $user_id, array $line = [] ) {
		$cache_key = $this->cache_key_all( $product_id, $user_id, $line );
		$found     = false;
		$cached    = $this->cache_get_all( $cache_key, $found );
		if ( $found ) {
			return $cached;
		}

		$categories_ids = $this->get_product_categories_ids( $product_id );
		$tags_ids       = $this->get_product_tags_ids( $product_id );
		$roles          = $this->get_user_roles( $user_id );

		$matching = [];

		$quantity = null;
		$args     = $this->make_args_for_matching( $product_id, $categories_ids, $tags_ids, $user_id, $roles, $line );
		$facts    = compact( 'product_id', 'user_id', 'quantity', 'line', 'roles', 'categories_ids', 'tags_ids' );
		$results  = $this->get_tiered_pricing_datamapper()->all_matching( $args, TieredPricing::STATUS_PUBLISH, $this->matching_order_columns(), 'ASC' );
		/** @var TieredPricing[] $results *///phpcs:ignore
		foreach ( $results as &$item ) {
			$this->get_tiered_pricing_datamapper()->set_rules( $item, $args );
			$matches = $item->is_fulfilled( $product_id, $user_id, $roles, $tags_ids, $categories_ids );
			if ( ! $this->filter_matches( $matches, $item, $facts ) ) {
				continue;
			}
			if ( empty( $item->tiers ) ) {
				$this->get_tiered_pricing_datamapper()->set_tiers( $item );
			}
			$matching[] = $item;
		}

		$this->cache_set( $cache_key, $matching );
		return $matching;
	}

	/**
	 * Get product terms ids from product id and parent of product id.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $taxonomy Taxonomy.
	 * @return int[]
	 */
	protected function get_product_terms_ids( $product_id, $taxonomy ) {
		$terms_ids = [];
		foreach ( [ $product_id, (int) wp_get_post_parent_id( $product_id ) ] as $pid ) {
			if ( ! $product_id ) {
				continue;
			}

			$ids = wp_get_post_terms( $pid, $taxonomy, [ 'fields' => 'ids' ] );
			if ( ! is_wp_error( $ids ) ) {
				foreach ( $ids as $term_id ) {
					$terms_ids[] = (int) $term_id;
				}
			}
		}

		$terms_ids = array_unique( $terms_ids, SORT_NUMERIC );
		return $terms_ids;
	}

	/**
	 * Get product categories ids.
	 *
	 * @param int $product_id Product ID.
	 * @return int[]
	 */
	protected function get_product_categories_ids( $product_id ) {
		return $this->get_product_terms_ids( $product_id, 'product_cat' );
	}

	/**
	 * Get product tags ids.
	 *
	 * @param int $product_id Product ID.
	 * @return int[]
	 */
	protected function get_product_tags_ids( $product_id ) {
		return $this->get_product_terms_ids( $product_id, 'product_tag' );
	}

	/**
	 * Get user roles.
	 *
	 * @param int $user_id User ID.
	 * @return string[]
	 */
	protected function get_user_roles( $user_id ) {
		$roles = [];
		$user  = get_user_by( 'id', $user_id );
		if ( $user instanceof \WP_User ) {
			$roles = $user->roles;
		}
		return $roles;
	}

	public function use_cache( bool $enabled ): void {
		$this->use_cache = $enabled;
	}

	/**
	 * Invalidate every cached lookup by rotating the generation token.
	 *
	 * @return void
	 */
	public function flush_cache() {
		$token = $this->new_cache_generation();
		// Explicit autoload keeps it in alloptions and skips update_option()'s re-evaluation SELECT.
		update_option( self::CACHE_GENERATION, $token, true );
		// Memo too, or keys built later this request would read the entries just orphaned.
		$this->cache_generation[ get_current_blog_id() ] = $token;
	}

	/**
	 * Current cache generation token.
	 *
	 * In an option, not the cache: an evicted token would be coined again onto keys that
	 * survived.
	 *
	 * @return string
	 */
	private function get_cache_generation() {
		$blog_id = get_current_blog_id();
		if ( ! isset( $this->cache_generation[ $blog_id ] ) ) {
			$stored = get_option( self::CACHE_GENERATION );
			if ( ! \is_string( $stored ) || '' === $stored ) {
				// Persisted, or every request would coin its own token and never hit an entry.
				$stored = $this->new_cache_generation();
				update_option( self::CACHE_GENERATION, $stored, true );
			}
			$this->cache_generation[ $blog_id ] = $stored;
		}
		return $this->cache_generation[ $blog_id ];
	}

	/**
	 * A generation token distinct from every earlier one.
	 *
	 * It only has to differ, never to be larger: update_option() skips the write when the
	 * value is unchanged, so a counter loses the invalidation to a request that raced it.
	 *
	 * @return string
	 */
	private function new_cache_generation() {
		return microtime( true ) . '-' . wp_rand();
	}

	// The keys below assume every price-affecting input is in them or derived from them: roles from
	// $user_id, terms and parent from $product_id. One that is not would serve another customer's price.
	// The plugin version scopes them too: nothing invalidates on an in-place update, and a graph
	// serialized by another version unserializes into these classes.

	/**
	 * Prefix every cache key shares: the plugin version, because the activation hook does not fire on an
	 * in-place update, and the generation token the invalidation rotates.
	 *
	 * @return string
	 */
	private function cache_key_prefix() {
		return Container::instance()->get( PluginInfo::class )->get_plugin_version() . '_g' . $this->get_cache_generation();
	}

	/**
	 * Cache key for the first Tiered Pricing matching a product, user and quantity.
	 *
	 * @param int          $product_id Product ID.
	 * @param int          $user_id User ID.
	 * @param int|float    $quantity Quantity.
	 * @param array<mixed> $line Cart line context.
	 * @return string
	 */
	private function cache_key_one( $product_id, $user_id, $quantity, array $line = [] ) {
		// Float cast so 1 and 1.0 share a key; the decimal point keeps 1.5 off 15.
		return $this->cache_key_prefix() . '_one_' . (int) $product_id . '_' . (int) $user_id . '_' . (float) $quantity . $this->cache_key_suffix( $line );
	}

	/**
	 * Cache key for all Tiered Pricing matching a product and user.
	 *
	 * @param int          $product_id Product ID.
	 * @param int          $user_id User ID.
	 * @param array<mixed> $line Cart line context.
	 * @return string
	 */
	private function cache_key_all( $product_id, $user_id, array $line = [] ) {
		return $this->cache_key_prefix() . '_all_' . (int) $product_id . '_' . (int) $user_id . $this->cache_key_suffix( $line );
	}

	/**
	 * Tail of a matching key: the context and the line, each only when non-empty, so without
	 * either the key is unchanged.
	 *
	 * @param array<mixed> $line Cart line context.
	 * @return string
	 */
	private function cache_key_suffix( array $line ) {
		$context = $this->get_cache_key_context();
		$suffix  = '' === $context ? '' : '_c' . $context;
		if ( ! empty( $line ) ) {
			ksort( $line );
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- only hashed, never unserialized; JSON fails on NAN, INF and deep nesting.
			$suffix .= '_l' . md5( serialize( $line ) );
		}
		return $suffix;
	}

	/**
	 * Context an extension folds into the matching keys, read once per blog per request.
	 *
	 * Filter output is untrusted: anything but a string is dropped, and so is every character
	 * outside [A-Za-z0-9.-]. The underscore goes too, since it separates the key's parts.
	 *
	 * @return string
	 */
	private function get_cache_key_context() {
		$blog_id = get_current_blog_id();
		if ( ! isset( $this->cache_key_context[ $blog_id ] ) ) {
			/**
			 * Filters the context folded into the matching cache keys.
			 *
			 * @since 2.0.0
			 *
			 * @param string $context Empty by default.
			 */
			$context = apply_filters( 'alondra_cache_key_context', '' );

			$this->cache_key_context[ $blog_id ] = \is_string( $context ) ? substr( (string) preg_replace( '/[^A-Za-z0-9.-]/', '', $context ), 0, 32 ) : '';
		}
		return $this->cache_key_context[ $blog_id ];
	}

	/**
	 * Cache key for a Tiered Pricing entity.
	 *
	 * @param int  $id Tiered Pricing ID.
	 * @param bool $with_relationships Whether the cached entity carries its relationships.
	 * @return string
	 */
	private function cache_key_entity( $id, $with_relationships = false ) {
		return $this->cache_key_prefix() . '_tp_' . (int) $id . ( $with_relationships ? '_full' : '_partial' );
	}

	/**
	 * Read a cached Tiered Pricing.
	 *
	 * @param string $key Cache key.
	 * @param bool   $found True only on a usable hit; a stored null is a hit, the cached "no match".
	 * @return TieredPricing|null
	 */
	private function cache_get_one( $key, &$found ) {
		$found = false;
		if ( ! $this->use_cache ) {
			return null;
		}
		$hit   = false;
		$value = wp_cache_get( $key, self::CACHE_GROUP, false, $hit );
		$found = true === $hit;
		if ( ! $found || null === $value ) {
			return null;
		}
		if ( $value instanceof TieredPricing && $this->is_well_formed( $value ) ) {
			return $this->isolate( $value );
		}
		// A payload an older version of the plugin wrote is a miss, not a price.
		$found = false;
		return null;
	}

	/**
	 * Read a cached list of Tiered Pricing.
	 *
	 * @param string $key Cache key.
	 * @param bool   $found Set to true only on a usable hit.
	 * @return TieredPricing[]
	 */
	private function cache_get_all( $key, &$found ) {
		$found = false;
		if ( ! $this->use_cache ) {
			return [];
		}
		$hit   = false;
		$value = wp_cache_get( $key, self::CACHE_GROUP, false, $hit );
		$found = true === $hit;
		$items = [];
		if ( ! $found || ! \is_array( $value ) ) {
			$found = false;
			return $items;
		}
		foreach ( $value as $item ) {
			if ( ! $item instanceof TieredPricing || ! $this->is_well_formed( $item ) ) {
				// A payload an older version of the plugin wrote is a miss, not a price.
				$found = false;
				return [];
			}
			$items[] = $this->isolate( $item );
		}
		return $items;
	}

	/**
	 * Whether an entity's relationship arrays hold what they claim to.
	 *
	 * Cloning in isolate() goes through type hints, so anything else would raise a TypeError.
	 * Checked on both ends, since a persistent cache can return a graph an older version wrote.
	 *
	 * @param TieredPricing $entity Entity to check.
	 * @return bool
	 */
	private function is_well_formed( TieredPricing $entity ) {
		foreach ( $entity->tiers as $tier ) {
			if ( ! $tier instanceof Tier ) {
				return false;
			}
		}
		foreach ( $entity->rules as $rule ) {
			if ( ! $rule instanceof Rule ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Copy a cached entity deeply enough that a caller cannot reach the cached graph.
	 *
	 * PHP's clone is shallow, so the children would stay shared with the cached graph.
	 * wp_cache_get() does not clone an array payload at all, hence per element on the list path.
	 *
	 * @param TieredPricing $entity Cached entity.
	 * @return TieredPricing
	 */
	private function isolate( TieredPricing $entity ) {
		$copy        = clone $entity;
		$copy->tiers = array_map( fn( Tier $tier ) => clone $tier, $entity->tiers );
		$copy->rules = array_map( fn( Rule $rule ) => clone $rule, $entity->rules );
		return $copy;
	}

	/**
	 * Write a value to the cache.
	 *
	 * Stores copies: the caller that populated the entry keeps its own reference to the payload.
	 *
	 * @param string $key Cache key.
	 * @param mixed  $value Value to cache.
	 * @return bool
	 */
	private function cache_set( $key, $value ) {
		if ( ! $this->use_cache ) {
			return false;
		}
		if ( $value instanceof TieredPricing ) {
			if ( ! $this->is_well_formed( $value ) ) {
				return false;
			}
			$value = $this->isolate( $value );
		} elseif ( \is_array( $value ) ) {
			foreach ( $value as $i => $item ) {
				if ( ! $item instanceof TieredPricing ) {
					continue;
				}
				if ( ! $this->is_well_formed( $item ) ) {
					return false;
				}
				$value[ $i ] = $this->isolate( $item );
			}
		}
		// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined -- self::CACHE_TTL is DAY_IN_SECONDS.
		return wp_cache_set( $key, $value, self::CACHE_GROUP, self::CACHE_TTL );
	}
}
