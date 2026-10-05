<?php
/**
 * Storefront pricing layout
 *
 * @package Midrinet\Alondra\Infrastructure\View\Front
 */

namespace Midrinet\Alondra\Infrastructure\View\Front;

use Midrinet\Alondra\Application\Dto\SimpleTierDto;

/**
 * Base of the interchangeable storefront layouts of the tier list.
 */
abstract class PricingLayoutView {

	/**
	 * Render the tiers.
	 *
	 * @param SimpleTierDto[] $tiers              Tiers to show; nothing renders when empty.
	 * @param string          $is_clickable_class CSS class of a clickable tier, or empty.
	 * @return string
	 */
	abstract public function render( array $tiers, string $is_clickable_class ): string;
}
