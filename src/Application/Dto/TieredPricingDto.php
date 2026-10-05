<?php
/**
 * Tiered Pricing DTO Class
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Application/Dto
 */

namespace Midrinet\Alondra\Application\Dto;

class TieredPricingDto {

	public int $id;

	/**
	 * Title for administration purposes
	 */
	public ?string $title;

	/**
	 * Date created or updated. Format: Y-m-d H:i:s (e.g. 2019-01-01 00:00:00) in UTC.
	 */
	public ?string $date_updated;

	/**
	 * Status
	 * Possible values: publish, draft, trash. Use class constants.
	 */
	public ?string $status;

	/**
	 * Tiers
	 *
	 * @var TierDto[]
	 */
	public array $tiers;

	/**
	 * Any of this rules must be met to apply this tiered pricing
	 *
	 * @var RuleDto[]
	 */
	public array $rules;

	/**
	 * Private constructor using attributes to prevent direct instantiation
	 * Use factory method instead.
	 *
	 * @param int        $id Unique ID.
	 * @param string     $title Title for administration purposes.
	 * @param string     $date_updated Date created or updated. Format: Y-m-d H:i:s (e.g. 2019-01-01 00:00:00) in UTC.
	 * @param string     $status Status. Possible values: publish, draft, trash. Use class constants.
	 * @param TierDto[] $tiers Tiers.
	 * @param RuleDto[] $rules Any of this rules must be met to apply this tiered pricing.
	 */
	public function __construct( $id = 0, $title = null, $date_updated = null, $status = null, $tiers = [], $rules = [] ) {
		$this->id           = $id;
		$this->title        = $title;
		$this->date_updated = $date_updated;
		$this->status       = $status;
		$this->tiers        = $tiers;
		$this->rules        = $rules;
	}
}
