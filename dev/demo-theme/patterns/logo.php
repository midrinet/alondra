<?php
/**
 * Title: Logo
 * Slug: demo-theme/logo
 * Inserter: no
 *
 * The wordmark is a theme asset rather than the site_logo option, so the header
 * stands on its own whether or not the demo store has been seeded. Like the hero,
 * that needs a runtime URL, which is what makes this a PHP pattern.
 *
 * @package demo-theme
 */

?>
<!-- wp:image {"className":"demo-logo","linkDestination":"custom"} -->
<figure class="wp-block-image demo-logo"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/logo.png' ) ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"/></a></figure>
<!-- /wp:image -->
