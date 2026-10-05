<?php
/**
 * Tier pricing entity
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Application/Dto
 */

namespace Midrinet\Alondra\Application\Dto;

class TierDto {

	public int $id;

	/**
	 * Min units for this tier to apply from. Value is inclusive.
	 */
	public int $min_units;

	/**
	 * Max units for this tier to apply to. Value is inclusive. Null means no maximum.
	 */
	public ?int $max_units;

	/**
	 * Fixed price per unit.
	 */
	public float $value;

	/**
	 * Private Constructor. Use static methods to create instances.
	 *
	 * @param int      $id Unique ID.
	 * @param int      $min_units Min units for this tier to apply from. Value is inclusive.
	 * @param int|null $max_units Max units for this tier to apply to. Value is inclusive. Null means no maximum.
	 * @param float    $value Fixed price per unit.
	 */
	public function __construct( $id = 0, $min_units = 0, $max_units = 0, $value = 0 ) {
		$this->id        = $id;
		$this->min_units = $min_units;
		$this->max_units = $max_units;
		$this->value     = $value;
	}
}
