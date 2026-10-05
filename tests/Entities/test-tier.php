<?php
/**
 * Tier Entity Tests
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Entities;

use Midrinet\Alondra\Domain\Entity\Tier;
use WP_UnitTestCase;

/**
 * Unit tests for Midrinet\Alondra\Domain\Entity\Tier::validate().
 */
class TierTest extends WP_UnitTestCase {

	/**
	 * Test that a tier with equal min and max units is valid.
	 */
	public function test_validate_equal_min_max_units_is_valid() {
		$tier = new Tier( 0, 0, 2, 2, true, 10.0 );
		$this->assertNull( $tier->validate() );
	}

	/**
	 * Test that a tier with min=1 and max=1 (single unit) is valid.
	 */
	public function test_validate_single_unit_tier_is_valid() {
		$tier = new Tier( 0, 0, 1, 1, true, 5.0 );
		$this->assertNull( $tier->validate() );
	}

	/**
	 * Test that a tier with max less than min is invalid.
	 */
	public function test_validate_max_less_than_min_is_invalid() {
		$tier = new Tier( 0, 0, 5, 3, true, 10.0 );
		$this->assertWPError( $tier->validate() );
	}

	/**
	 * Test that a tier with zero min units is invalid.
	 */
	public function test_validate_zero_min_units_is_invalid() {
		$tier = new Tier( 0, 0, 0, 5, true, 10.0 );
		$this->assertWPError( $tier->validate() );
	}

	/**
	 * Test that a tier with zero max units is invalid.
	 */
	public function test_validate_zero_max_units_is_invalid() {
		$tier = new Tier( 0, 0, 1, 0, true, 10.0 );
		$this->assertWPError( $tier->validate() );
	}

	/**
	 * Test that a tier with negative value is invalid.
	 */
	public function test_validate_negative_value_is_invalid() {
		$tier = new Tier( 0, 0, 1, 10, true, -1.0 );
		$this->assertWPError( $tier->validate() );
	}

	/**
	 * Test that a normal tier with max greater than min is valid.
	 */
	public function test_validate_normal_tier_is_valid() {
		$tier = new Tier( 0, 0, 1, 10, true, 10.0 );
		$this->assertNull( $tier->validate() );
	}
}
