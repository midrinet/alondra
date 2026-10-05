<?php
/**
 * Preferences page opening markup
 *
 * @package Midrinet\Alondra\Infrastructure\View\Preferences
 */

namespace Midrinet\Alondra\Infrastructure\View\Preferences;

/**
 * Preferences page opening markup, up to and including the content area.
 */
class PreferencesPageOpenView {

	/**
	 * @param string $id      Page ID.
	 * @param string $header  Header area HTML, already escaped.
	 * @param string $content Content area HTML, already escaped.
	 * @return string
	 */
	public function render( string $id, string $header, string $content ): string {
		ob_start();
		?>

<div class="alondra-preferences" data-page="<?php echo esc_attr( $id ); ?>">
	<div class="alondra-preferences__header">
		<?php echo $header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by its producer. ?>
	</div>
	<div class="alondra-preferences__content">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by its producer. ?>
<?php // phpcs:ignore Generic.WhiteSpace.ScopeIndent.Incorrect -- the markup whitespace is output.
		return (string) ob_get_clean();
	}
}
