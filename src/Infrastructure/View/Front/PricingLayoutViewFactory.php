<?php
/**
 * Storefront pricing layout factory
 *
 * @package Midrinet\Alondra\Infrastructure\View\Front
 */

namespace Midrinet\Alondra\Infrastructure\View\Front;

use Midrinet\Alondra\Application\Service\PreferencesService;

/**
 * Maps a stored pricing layout id to the view that renders it.
 */
class PricingLayoutViewFactory {

	/**
	 * A fresh view for the layout id, the table for any id nobody registered or whose view cannot be built
	 * without arguments.
	 *
	 * @param string $layout Stored pricing layout id.
	 * @return PricingLayoutView
	 */
	public function create( string $layout ): PricingLayoutView {
		/**
		 * Register storefront pricing layouts.
		 *
		 * @since 2.0.0
		 *
		 * @param array<string, class-string<PricingLayoutView>> $views View class by layout id.
		 */
		$views = apply_filters( 'alondra_pricing_layout_views', [ PreferencesService::PRICING_LAYOUT_TABLE => TableView::class ] );

		$class = \is_array( $views ) ? ( $views[ $layout ] ?? null ) : null;
		if ( ! \is_string( $class ) || ! is_subclass_of( $class, PricingLayoutView::class ) ) {
			return new TableView();
		}
		$reflection  = new \ReflectionClass( $class );
		$constructor = $reflection->getConstructor();
		if ( ! $reflection->isInstantiable() || ( null !== $constructor && $constructor->getNumberOfRequiredParameters() > 0 ) ) {
			return new TableView();
		}
		return new $class();
	}
}
