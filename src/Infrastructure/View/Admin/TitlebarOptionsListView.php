<?php
/**
 * Titlebar options of the listing
 *
 * @package Midrinet\Alondra\Infrastructure\View\Admin
 */

namespace Midrinet\Alondra\Infrastructure\View\Admin;

class TitlebarOptionsListView {

	public function render( string $add_new_url ): string {
		ob_start();
		?>
<a href="<?php echo esc_url( $add_new_url ); ?>" id="alondra-add-tiered-pricing" class="button button-primary">＋ <?php esc_html_e( 'Add New', 'alondra' ); ?></a>
<?php // phpcs:ignore Generic.WhiteSpace.ScopeIndent.Incorrect -- the markup whitespace is output.
		return (string) ob_get_clean();
	}
}
