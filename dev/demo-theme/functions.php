<?php
/**
 * Coffee Roasters child theme.
 *
 * @package demo-theme
 */

/**
 * Enqueue the child stylesheet. Block themes never load style.css on their own;
 * Twenty Twenty-Five loads its own explicitly and a child gets nothing.
 */
function demo_theme_enqueue_styles() {
	wp_enqueue_style(
		'demo-theme-style',
		get_stylesheet_uri(),
		[ 'twentytwentyfive-style' ],
		wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'demo_theme_enqueue_styles' );
