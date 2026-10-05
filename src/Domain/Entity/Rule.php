<?php
/**
 * Rule for tiered pricing
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Domain/Entity
 */

namespace Midrinet\Alondra\Domain\Entity;

class Rule {

	public const RELATIONSHIP_AND = 'AND';
	public const RELATIONSHIP_OR  = 'OR';
	public const RELATIONSHIP_ANY = 'ANY';
	public const RELATIONSHIP_ALL = 'ALL';

	/**
	 * Canonical form of a bundle-scoped product target: both ids positive, no leading zeros, no
	 * sign, no whitespace, exactly one colon. Matching compares the serialized string itself, so
	 * "01:15" and "1:15" would be two stored values naming one pair, only one of which ever matches.
	 *
	 * The pattern body, without delimiters, so a JS RegExp can take it verbatim; is_bundle_pair() is
	 * the one place PHP adds them.
	 */
	public const BUNDLE_PRODUCT_PATTERN = '^[1-9][0-9]*:[1-9][0-9]*$';

	/**
	 * The canonical pair naming one product inside one bundle.
	 *
	 * @param int $bundle_id Product ID of the bundle.
	 * @param int $product_id Product ID inside it.
	 * @return string
	 */
	public static function bundle_product( $bundle_id, $product_id ) {
		return (int) $bundle_id . ':' . (int) $product_id;
	}

	/**
	 * Whether a value is a canonical bundle-scoped pair.
	 *
	 * The `D` stops `$` matching before a trailing newline, as a JS RegExp's `$` would not, so a
	 * "15:63\n" no LIKE needle can match is refused rather than stored.
	 *
	 * @param mixed $value The value to test.
	 * @return bool
	 */
	public static function is_bundle_pair( $value ) {
		return \is_string( $value ) && 1 === preg_match( '/' . self::BUNDLE_PRODUCT_PATTERN . '/D', $value );
	}

	public int $id;

	public int $tiered_pricing_id;

	/**
	 * User roles relationship type. Must be one of the following:
	 * - 'ALL' - All categories must be present
	 * - 'ANY' - At least one category must be present
	 * Use the constants Rule::RELATIONSHIP_ALL and Rule::RELATIONSHIP_ANY
	 */
	public ?string $roles_rel;

	/**
	 * Categories relationship type. Must be one of the following:
	 * - 'ALL' - All categories must be present
	 * - 'ANY' - At least one category must be present
	 * Use the constants Rule::RELATIONSHIP_ALL and Rule::RELATIONSHIP_ANY
	 */
	public ?string $cats_rel;

	/**
	 * Tags relationship type. Must be one of the following:
	 * - 'ALL' - All tags must be present
	 * - 'ANY' - At least one tag must be present
	 * Use the constants Rule::RELATIONSHIP_ALL and Rule::RELATIONSHIP_ANY
	 */
	public ?string $tags_rel;

	/**
	 * Tags with categories relationship type. Must be one of the following:
	 * - 'AND' - All tags with categories must be present
	 * - 'OR' - At least one tag with category must be present
	 * Use the constants Rule::RELATIONSHIP_AND and Rule::RELATIONSHIP_OR
	 */
	public ?string $tags_with_cats_rel;

	/**
	 * Products, categories and tags group relationship with user and roles group. Must be one of the following:
	 * - 'AND' - All group conditions must be present
	 * - 'OR' - At least one group condition must be present
	 * Use the constants Rule::RELATIONSHIP_AND and Rule::RELATIONSHIP_OR
	 */
	public ?string $prods_cats_tags_with_roles_users_rel;

	/**
	 * Tags
	 *
	 * @var int[]
	 */
	public array $tags;

	/**
	 * Categories
	 *
	 * @var int[]
	 */
	public array $categories;

	/**
	 * Products
	 *
	 * @var int[]
	 */
	public array $products;

	/**
	 * Users
	 *
	 * @var int[]
	 */
	public array $users;

	/**
	 * Roles
	 *
	 * @var string[]
	 */
	public array $roles;

	/**
	 * Products scoped to one bundle, as "<bundle_id>:<product_id>" pairs.
	 *
	 * Kept as stored so an add-on's rules survive a save here; nothing here sets or matches on them.
	 *
	 * @var string[]
	 */
	public array $bundle_products;

	/**
	 * @param integer  $id Unique ID.
	 * @param integer  $tiered_pricing_id Tiered pricing ID this tier belongs to.
	 * @param string   $roles_rel User roles relationship type.
	 * @param string   $cats_rel Categories relationship type.
	 * @param string   $tags_rel Tags relationship type.
	 * @param string   $tags_with_cats_rel Tags with categories relationship type.
	 * @param string   $prods_cats_tags_with_roles_users_rel Products, categories and tags group relationship with user and roles group.
	 * @param int[]    $tags Tags.
	 * @param int[]    $categories Categories.
	 * @param int[]    $products Products.
	 * @param int[]    $users Users.
	 * @param string[] $roles Roles.
	 * @param string[] $bundle_products Products scoped to one bundle, as "<bundle_id>:<product_id>" pairs.
	 */
	public function __construct( $id = 0, $tiered_pricing_id = 0, $roles_rel = null, $cats_rel = null, $tags_rel = null, $tags_with_cats_rel = null, $prods_cats_tags_with_roles_users_rel = null, $tags = [], $categories = [], $products = [], $users = [], $roles = [], $bundle_products = [] ) {
		$this->id                                   = $id;
		$this->tiered_pricing_id                    = $tiered_pricing_id;
		$this->roles_rel                            = $roles_rel;
		$this->cats_rel                             = $cats_rel;
		$this->tags_rel                             = $tags_rel;
		$this->tags_with_cats_rel                   = $tags_with_cats_rel;
		$this->prods_cats_tags_with_roles_users_rel = $prods_cats_tags_with_roles_users_rel;
		$this->tags                                 = $tags;
		$this->categories                           = $categories;
		$this->products                             = $products;
		$this->users                                = $users;
		$this->roles                                = $roles;
		$this->bundle_products                      = $bundle_products;
	}

	/**
	 * Check whether the rule's category/tag criteria are fulfilled for a product.
	 * Isolated from is_fulfilled() so the ANY/ALL-within-a-list and OR/AND-between-
	 * categories-and-tags logic has its own boolean algebra, independently testable.
	 *
	 * @param bool      $is_product_fulfilled Whether the product itself already matched.
	 * @param int[]     $categories_ids Product categories IDs.
	 * @param int[]     $tags_ids Product tags IDs.
	 * @return boolean
	 */
	public function is_category_or_tag_fulfilled( $is_product_fulfilled, $categories_ids, $tags_ids ) {
		$is_category_fulfilled = false;
		if ( ! $is_product_fulfilled ) {
			foreach ( $this->categories as $rule_category ) {
				if ( \in_array( $rule_category, $categories_ids, true ) ) {
					$is_category_fulfilled = true;
					break;
				}
			}
		}

		$categories_or_tags = empty( $this->tags ) || empty( $this->categories );

		// Only check tags when the result can still flip: under OR, once the
		// category already matched the combined result is already true; under
		// AND, once the category already failed the combined result is already
		// false. Both are already decided without needing the tags loop.
		$is_tag_fulfilled = false;
		if ( ! $is_product_fulfilled && ! empty( $this->tags ) && $categories_or_tags !== $is_category_fulfilled ) {
			foreach ( $this->tags as $rule_tag ) {
				if ( \in_array( $rule_tag, $tags_ids, true ) ) {
					$is_tag_fulfilled = true;
					break;
				}
			}
		}

		return $categories_or_tags ? ( $is_category_fulfilled || $is_tag_fulfilled ) : ( $is_category_fulfilled && $is_tag_fulfilled );
	}

	/**
	 * Check if rule is fulfilled
	 *
	 * @param int       $user_id User ID.
	 * @param string[]  $user_roles User roles.
	 * @param int       $product_id Product or product variation ID.
	 * @param int[]     $tags_ids Optional. Product tags IDs.
	 * @param int[]     $categories_ids Optional. Product categories IDs.
	 * @return boolean
	 */
	public function is_fulfilled( $user_id, $user_roles, $product_id, $tags_ids, $categories_ids ) {
		$parent     = (int) wp_get_post_parent_id( $product_id );
		$product_id = $parent > 0 ? [ $product_id, $parent ] : [ $product_id ];

		// Pairs count as populating the product side even though this plugin never matches them: a rule
		// naming only pairs is not targetless, so it must not relax AND to OR and let the roles alone match.
		$is_product_fulfilled  = ! empty( array_intersect( $product_id, $this->products ) );
		$empty_user_and_roles  = empty( $this->users ) && empty( $this->roles );
		$empty_prods_cats_tags = empty( $this->products ) && empty( $this->categories ) && empty( $this->tags ) && empty( $this->bundle_products );

		$prod_cat_tag_or_user_role = $empty_prods_cats_tags || $empty_user_and_roles;

		if ( $prod_cat_tag_or_user_role && $is_product_fulfilled ) {
			return true;
		}

		$is_user_fulfilled = \in_array( $user_id, $this->users, true );

		if ( $prod_cat_tag_or_user_role && $is_user_fulfilled ) {
			return true;
		}

		$is_tag_with_category_fulfilled = $this->is_category_or_tag_fulfilled( $is_product_fulfilled, $categories_ids, $tags_ids );

		if ( $prod_cat_tag_or_user_role && $is_tag_with_category_fulfilled ) {
			return true;
		}

		$is_role_fulfilled = false;
		if ( ! $is_user_fulfilled ) {
			foreach ( $this->roles as $rule_role ) {
				if ( \in_array( $rule_role, $user_roles, true ) ) {
					$is_role_fulfilled = true;
					break;
				}
			}
		}

		$is_prods_cats_tags_fulfilled = $is_product_fulfilled || $is_tag_with_category_fulfilled;
		$is_roles_users_fulfilled     = $is_user_fulfilled || $is_role_fulfilled;

		return $prod_cat_tag_or_user_role ? ( $is_prods_cats_tags_fulfilled || $is_roles_users_fulfilled ) : ( $is_prods_cats_tags_fulfilled && $is_roles_users_fulfilled );
	}


	/**
	 * Validate the entity and return WP_Error if there are errors
	 *
	 * @return null|\WP_Error
	 */
	public function validate() {
		foreach ( $this->categories as $category ) {
			if ( $category <= 0 ) {
				return new \WP_Error( 'categories', __( 'Categories must be numeric and greater than 0', 'alondra' ) );
			}
		}

		foreach ( $this->products as $product ) {
			if ( $product <= 0 ) {
				return new \WP_Error( 'products', __( 'Products must be numeric and greater than 0', 'alondra' ) );
			}
		}

		foreach ( $this->tags as $tag ) {
			if ( $tag <= 0 ) {
				return new \WP_Error( 'tags', __( 'Tags must be numeric and greater than 0', 'alondra' ) );
			}
		}

		foreach ( $this->users as $user ) {
			if ( $user <= 0 ) {
				return new \WP_Error( 'users', __( 'Users must be numeric and greater than 0', 'alondra' ) );
			}
		}

		foreach ( $this->roles as $role ) {
			if ( empty( $role ) || trim( $role ) === '' ) {
				return new \WP_Error( 'roles', __( 'Roles must be not empty', 'alondra' ) );
			}
		}

		foreach ( $this->bundle_products as $bundle_product ) {
			if ( ! self::is_bundle_pair( $bundle_product ) ) {
				return new \WP_Error( 'bundle_products', __( 'Bundle products must be "<bundle_id>:<product_id>" pairs of numbers greater than 0', 'alondra' ) );
			}
		}

		if ( ! \in_array( $this->cats_rel, [ self::RELATIONSHIP_ALL, self::RELATIONSHIP_ANY ], true ) ) {
			return new \WP_Error( 'cats_rel', __( 'Categories relationship must be ANY or ALL', 'alondra' ) );
		}

		if ( ! \in_array( $this->roles_rel, [ self::RELATIONSHIP_ALL, self::RELATIONSHIP_ANY ], true ) ) {
			return new \WP_Error( 'roles_rel', __( 'Roles relationship must be ANY or ALL', 'alondra' ) );
		}

		if ( ! \in_array( $this->tags_rel, [ self::RELATIONSHIP_ALL, self::RELATIONSHIP_ANY ], true ) ) {
			return new \WP_Error( 'tags_rel', __( 'Tags relationship must be ANY or ALL', 'alondra' ) );
		}

		if ( ! \in_array( $this->prods_cats_tags_with_roles_users_rel, [ self::RELATIONSHIP_AND, self::RELATIONSHIP_OR ], true ) ) {
			return new \WP_Error( 'prods_cats_tags_with_roles_users_rel', __( 'Products, categories and tags group relationship with user and roles group must be AND or OR', 'alondra' ) );
		}

		if ( ! \in_array( $this->tags_with_cats_rel, [ self::RELATIONSHIP_AND, self::RELATIONSHIP_OR ], true ) ) {
			return new \WP_Error( 'tags_with_cats_rel', __( 'Tags with categories relationship must be AND or OR', 'alondra' ) );
		}

		if ( empty( $this->categories ) && empty( $this->products ) && empty( $this->tags ) && empty( $this->users ) && empty( $this->roles ) && empty( $this->bundle_products ) ) {
			return new \WP_Error( 'rule', __( 'At least one category, product, tag, user or role is required', 'alondra' ) );
		}

		return null;
	}
}
