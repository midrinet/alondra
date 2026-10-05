<?php
/**
 * Settings form
 *
 * @package Midrinet\Alondra\Infrastructure\View\Preferences
 */

namespace Midrinet\Alondra\Infrastructure\View\Preferences;

/**
 * The settings form, posted to options.php.
 */
class SettingsFormView {

	/**
	 * @param string $action   Form action URL.
	 * @param string $fields   Settings API fields (option page, action, nonce), already escaped.
	 * @param string $sections Sections HTML, already escaped.
	 * @return string
	 */
	public function render( string $action, string $fields, string $sections ): string {
		return '<form method="post" action="' . esc_url( $action ) . '" class="alondra-settings">'
			. $fields // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by its producer.
			. $sections // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by its producer.
			. '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save Changes', 'alondra' ) . '</button></p>'
			. '</form>';
	}
}
