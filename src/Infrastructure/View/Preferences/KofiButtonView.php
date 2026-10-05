<?php
/**
 * Ko-fi button
 *
 * @package Midrinet\Alondra\Infrastructure\View\Preferences
 */

namespace Midrinet\Alondra\Infrastructure\View\Preferences;

/**
 * A plain link to the Ko-fi page, styled as Ko-fi's "Support" pill. No Ko-fi script or iframe.
 */
class KofiButtonView {

	public const CUP = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="6 6 149 118" class="alondra-kofi__icon" aria-hidden="true" focusable="false">'
		. '<path d="M17 34a18 18 0 0 1 18-18h79a29 29 0 0 1 0 58h-4v8c0 18-18 29-46 29s-47-13-47-33z" fill="#fff" stroke="#202020" stroke-width="11" stroke-linejoin="round"/>'
		. '<path d="M112 40.5a6.5 6.5 0 0 1 13 0v11a6.5 6.5 0 0 1-13 0z" fill="#202020"/>'
		. '<path d="M69 90 39 60c-6-6-4-23 13-23 8 0 14 5 17 9 3-4 9-9 17-9 17 0 19 17 13 23z" fill="#ff5a16"/>'
		. '</svg>';

	public function render( string $url ): string {
		return '<a class="alondra-kofi" href="' . esc_url( $url ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr__( 'Support Us on Ko-fi', 'alondra' ) . '">'
			. self::CUP
			. '<span class="alondra-kofi__label">' . esc_html__( 'Support Us', 'alondra' ) . '</span>'
			. '</a>';
	}
}
