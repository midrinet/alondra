<?php
/**
 * The Price Entity
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Domain/Entity
 */

namespace Midrinet\Alondra\Domain\Entity;

/**
 * The Price Entity Class
 *
 * @since      1.0.0
 */
class Price {

	/**
	 * Default Price
	 *
	 * @since    1.0.0
	 *
	 * @var      float|false
	 */
	private $default_price;

	/**
	 * Tiered Prices
	 *
	 * @since    1.0.0
	 *
	 * @var      PriceSection[]
	 */
	private array $tiered_prices;

	/**
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->default_price = false;
		$this->tiered_prices = [];
	}

	/**
	 * Get default_price
	 *
	 * @since   1.0.0
	 *
	 * @return false|float
	 */
	public function get_default_price() {
		return $this->default_price;
	}

	/**
	 * Set default_price
	 *
	 * @since   1.0.0
	 *
	 * @param false|float $default_price default_price.
	 *
	 * @return Price
	 */
	public function set_default_price( $default_price ) {
		$this->default_price = $default_price;

		return $this;
	}

	/**
	 * Get tiered_prices
	 *
	 * @since   1.0.0
	 *
	 * @return PriceSection[]
	 */
	public function get_tiered_prices() {
		return $this->tiered_prices;
	}

	/**
	 * Set tiered_prices
	 *
	 * @since   1.0.0
	 *
	 * @param PriceSection[] $tiered_prices tiered_prices.
	 *
	 * @return Price
	 */
	public function set_tiered_prices( $tiered_prices ) {
		$this->tiered_prices = $tiered_prices;

		return $this;
	}

	/**
	 * Add tiered_prices
	 *
	 * @since   1.0.0
	 *
	 * @param PriceSection|PriceSection[] $tiered_price_s Tiered Price (s).
	 *
	 * @return Price
	 */
	public function add_tiered_prices( $tiered_price_s ) {
		if ( $tiered_price_s instanceof PriceSection ) {
			$this->tiered_prices[] = $tiered_price_s;
		} elseif ( \is_array( $tiered_price_s ) ) {
			foreach ( $tiered_price_s as $sf ) {
				if ( $sf instanceof PriceSection ) {
					$this->tiered_prices[] = $sf;
				}
			}
		}

		return $this;
	}
}
