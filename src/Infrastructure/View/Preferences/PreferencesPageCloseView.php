<?php
/**
 * Preferences page closing markup
 *
 * @package Midrinet\Alondra\Infrastructure\View\Preferences
 */

namespace Midrinet\Alondra\Infrastructure\View\Preferences;

/**
 * Preferences page closing markup, footer included.
 */
class PreferencesPageCloseView {

	/**
	 * @param string $footer Footer area HTML, already escaped.
	 * @return string
	 */
	public function render( string $footer ): string {
		ob_start();
		?>

	</div>
	<div class="alondra-preferences__footer">
		<?php echo $footer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by its producer. ?>
	</div>
</div>
<?php // phpcs:ignore Generic.WhiteSpace.ScopeIndent.Incorrect -- the markup whitespace is output.
		return (string) ob_get_clean();
	}
}
