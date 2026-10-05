<?php
/**
 * Tag DTO
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Application/Dto
 */

namespace Midrinet\Alondra\Application\Dto;

class TagDto {

	public int $id = 0;

	public string $name;

	/**
	 * @param int    $id   Unique ID.
	 * @param string $name Name.
	 */
	public function __construct( $id, $name ) {
		$this->id   = $id;
		$this->name = $name;
	}
}
