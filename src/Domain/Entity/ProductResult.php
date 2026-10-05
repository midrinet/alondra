<?php
/**
 * Product result entity for search operations
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Domain/Entity
 */

namespace Midrinet\Alondra\Domain\Entity;

/**
 * Simple representation of a product result with minimal data
 *
 * @since      1.0.0
 */
class ProductResult {

	public int $id;

	public string $title;

	public int $parent;

	public string $sku;

	public bool $is_variable;

	/**
	 * @param int    $id Product ID.
	 * @param string $title Product title.
	 * @param int    $parent_id Product parent ID.
	 * @param string $sku Product SKU.
	 * @param bool   $is_variable Product is variable.
	 */
	public function __construct( $id, $title, $parent_id = 0, $sku = '', $is_variable = false ) {
		$this->id          = $id;
		$this->title       = $title;
		$this->parent      = $parent_id;
		$this->sku         = $sku;
		$this->is_variable = $is_variable;
	}
}
