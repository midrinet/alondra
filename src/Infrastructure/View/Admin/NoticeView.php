<?php
/**
 * Admin notice
 *
 * @package Midrinet\Alondra\Infrastructure\View\Admin
 */

namespace Midrinet\Alondra\Infrastructure\View\Admin;

class NoticeView {

	/**
	 * @param string $message     Message, may carry post HTML.
	 * @param string $type        Notice class, e.g. 'notice-error'.
	 * @param bool   $dismissible Whether the notice is dismissible.
	 * @return string
	 */
	public function render( string $message, string $type, bool $dismissible ): string {
		return '<div id="message" class="notice ' . esc_attr( $type ) . ' ' . esc_attr( $dismissible ? 'is-dismissible' : '' ) . '"><p>' . wp_kses_post( $message ) . '</p></div>';
	}
}
