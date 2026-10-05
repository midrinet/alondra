<?php
/**
 * Base controller
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Infrastructure/Controller
 */

namespace Midrinet\Alondra\Infrastructure\Controller;

use Midrinet\Alondra\Infrastructure\DI\Container;
use Midrinet\Alondra\Infrastructure\View\Admin\NoticeView;
use Midrinet\Alondra\Infrastructure\View\Admin\UpsellBannerView;

/**
 * A controller owns the hooks it registers.
 */
abstract class Controller {

	// Alondra Plus on Freemius: the add-on product and the plan the upgrade link sells.
	private const PLUS_PRODUCT_ID = 38115;
	private const PLUS_PLAN_ID    = 63451;

	/**
	 * Register the controller's hooks. Called once, while the plugin boots.
	 *
	 * @return void
	 */
	abstract public function register(): void;

	/**
	 * Display an admin notice.
	 *
	 * @param string $message Message to display.
	 * @param bool   $do_echo Whether to echo the notice.
	 * @param string $type Type of notice. Either 'error', 'warning', 'success', or 'info'. Default 'info'.
	 * @param bool   $dismissible Whether the notice is dismissible.
	 * @return string|void Notice HTML if $echo is false.
	 */
	public function show_notice( $message, $do_echo = true, $type = 'info', $dismissible = true ) {
		if ( empty( $message ) ) {
			return;
		}
		if ( ! \in_array( (string) $type, [ 'error', 'warning', 'success', 'info' ], true ) ) {
			$type = 'info';
		}
		$notice = ( new NoticeView() )->render( (string) $message, 'notice-' . $type, (bool) $dismissible );
		if ( $do_echo ) {
			echo wp_kses_post( $notice );
		} else {
			return $notice;
		}
	}

	/**
	 * The banner on the plugin's own screens. A licensed add-on's subclass returns ''.
	 *
	 * @return string
	 */
	protected function upsell_banner(): string {
		return ( new UpsellBannerView() )->render( $this->upgrade_url() );
	}

	/**
	 * The Freemius checkout of Alondra Plus.
	 *
	 * This plugin's own pricing page would sell its own plan, which never unlocks the add-on, so the
	 * checkout names the add-on's product and plan. Freemius registers that hidden page, which redirects
	 * to its hosted checkout with the site's context, only once the site has opted in or skipped; before
	 * that, the link goes to the hosted checkout directly.
	 *
	 * @return string
	 */
	protected function upgrade_url(): string {
		// The checkout would otherwise read "Alondra Plus Premium", product and plan title together.
		$params = [
			'plugin_id' => self::PLUS_PRODUCT_ID,
			'plan_id'   => self::PLUS_PLAN_ID,
			'title'     => 'Alondra Plus',
		];
		if ( '' !== menu_page_url( 'alondra-pricing', false ) ) {
			return Container::instance()->get_named( 'alondra/freemius', \Freemius::class )->checkout_url( 'annual', false, $params );
		}

		return add_query_arg(
			[
				'billing_cycle' => 'annual',
				'title'         => rawurlencode( $params['title'] ),
				'cancel_url'    => rawurlencode( admin_url( 'options-general.php?page=' . PreferencesController::PAGE_SLUG ) ),
			],
			sprintf( 'https://checkout.freemius.com/plugin/%d/plan/%d/', self::PLUS_PRODUCT_ID, self::PLUS_PLAN_ID )
		);
	}
}
