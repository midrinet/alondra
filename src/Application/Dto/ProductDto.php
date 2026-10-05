<?php
/**
 * Product DTO
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Application/Dto
 */

namespace Midrinet\Alondra\Application\Dto;

class ProductDto {

	public int $id = 0;

	public string $title;

	/**
	 * @param int    $id    Unique ID.
	 * @param string $title Product title.
	 */
	public function __construct( $id, $title ) {
		$this->id    = $id;
		$this->title = $title;
	}
}
