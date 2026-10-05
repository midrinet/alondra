<?php
/**
 * Settings section
 *
 * @package Midrinet\Alondra\Infrastructure\View\Preferences
 */

namespace Midrinet\Alondra\Infrastructure\View\Preferences;

/**
 * A titled group of settings rows.
 */
class SettingsSectionView {

	/**
	 * @param string $title Section title.
	 * @param string $rows  Rows HTML, already escaped.
	 * @return string
	 */
	public function render( string $title, string $rows ): string {
		return '<h2>' . esc_html( $title ) . '</h2><table class="form-table" role="presentation"><tbody>'
			. $rows // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by its producer.
			. '</tbody></table>';
	}
}
