<?php
/**
 * Rule for tiered pricing
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Application/Dto
 */

namespace Midrinet\Alondra\Application\Dto;

class RuleDto {

	public int $id = 0;

	/**
	 * User roles relationship type. Must be one of the following:
	 * - 'AND' - All roles must be present
	 * - 'OR' - At least one role must be present
	 * Use the constants Rule::RELATIONSHIP_AND and Rule::RELATIONSHIP_OR
	 */
	public ?string $roles_rel = null;

	/**
	 * Categories relationship type. Must be one of the following:
	 * - 'ALL' - All categories must be present
	 * - 'ANY' - At least one category must be present
	 * Use the constants Rule::RELATIONSHIP_ALL and Rule::RELATIONSHIP_ANY
	 */
	public ?string $cats_rel = null;

	/**
	 * Tags relationship type. Must be one of the following:
	 * - 'ALL' - All tags must be present
	 * - 'ANY' - At least one tag must be present
	 * Use the constants Rule::RELATIONSHIP_ALL and Rule::RELATIONSHIP_ANY
	 */
	public ?string $tags_rel = null;

	/**
	 * Tags with categories relationship type. Must be one of the following:
	 * - 'AND' - All tags with categories must be present
	 * - 'OR' - At least one tag with category must be present
	 * Use the constants Rule::RELATIONSHIP_AND and Rule::RELATIONSHIP_OR
	 */
	public ?string $tags_with_cats_rel = null;

	/**
	 * Products, categories and tags group relationship with user and roles group. Must be one of the following:
	 * - 'AND' - All group conditions must be present
	 * - 'OR' - At least one group condition must be present
	 * Use the constants Rule::RELATIONSHIP_AND and Rule::RELATIONSHIP_OR
	 */
	public ?string $prods_cats_tags_with_roles_users_rel = null;

	/**
	 * Tags
	 *
	 * @var TagDto[]
	 */
	public array $tags = [];

	/**
	 * Categories
	 *
	 * @var CategoryDto[]
	 */
	public array $categories = [];

	/**
	 * Products
	 *
	 * @var ProductDto[]
	 */
	public array $products = [];

	/**
	 * Users
	 *
	 * @var UserDto[]
	 */
	public array $users = [];

	/**
	 * Roles
	 *
	 * @var string[]
	 */
	public array $roles = [];

	/**
	 * Private constructor. Use the static factory methods to create instances.
	 *
	 * @param int           $id Id of the rule. 0 if it's a new rule.
	 * @param string|null  $roles_rel Relationship type for user roles.
	 * @param string|null  $cats_rel Relationship type for categories.
	 * @param string|null  $tags_rel Relationship type for tags.
	 * @param string|null  $tags_with_cats_rel Relationship type for tags with categories.
	 * @param string|null  $prods_cats_tags_with_roles_users_rel Relationship type for products, categories, tags, users and roles.
	 * @param TagDto[]     $tags Tags.
	 * @param CategoryDto[] $categories Categories.
	 * @param ProductDto[] $products Products.
	 * @param UserDto[]    $users Users.
	 * @param string[]      $roles Roles.
	 */
	public function __construct( $id = 0, $roles_rel = null, $cats_rel = null, $tags_rel = null, $tags_with_cats_rel = null, $prods_cats_tags_with_roles_users_rel = null, $tags = [], $categories = [], $products = [], $users = [], $roles = [] ) {
		$this->id                                   = $id;
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
	}
}
