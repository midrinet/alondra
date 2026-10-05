<?php
/**
 * Rule Entity Tests
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Entities;

use Midrinet\Alondra\Domain\Entity\Rule;
use WP_UnitTestCase;

/**
 * Unit tests for Midrinet\Alondra\Domain\Entity\Rule::is_fulfilled().
 */
class RuleTest extends WP_UnitTestCase {

	/**
	 * Build a rule, defaulting every relationship to the strictest value so the
	 * tests prove the relationships are not honoured.
	 *
	 * @param array<string, mixed> $criteria Any of tags, categories, products, users, roles.
	 * @return Rule
	 */
	private function make_rule( $criteria ) {
		return new Rule(
			0,
			0,
			Rule::RELATIONSHIP_ALL,
			Rule::RELATIONSHIP_ALL,
			Rule::RELATIONSHIP_ALL,
			Rule::RELATIONSHIP_AND,
			Rule::RELATIONSHIP_AND,
			$criteria['tags'] ?? [],
			$criteria['categories'] ?? [],
			$criteria['products'] ?? [],
			$criteria['users'] ?? [],
			$criteria['roles'] ?? [],
			$criteria['bundle_products'] ?? []
		);
	}

	/**
	 * A product listed in the rule fulfills it.
	 */
	public function test_product_match_fulfills_the_rule() {
		$rule = $this->make_rule( [ 'products' => [ 10, 11 ] ] );
		$this->assertTrue( $rule->is_fulfilled( 0, [], 11, [], [] ) );
	}

	/**
	 * A product absent from the rule does not fulfill it.
	 */
	public function test_product_mismatch_does_not_fulfill_the_rule() {
		$rule = $this->make_rule( [ 'products' => [ 10, 11 ] ] );
		$this->assertFalse( $rule->is_fulfilled( 0, [], 12, [], [] ) );
	}

	/**
	 * One matching category is enough even though cats_rel is ALL.
	 */
	public function test_single_category_match_fulfills_the_rule() {
		$rule = $this->make_rule( [ 'categories' => [ 5, 6 ] ] );
		$this->assertTrue( $rule->is_fulfilled( 0, [], 12, [], [ 6 ] ) );
	}

	/**
	 * One matching tag is enough even though tags_rel is ALL.
	 */
	public function test_single_tag_match_fulfills_the_rule() {
		$rule = $this->make_rule( [ 'tags' => [ 7, 8 ] ] );
		$this->assertTrue( $rule->is_fulfilled( 0, [], 12, [ 8 ], [] ) );
	}

	/**
	 * One matching role is enough even though roles_rel is ALL.
	 */
	public function test_single_role_match_fulfills_the_rule() {
		$rule = $this->make_rule( [ 'roles' => [ 'customer', 'subscriber' ] ] );
		$this->assertTrue( $rule->is_fulfilled( 0, [ 'subscriber' ], 12, [], [] ) );
	}

	/**
	 * A rule an add-on stored with only bundle pairs and a role is not targetless, so under OR the
	 * role alone must not reach a plain product.
	 */
	public function test_bundle_pairs_and_a_role_under_or_do_not_match_a_plain_product() {
		$rule                                       = $this->make_rule(
			[
				'bundle_products' => [ '61:15' ],
				'roles'           => [ 'subscriber' ],
			]
		);
		$rule->prods_cats_tags_with_roles_users_rel = Rule::RELATIONSHIP_OR;

		$this->assertFalse( $rule->is_fulfilled( 0, [ 'subscriber' ], 15, [], [] ) );
		$this->assertFalse( $rule->is_fulfilled( 0, [ 'subscriber' ], 12, [], [] ) );
	}

	/**
	 * The same rule stored with AND.
	 */
	public function test_bundle_pairs_and_a_role_under_and_do_not_match_a_plain_product() {
		$rule = $this->make_rule(
			[
				'bundle_products' => [ '61:15' ],
				'roles'           => [ 'subscriber' ],
			]
		);

		$this->assertFalse( $rule->is_fulfilled( 0, [ 'subscriber' ], 15, [], [] ) );
		$this->assertFalse( $rule->is_fulfilled( 0, [ 'subscriber' ], 12, [], [] ) );
	}

	/**
	 * With no product, category, tag or pair at all, the role alone still matches every product.
	 */
	public function test_a_role_with_no_targets_still_matches_every_product() {
		$rule = $this->make_rule( [ 'roles' => [ 'subscriber' ] ] );

		$this->assertTrue( $rule->is_fulfilled( 0, [ 'subscriber' ], 12, [], [] ) );
		$this->assertFalse( $rule->is_fulfilled( 0, [ 'customer' ], 12, [], [] ) );
	}

	/**
	 * A listed user fulfills the rule.
	 */
	public function test_user_match_fulfills_the_rule() {
		$rule = $this->make_rule( [ 'users' => [ 3 ] ] );
		$this->assertTrue( $rule->is_fulfilled( 3, [], 12, [], [] ) );
	}

	/**
	 * Categories and tags are combined with AND when both lists are populated,
	 * regardless of tags_with_cats_rel.
	 */
	public function test_categories_and_tags_both_populated_require_both_to_match() {
		$rule = $this->make_rule(
			[
				'categories' => [ 5 ],
				'tags'       => [ 7 ],
			]
		);
		$this->assertFalse( $rule->is_fulfilled( 0, [], 12, [], [ 5 ] ) );
		$this->assertTrue( $rule->is_fulfilled( 0, [], 12, [ 7 ], [ 5 ] ) );
	}

	/**
	 * Product/category/tag criteria are combined with AND against user/role
	 * criteria when both groups are populated.
	 */
	public function test_product_and_user_groups_both_populated_require_both_to_match() {
		$rule = $this->make_rule(
			[
				'products' => [ 11 ],
				'users'    => [ 3 ],
			]
		);
		$this->assertFalse( $rule->is_fulfilled( 4, [], 11, [], [] ) );
		$this->assertTrue( $rule->is_fulfilled( 3, [], 11, [], [] ) );
	}
}
