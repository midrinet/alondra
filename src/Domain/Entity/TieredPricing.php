<?php
/**
 * Tiered Pricing Entity
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Domain/Entity
 */

namespace Midrinet\Alondra\Domain\Entity;

class TieredPricing {

	public const MIN_PRIORITY   = 1;
	public const STATUS_PUBLISH = 'publish';
	public const STATUS_DRAFT   = 'draft';
	public const STATUS_TRASH   = 'trash';

	public int $id;

	/**
	 * Title for administration purposes
	 */
	public string $title;

	/**
	 * Priority to choose which tiered pricing to use
	 */
	public int $priority;

	/**
	 * Date created or updated. Format: Y-m-d H:i:s (e.g. 2019-01-01 00:00:00) in UTC.
	 */
	public string $date_updated;

	/**
	 * Status
	 * Possible values: publish, draft, trash. Use class constants.
	 */
	public string $status;

	/**
	 * Tiers
	 *
	 * @var Tier[]
	 */
	public array $tiers;

	/**
	 * Any of this rules must be met to apply this tiered pricing
	 *
	 * @var Rule[]
	 */
	public array $rules;

	/**
	 * @param int    $id Unique ID.
	 * @param string $title Title for administration purposes.
	 * @param int    $priority Priority to choose which tiered pricing to use.
	 * @param string $status Status. Possible values: publish, draft, trash. Use class constants.
	 * @param string $date_updated Date created or updated. Format: Y-m-d H:i:s (e.g. 2019-01-01 00:00:00) in UTC.
	 * @param Tier[] $tiers Tiers.
	 * @param Rule[] $rules Any of this rules must be met to apply this tiered pricing.
	 */
	public function __construct(
		$id = 0,
		$title = '',
		$priority = self::MIN_PRIORITY,
		$status = self::STATUS_DRAFT,
		$date_updated = '0000-00-00 00:00:00',
		$tiers = [],
		$rules = []
	) {
		$this->id           = $id;
		$this->title        = $title;
		$this->priority     = $priority;
		$this->status       = $status;
		$this->date_updated = $date_updated;
		$this->tiers        = $tiers;
		$this->rules        = $rules;
	}

	/**
	 * Check if this tiered pricing is fulfilled for a product
	 *
	 * @param int       $product_id Product ID.
	 * @param int       $user_id User ID.
	 * @param string[]  $user_roles User roles.
	 * @param int[]     $tags_ids Tags IDs.
	 * @param int[]     $categories_ids Categories IDs.
	 * @return bool
	 */
	public function is_fulfilled( $product_id, $user_id, $user_roles, $tags_ids, $categories_ids ) {
		foreach ( $this->rules as $rule ) {
			if ( $rule->is_fulfilled( $user_id, $user_roles, $product_id, $tags_ids, $categories_ids ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get the tier for a quantity
	 *
	 * @param int $quantity Quantity.
	 * @return Tier|null
	 */
	public function get_tier_for_quantity( $quantity ) {
		$quantity = (int) $quantity;
		foreach ( $this->tiers as $tier ) {
			if ( $tier->min_units <= $quantity && $tier->max_units >= $quantity ) {
				return $tier;
			}
		}
		return null;
	}

	/**
	 * Get the date updated in the current timezone
	 *
	 * @return string
	 */
	public function get_date_in_current_tz() {
		$timezone = wp_timezone_string();
		$datetime = new \DateTime( $this->date_updated, new \DateTimeZone( 'UTC' ) );
		$datetime->setTimezone( new \DateTimeZone( $timezone ) );
		return $datetime->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Validate the entity and return WP_Error if there are errors
	 *
	 * @return null|\WP_Error
	 */
	public function validate() {
		if ( empty( $this->title ) ) {
			return new \WP_Error( 'title', __( 'Title is required', 'alondra' ) );
		}

		if ( empty( $this->priority ) ) {
			return new \WP_Error( 'priority', __( 'Priority is required', 'alondra' ) );
		}

		if ( $this->priority < self::MIN_PRIORITY ) {
			return new \WP_Error( 'priority', __( 'Priority must be greater than or equal to 1', 'alondra' ) );
		}

		if ( empty( $this->tiers ) ) {
			return new \WP_Error( 'tiers', __( 'At least one tier is required', 'alondra' ) );
		}

		// Filter for wrong tiers.
		foreach ( $this->tiers as $tier ) {
			$error = $tier->validate();
			if ( $error ) {
				return $error;
			}
		}

		// Filter for overlapping tiers.
		foreach ( $this->tiers as $tier ) {
			foreach ( $this->tiers as $tier2 ) {
				if ( $tier === $tier2 ) {
					continue;
				}

				if ( ( $tier->min_units >= $tier2->min_units && $tier->min_units <= $tier2->max_units )
				|| ( $tier->max_units >= $tier2->min_units && $tier->max_units <= $tier2->max_units )
				|| ( $tier->min_units <= $tier2->min_units && $tier->max_units >= $tier2->max_units ) ) {
					return new \WP_Error( 'tiers', __( 'There are overlapping tiers', 'alondra' ) );
				}
			}
		}

		if ( empty( $this->rules ) ) {
			return new \WP_Error( 'rules', __( 'At least one rule is required', 'alondra' ) );
		}

		foreach ( $this->rules as $rule ) {
			$error = $rule->validate();
			if ( $error ) {
				return $error;
			}
		}

		return null;
	}
}
