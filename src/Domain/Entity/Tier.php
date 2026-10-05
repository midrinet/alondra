<?php
/**
 * Tier pricing entity
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Domain/Entity
 */

namespace Midrinet\Alondra\Domain\Entity;

class Tier {

	// use max unsigned int for max units.
	public const MAX_UNITS = 4294967295;

	public int $id;

	public int $tiered_pricing_id;

	/**
	 * Min units for this tier to apply from. Value is inclusive.
	 */
	public int $min_units;

	/**
	 * Max units for this tier to apply to. Value is inclusive.
	 */
	public int $max_units;

	/**
	 * Always true here: prices are fixed. The column is kept for an add-on that
	 * also prices by percentage. A row where it is false is read as a fixed price.
	 */
	public bool $is_fixed;

	/**
	 * Fixed price per unit.
	 */
	public float $value;

	/**
	 * @param int   $id Unique ID.
	 * @param int   $tiered_pricing_id Tiered pricing ID this tier belongs to.
	 * @param int   $min_units Min units for this tier to apply from. Value is inclusive.
	 * @param int   $max_units Max units for this tier to apply to. Value is inclusive.
	 * @param bool  $is_fixed Whether the price is fixed. Always true here.
	 * @param float $value Fixed price per unit.
	 */
	public function __construct( $id = 0, $tiered_pricing_id = 0, $min_units = 0, $max_units = 0, $is_fixed = false, $value = 0 ) {
		$this->id                = $id;
		$this->tiered_pricing_id = $tiered_pricing_id;
		$this->min_units         = $min_units;
		$this->max_units         = $max_units;
		$this->is_fixed          = $is_fixed;
		$this->value             = $value;
	}

	/**
	 * Check if the given units are in range for this tier
	 *
	 * @param int $units Units to check.
	 * @return bool
	 */
	public function is_in_range( $units ) {
		return $units >= $this->min_units && $units <= $this->max_units;
	}


	/**
	 * Validate the entity and return WP_Error if there are errors
	 *
	 * @return null|\WP_Error
	 */
	public function validate() {
		if ( $this->min_units <= 0
		|| $this->max_units <= 0
		|| $this->max_units < $this->min_units
		|| $this->value < 0 ) {
			return new \WP_Error( 'tiers', __( 'There are wrong tiers', 'alondra' ) );
		}
		return null;
	}
}
