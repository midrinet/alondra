<?php
/**
 * Settings checkbox row
 *
 * @package Midrinet\Alondra\Infrastructure\View\Preferences
 */

namespace Midrinet\Alondra\Infrastructure\View\Preferences;

/**
 * A settings row holding one checkbox. Unchecked, the browser submits nothing for it.
 */
class CheckboxFieldView {

	/**
	 * @param string $name        Input name.
	 * @param string $id          Input id.
	 * @param string $label       Row label.
	 * @param string $description Text next to the checkbox.
	 * @param bool   $checked     Whether the box is checked.
	 * @return string
	 */
	public function render( string $name, string $id, string $label, string $description, bool $checked ): string {
		return '<tr class="form-field alondra-field__wrapper"><th scope="row">' . esc_html( $label ) . '</th><td><label for="' . esc_attr( $id ) . '">'
			. '<input type="checkbox" class="alondra-form-field" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . ( $checked ? ' checked="checked"' : '' ) . ' /> '
			. esc_html( $description ) . '</label></td></tr>';
	}
}
