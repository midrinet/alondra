<?php
/**
 * Settings footer
 *
 * @package Midrinet\Alondra\Infrastructure\View\Preferences
 */

namespace Midrinet\Alondra\Infrastructure\View\Preferences;

/**
 * The settings footer: edition and version, the changelog and rating links and the Ko-fi button.
 */
class SettingsFooterView {

	public const STARS = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 24" class="alondra-rate__icon" aria-hidden="true" focusable="false" fill="#f0b849">'
		. '<path d="M12 2l2.9 6.9 7.1.6-5.4 4.7 1.7 7.3-6.3-3.7-6.3 3.7 1.7-7.3-5.4-4.7 7.1-.6z"/>'
		. '<path d="M36 2l2.9 6.9 7.1.6-5.4 4.7 1.7 7.3-6.3-3.7-6.3 3.7 1.7-7.3-5.4-4.7 7.1-.6z"/>'
		. '<path d="M60 2l2.9 6.9 7.1.6-5.4 4.7 1.7 7.3-6.3-3.7-6.3 3.7 1.7-7.3-5.4-4.7 7.1-.6z"/>'
		. '<path d="M84 2l2.9 6.9 7.1.6-5.4 4.7 1.7 7.3-6.3-3.7-6.3 3.7 1.7-7.3-5.4-4.7 7.1-.6z"/>'
		. '<path d="M108 2l2.9 6.9 7.1.6-5.4 4.7 1.7 7.3-6.3-3.7-6.3 3.7 1.7-7.3-5.4-4.7 7.1-.6z"/>'
		. '</svg>';

	/**
	 * @param string $edition       Edition label, such as FREE.
	 * @param string $version       Plugin version.
	 * @param string $changelog_url Changelog page URL.
	 * @param string $support_url   Support page URL.
	 * @param string $reviews_url   Reviews page URL.
	 * @param string $donate_url    Donation page URL.
	 * @return string
	 */
	public function render( string $edition, string $version, string $changelog_url, string $support_url, string $reviews_url, string $donate_url ): string {
		return '<p class="alondra-preferences__footer-meta">'
			// translators: %1$s is the edition label, %2$s the plugin version.
			. '<span class="alondra-preferences__footer-edition">' . esc_html( \sprintf( __( '%1$s v%2$s', 'alondra' ), $edition, $version ) ) . '</span>'
			. ' <a class="alondra-preferences__footer-link" href="' . esc_url( $changelog_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Changelog', 'alondra' ) . '</a>'
			. ' <a class="alondra-preferences__footer-link" href="' . esc_url( $support_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Get help', 'alondra' ) . '</a>'
			. '</p>'
			. '<div class="alondra-preferences__footer-actions">'
			. ( new KofiButtonView() )->render( $donate_url )
			. '<a class="alondra-rate" href="' . esc_url( $reviews_url ) . '" target="_blank" rel="noopener">'
			. self::STARS
			. '<span class="alondra-rate__label">' . esc_html__( 'Rate us on WordPress.org', 'alondra' ) . '</span>'
			. '</a>'
			. '</div>';
	}
}
