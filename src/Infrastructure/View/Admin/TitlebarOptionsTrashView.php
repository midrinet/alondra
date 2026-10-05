<?php
/**
 * Titlebar options of a trashed item
 *
 * @package Midrinet\Alondra\Infrastructure\View\Admin
 */

namespace Midrinet\Alondra\Infrastructure\View\Admin;

class TitlebarOptionsTrashView {

	public function render(): string {
		ob_start();
		?>
<button type="button" class="button alondra-restore-tiered-pricing"><?php esc_html_e( 'Restore', 'alondra' ); ?></button>
<button type="button" class="button button-link-delete button-link-delete--fill alondra-delete-tiered-pricing"><?php esc_html_e( 'Delete Permanently', 'alondra' ); ?></button>
<?php // phpcs:ignore Generic.WhiteSpace.ScopeIndent.Incorrect -- the markup whitespace is output.
		return (string) ob_get_clean();
	}
}
