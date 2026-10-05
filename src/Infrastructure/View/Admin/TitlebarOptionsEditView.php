<?php
/**
 * Titlebar options of the edit screen
 *
 * @package Midrinet\Alondra\Infrastructure\View\Admin
 */

namespace Midrinet\Alondra\Infrastructure\View\Admin;

class TitlebarOptionsEditView {

	/**
	 * @param string $publish_button_title Publish button title; empty means 'Publish'.
	 * @return string
	 */
	public function render( string $publish_button_title ): string {
		$publish_title = empty( $publish_button_title ) ? __( 'Publish', 'alondra' ) : $publish_button_title;
		ob_start();
		?>
<button type="button" class="button alondra-draft-tiered-pricing"><?php echo esc_html__( 'Save as unpublished', 'alondra' ); ?></button>
<button type="button" class="button button-primary alondra-publish-tiered-pricing"><?php echo esc_html( $publish_title ); ?></button>
<button type="button" class="button alondra-options-menu__toggle">&#8942;</button>
<div class="alondra-options-menu">
	<button type="button" class="button alondra-options-menu__item alondra-draft-tiered-pricing"><?php echo esc_html__( 'Save as unpublished', 'alondra' ); ?></button>
	<button type="button" class="button alondra-options-menu__item alondra-options-menu__item--trash alondra-trash-tiered-pricing"><?php echo esc_html_x( 'Trash', 'verb', 'alondra' ); ?></button>
</div>
<?php // phpcs:ignore Generic.WhiteSpace.ScopeIndent.Incorrect -- the markup whitespace is output.
		return (string) ob_get_clean();
	}
}
