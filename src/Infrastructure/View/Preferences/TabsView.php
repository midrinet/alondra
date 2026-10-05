<?php
/**
 * Settings tabs
 *
 * @package Midrinet\Alondra\Infrastructure\View\Preferences
 */

namespace Midrinet\Alondra\Infrastructure\View\Preferences;

/**
 * The tab bar and one pane per tab. The first tab is active.
 */
class TabsView {

	/**
	 * @param array<string, array{title: string, content: string}> $tabs Tabs keyed by id, each content already escaped.
	 * @return string
	 */
	public function render( array $tabs ): string {
		$bar    = '';
		$panes  = '';
		$active = ' alondra-active';
		foreach ( $tabs as $id => $tab ) {
			$target = 'alondra-tab-' . $id;
			$bar   .= '<div class="alondra-tab' . $active . '" data-target="#' . esc_attr( $target ) . '">' . esc_html( $tab['title'] ) . '</div>';
			$panes .= '<div id="' . esc_attr( $target ) . '" class="alondra-tab__content' . $active . '">'
				. $tab['content'] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by its producer.
				. '</div>';
			$active = '';
		}
		return '<div class="alondra-tab__container">' . $bar . '</div>' . $panes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	}
}
