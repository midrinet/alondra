<?php
/**
 * The Price Section Entity
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Domain/Entity
 */

namespace Midrinet\Alondra\Domain\Entity;

/**
 * The Price Section Entity Class
 *
 * @since      1.0.0
 */
class PriceSection {

	/**
	 * From
	 *
	 * @since    1.0.0
	 */
	private float $from = 0.0;

	/**
	 * To
	 *
	 * @since    1.0.0
	 */
	private float $to = 0.0;

	/**
	 * Price
	 *
	 * @since    1.0.0
	 */
	private float $price = 0.0;

	/**
	 * Priority
	 *
	 * @since    1.0.0
	 */
	private int $priority = 0;

	/**
	 * Get from
	 *
	 * @since   1.0.0
	 *
	 * @return float
	 */
	public function get_from() {
		return $this->from;
	}

	/**
	 * Set from
	 *
	 * @since   1.0.0
	 *
	 * @param float $from from.
	 *
	 * @return PriceSection
	 */
	public function set_from( $from ) {
		$this->from = $from;

		return $this;
	}

	/**
	 * Get to
	 *
	 * @since   1.0.0
	 *
	 * @return float
	 */
	public function get_to() {
		return $this->to;
	}

	/**
	 * Set to
	 *
	 * @since   1.0.0
	 *
	 * @param float $to to.
	 *
	 * @return PriceSection
	 */
	public function set_to( $to ) {
		$this->to = $to;

		return $this;
	}

	/**
	 * Get price
	 *
	 * @since   1.0.0
	 *
	 * @return float
	 */
	public function get_price() {
		return $this->price;
	}

	/**
	 * Set price
	 *
	 * @since   1.0.0
	 *
	 * @param float $price price.
	 *
	 * @return PriceSection
	 */
	public function set_price( $price ) {
		$this->price = $price;

		return $this;
	}

	/**
	 * Get priority
	 *
	 * @since   1.0.0
	 *
	 * @return int
	 */
	public function get_priority() {
		return $this->priority;
	}

	/**
	 * Set priority
	 *
	 * @since   1.0.0
	 *
	 * @param int $priority priority.
	 *
	 * @return PriceSection
	 */
	public function set_priority( $priority ) {
		$this->priority = $priority;

		return $this;
	}
}
