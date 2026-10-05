<?php
/**
 * This file is part of the Alondra package.
 *
 * Call this script in your _manually_load_plugin function to load WooCommerce.
 *
 * @package Alondra
 */

defined( 'ABSPATH' ) || exit;

/**
 * Load WooCommerce and run necessary installation steps.
 */
require dirname( dirname( __DIR__ ) ) . '/woocommerce/woocommerce.php';
WC_Install::create_tables();

// Product Bundles installs its own tables from its 'init' hook, which fires while the test suite boots.
if ( getenv( 'ALONDRA_LOAD_PB' ) ) {
	require dirname( dirname( __DIR__ ) ) . '/woocommerce-product-bundles/woocommerce-product-bundles.php';
}
