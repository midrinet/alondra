<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @package           Midrinet/Alondra
 *
 * @wordpress-plugin
 * Plugin Name:       Alondra
 * Plugin URI:        https://alondra.midri.net/
 * Description:       Advanced tiered pricing for WooCommerce.
 * Version:           2.0.0
 * Author:            Midrinet
 * Author URI:        https://midri.net/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       alondra
 * Requires PHP:      7.4
 * Requires at least: 6.8
 * Tested up to:      7.1
 * WC requires at least: 10.4.0
 * WC tested up to:   11.0.1
 * Requires Plugins:  woocommerce
 */

use Midrinet\Alondra\Infrastructure\Config\LegacyMonolith;
use Midrinet\Alondra\Infrastructure\Controller\ActivationController;
use Midrinet\Alondra\Infrastructure\Controller\AssetController;
use Midrinet\Alondra\Infrastructure\Controller\Controller;
use Midrinet\Alondra\Infrastructure\Controller\PreferencesController;
use Midrinet\Alondra\Infrastructure\Controller\TieredPricingController;
use Midrinet\Alondra\Infrastructure\DI\Container;

/* If this file is called directly, abort. */
if ( ! \defined( 'WPINC' ) ) {
	die;
}

require __DIR__ . '/vendor/autoload.php';

// Declared even while dormant next to Alondra 1.x, so WooCommerce does not flag this plugin as incompatible.
add_action(
	'before_woocommerce_init',
	static function () {
		// Declare compatibility with WooCommerce Custom Order Tables (HPOS).
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

// Next to Alondra 1.x nothing may run, not even the activation hook: 1.x listens on the same hooks and fatals.
if ( LegacyMonolith::is_active() ) {
	add_action( 'admin_notices', [ LegacyMonolith::class, 'print_notice' ] );
	return;
}

// Wrapped in a closure to keep its locals out of the global namespace.
call_user_func(
	static function () {
		// Freemius requires its SDK to be initialised while the plugin file is included, never later.
		$fs = fs_dynamic_init(
			[
				'id'                  => '10153',
				'slug'                => 'alondra',
				'type'                => 'plugin',
				'public_key'          => 'pk_4dfef2e068fd3716220a9543f1574',
				'is_premium'          => false,
				'has_premium_version' => false,
				'has_addons'          => true,
				'has_paid_plans'      => false,
				'is_org_compliant'    => true,
				'menu'                => [
					'first-path' => 'plugins.php',
					'contact'    => false,
					'support'    => false,
				],
			]
		);

		// This plugin's own pricing page would sell a plan that unlocks nothing; the hidden page still serves the add-on's checkout.
		$fs->add_filter( 'is_pricing_page_visible', '__return_false' );
		// Links to that page open the settings page; null must stay null, or the SDK stops registering the page.
		$fs->add_filter(
			'pricing_url',
			static function ( $url ) {
				return null === $url ? null : admin_url( 'options-general.php?page=' . PreferencesController::PAGE_SLUG );
			}
		);
		// Any trial on this product belongs to the 1.x paid plans, which unlock nothing here.
		$fs->add_filter( 'show_trial', '__return_false' );

		/**
		 * The SDK is initialised. Add-ons initialise their own Freemius instance here.
		 *
		 * @since 2.0.0
		 *
		 * @param Freemius $fs This plugin's Freemius instance.
		 */
		do_action( 'alondra_loaded', $fs );

		// A named key rather than Freemius::class: an add-on has its own instance of the same class.
		add_filter(
			'alondra_di_definitions',
			static function ( array $definitions ) use ( $fs ): array {
				$definitions['alondra/freemius'] = static fn() => $fs;
				return $definitions;
			},
			1
		);

		// The activation request includes this file after plugins_loaded has fired, so the container is
		// built on demand here.
		register_activation_hook(
			__FILE__,
			static function () {
				Container::build( __FILE__ )->get( ActivationController::class )->activate();
			}
		);

		// Read the extension filters once every plugin file has been included: an add-on cannot rely on
		// sorting before this one in active_plugins. WC_VERSION is only defined from here on too.
		add_action(
			'plugins_loaded',
			static function () {
				$container  = Container::build( __FILE__ );
				$activation = $container->get( ActivationController::class );

				if ( ! $activation->requirements_met() ) {
					add_action( 'admin_notices', [ $activation, 'print_requirements_notice' ] );
					return;
				}

				/**
				 * Controllers to register. Add-ons append their own.
				 *
				 * @since 2.0.0
				 *
				 * @param string[] $controllers Controller class names.
				 */
				$controllers = (array) apply_filters(
					'alondra_controllers',
					[
						ActivationController::class,
						AssetController::class,
						TieredPricingController::class,
						PreferencesController::class,
					]
				);

				foreach ( $controllers as $controller ) {
					if ( ! \is_string( $controller ) || ! is_subclass_of( $controller, Controller::class ) ) {
						_doing_it_wrong( 'alondra_controllers', esc_html__( 'Every entry must name a Controller subclass.', 'alondra' ), '2.0.0' );
						continue;
					}

					$container->get( $controller )->register();
				}
			},
			20
		);
	}
);
