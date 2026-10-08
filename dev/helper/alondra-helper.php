<?php
/**
 * Alondra Helper plugin
 *
 * @package Alondra
 *
 * @wordpress-plugin
 * Plugin Name:       Alondra Helper
 * Description:       Provides helper functions for Alondra plugin development and testing
 * Version:           1.0.0
 * Author:            Midrinet
 * Author URI:        https://midri.net/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       alondra
 * Domain Path:       /languages
 * Requires PHP:      7.4
 * Requires at least: 6.8
 * Tested up to:      7.1
 * WC requires at least: 10.4.0
 * WC tested up to:   11.2.0
 * Requires Plugins:  woocommerce
 */

defined( 'WPINC' ) || die;

require_once __DIR__ . '/vendor/autoload.php';
add_action(
	'before_woocommerce_init',
	function () {
		// Declare compatibility with WooCommerce Custom Order Tables (HPOS).
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);
new \Midrinet\Alondra\Helper\Plugin();
