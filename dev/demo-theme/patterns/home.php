<?php
/**
 * Title: Home
 * Slug: demo-theme/home
 * Inserter: no
 *
 * The hero is a theme asset rather than a media-library attachment, so it needs a
 * runtime URL, which is what makes this a PHP pattern instead of inline template markup.
 *
 * @package demo-theme
 */

?>
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|60"},"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--60)"><!-- wp:paragraph {"align":"center","className":"demo-eyebrow","textColor":"accent-4","fontSize":"small"} -->
<p class="has-text-align-center demo-eyebrow has-accent-4-color has-text-color has-small-font-size">Madrid · Roasted to order</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textAlign":"center","level":1} -->
<h1 class="wp-block-heading has-text-align-center">Coffee roasted the week you drink it</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","textColor":"accent-4","fontSize":"large"} -->
<p class="has-text-align-center has-accent-4-color has-text-color has-large-font-size">Single origins, house blends and the kit to brew them. Buy more of one coffee and the price per bag drops on its own.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/shop/">Shop all coffee</a></div>
<!-- /wp:button -->

<!-- wp:button {"textColor":"contrast","className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-contrast-color has-text-color wp-element-button" href="/shop/">Brewing kit</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<!-- wp:image {"align":"wide","sizeSlug":"full","linkDestination":"none","style":{"border":{"radius":"6px"}}} -->
<figure class="wp-block-image alignwide size-full has-custom-border"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/home-hero.jpg' ) ); ?>" alt="Coffee pouches, a hand grinder and a glass pourover dripper on a warm paper sweep" style="border-radius:6px"/></figure>
<!-- /wp:image -->

<!-- wp:group {"align":"wide","backgroundColor":"accent-5","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}},"border":{"radius":"6px"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide has-accent-5-background-color has-background" style="border-radius:6px;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|50"}}}} -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size">Roasted in small batches</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"accent-4","fontSize":"small"} -->
<p class="has-accent-4-color has-text-color has-small-font-size">Twelve kilos at a time, three mornings a week, on a drum roaster we have kept running since 2014.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size">Better by the bag</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"accent-4","fontSize":"small"} -->
<p class="has-accent-4-color has-text-color has-small-font-size">Every coffee carries volume pricing. The table on the product page shows where the price drops and what each bag costs before you add it.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size">Kit and guides</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"accent-4","fontSize":"small"} -->
<p class="has-accent-4-color has-text-color has-small-font-size">A hand grinder and a glass dripper to brew with, and the roasting course and brew guide to read.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|40"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50)"><!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">From the roastery</h2>
<!-- /wp:heading -->

<!-- wp:woocommerce/product-collection {"queryId":1,"query":{"perPage":6,"pages":1,"offset":0,"postType":"product","order":"desc","orderBy":"price","search":"","exclude":[],"sticky":"","inherit":false,"taxQuery":{},"isProductCollectionBlock":true,"woocommerceOnSale":false,"woocommerceStockStatus":["instock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[]},"tagName":"div","dimensions":{"widthType":"fill","fixedWidth":""},"displayLayout":{"type":"flex","columns":3,"shrinkColumns":true},"convertedFromProducts":false,"queryContextIncludes":["collection"],"align":"wide"} -->
<div class="wp-block-woocommerce-product-collection alignwide"><!-- wp:woocommerce/product-template -->
<!-- wp:woocommerce/product-image {"showSaleBadge":true,"imageSizing":"thumbnail","isDescendentOfQueryLoop":true} -->
<!-- wp:woocommerce/product-sale-badge {"isDescendentOfQueryLoop":true,"align":"right"} /-->
<!-- /wp:woocommerce/product-image -->

<!-- wp:post-title {"textAlign":"center","level":3,"isLink":true,"fontSize":"medium","__woocommerceNamespace":"woocommerce/product-collection/product-title","style":{"typography":{"lineHeight":"1.4"}}} /-->

<!-- wp:woocommerce/product-price {"textAlign":"center","isDescendentOfQueryLoop":true,"fontSize":"small"} /-->
<!-- /wp:woocommerce/product-template --></div>
<!-- /wp:woocommerce/product-collection -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"textColor":"contrast","className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-contrast-color has-text-color wp-element-button" href="/shop/">See the whole shop</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
