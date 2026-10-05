<?php
/**
 * Settings select row
 *
 * @package Midrinet\Alondra\Infrastructure\View\Preferences
 */

namespace Midrinet\Alondra\Infrastructure\View\Preferences;

/**
 * A settings row holding one select.
 */
class SelectFieldView {

	/**
	 * @param string                $name     Select name.
	 * @param string                $id       Select id.
	 * @param string                $label    Row label.
	 * @param array<string, string> $options  Labels keyed by value.
	 * @param string                $selected Selected value.
	 * @return string
	 */
	public function render( string $name, string $id, string $label, array $options, string $selected ): string {
		$html = '<tr class="form-field alondra-field__wrapper"><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>'
			. '<select class="alondra-form-field" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
		foreach ( $options as $value => $option_label ) {
			$html .= '<option value="' . esc_attr( (string) $value ) . '"' . ( (string) $value === $selected ? ' selected="selected"' : '' ) . '>' . esc_html( $option_label ) . '</option>';
		}
		return $html . '</select></td></tr>';
	}
}
