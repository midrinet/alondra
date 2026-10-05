<?php
/**
 * Product result datamapper
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Domain/Datamapper
 */

namespace Midrinet\Alondra\Domain\Datamapper;

use Midrinet\Alondra\Domain\Entity\ProductResult;

class ProductResultDatamapper {

	/**
	 * Column name for ID
	 *
	 * @return string
	 */
	public function col_id() {
		return 'product_id';
	}

	/**
	 * Column name for title
	 *
	 * @return string
	 */
	public function col_title() {
		return 'product_title';
	}

	/**
	 * Column name for parent_id
	 *
	 * @return string
	 */
	public function col_parent_id() {
		return 'product_parent';
	}

	/**
	 * Column name for sku
	 *
	 * @return string
	 */
	public function col_sku() {
		return 'product_sku';
	}

	/**
	 * Column name for is variable
	 *
	 * @return string
	 */
	public function col_is_variable() {
		return 'is_variable';
	}

	/**
	 * Map object to entity
	 *
	 * @param object $data Data to map.
	 * @return ProductResult
	 */
	public function map( $data ) {

		$is_variable = (bool) $data->{ $this->col_is_variable() };
		// translators: %s is product title.
		$title = ! $is_variable ? $data->{ $this->col_title() } : \sprintf( esc_html__( '%s (all variations)', 'alondra' ), $data->{ $this->col_title() } );

		return new ProductResult(
			(int) $data->{ $this->col_id() },
			$title,
			(int) $data->{ $this->col_parent_id() },
			(string) $data->{ $this->col_sku() },
			$is_variable
		);
	}

	/**
	 * Map array of objects to entities
	 *
	 * @param object[] $data Array of Data to map.
	 * @return ProductResult[]
	 */
	public function map_all( $data ) {
		$entities = [];
		if ( \is_array( $data ) && ! empty( $data ) ) {
			foreach ( $data as $item ) {
				$entities[] = $this->map( $item );
			}
		}
		return $entities;
	}
}
