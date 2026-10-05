<?php
/**
 * Simple Tier for use in the frontend
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Application/Dto
 */

namespace Midrinet\Alondra\Application\Dto;

use Midrinet\Alondra\Domain\Entity\Tier;

/**
 * Simple Tier for use in the frontend
 */
class SimpleTierDto {

	/**
	 * Min units for this tier to apply from. Value is inclusive.
	 */
	public int $min_units;

	/**
	 * Max units for this tier to apply to. Value is inclusive.
	 */
	public int $max_units;

	public float $price;

	public float $regular_price;

	/**
	 * Discount percent. From 0 to 100.
	 */
	public float $percent;

	/**
	 * @param int   $min_units Min units for this tier to apply from. Value is inclusive.
	 * @param int   $max_units Max units for this tier to apply to. Value is inclusive.
	 * @param float $price Value of the price.
	 * @param float $regular_price Value of the regular price.
	 * @param float $percent Discount percent. From 0 to 100.
	 */
	public function __construct( $min_units = 0, $max_units = 0, $price = 0, $regular_price = 0, $percent = 0 ) {
		$this->min_units     = $min_units;
		$this->max_units     = $max_units;
		$this->price         = $price;
		$this->regular_price = $regular_price;
		$this->percent       = $percent;
	}

	/**
	 * Check if this tier has a discount
	 *
	 * @return bool
	 */
	public function is_discount() {
		return $this->regular_price > $this->price;
	}

	/**
	 * Get the discount percent as string
	 *
	 * @return string
	 */
	public function get_formatted_percent() {
		$decimal  = function_exists( 'wc_get_price_decimal_separator' ) ? wc_get_price_decimal_separator() : '.';
		$thousand = function_exists( 'wc_get_price_thousand_separator' ) ? wc_get_price_thousand_separator() : ',';
		// translators: %s is the percent.
		return \sprintf( esc_html__( '%s%%', 'alondra' ), number_format( 100 - $this->percent, 0, $decimal, $thousand ) );
	}

	/**
	 * Get the price as string
	 *
	 * @return string
	 */
	public function get_formatted_price() {
		$out     = '';
		$percent = '';

		if ( $this->is_discount() ) {
			$out .= \sprintf( '<del>%s</del> ', wc_price( $this->regular_price ) );
			// translators: %s is the percent.
			$percent = ' ' . \sprintf( '<span>' . esc_html__( '(%s off)', 'alondra' ) . '</span>', $this->get_formatted_percent() );
		}
		$out .= wc_price( $this->price ) . $percent;

		return $out;
	}

	/**
	 * Get the quantity for this tier as string
	 *
	 * @return string
	 */
	public function get_quantity() {
		$quantity = '';
		if ( $this->min_units === $this->max_units ) {
			$quantity = "{$this->min_units}";
		} elseif ( Tier::MAX_UNITS === $this->max_units ) {
			// translators: %d is the minimum units.
			$quantity = \sprintf( esc_html__( 'From %d', 'alondra' ), $this->min_units );
		} elseif ( 1 === $this->min_units ) {
			// translators: %d is the maximum units.
			$quantity = \sprintf( esc_html__( 'Up to %s', 'alondra' ), $this->max_units );
		} else {
			$quantity = \sprintf( '%d - %d', $this->min_units, $this->max_units );
		}
		return $quantity;
	}
}
