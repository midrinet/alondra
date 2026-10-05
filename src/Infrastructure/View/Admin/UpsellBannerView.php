<?php
/**
 * Upsell banner
 *
 * @package Midrinet\Alondra\Infrastructure\View\Admin
 */

namespace Midrinet\Alondra\Infrastructure\View\Admin;

/**
 * The one notice on the plugin's own screens: the Alondra Plus upgrade.
 */
class UpsellBannerView {

	public function render( string $upgrade_url ): string {
		return '<div class="notice notice-info alondra-upsell"><p>'
			. esc_html__( 'Alondra Plus adds percentage prices, group priorities, AND/OR operators inside and between rule groups, pills and list layouts, live price and total updates, and Product Bundles pricing.', 'alondra' )
			. ' <a href="' . esc_url( $upgrade_url ) . '" target="_self">' . esc_html__( 'Get Alondra Plus', 'alondra' ) . '</a></p></div>';
	}
}
