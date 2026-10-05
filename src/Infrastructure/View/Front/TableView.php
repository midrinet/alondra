<?php
/**
 * Tiers as a table
 *
 * @package Midrinet\Alondra\Infrastructure\View\Front
 */

namespace Midrinet\Alondra\Infrastructure\View\Front;

/**
 * Tiers as a table, the default storefront layout.
 */
class TableView extends PricingLayoutView {

	/**
	 * Render the tiers.
	 *
	 * @param \Midrinet\Alondra\Application\Dto\SimpleTierDto[] $tiers              Tiers to show; nothing renders when empty.
	 * @param string                                            $is_clickable_class CSS class of a clickable tier, or empty.
	 * @return string
	 */
	public function render( array $tiers, string $is_clickable_class ): string {
		if ( empty( $tiers ) ) {
			return '';
		}
		ob_start();
		?>

<table class="alondra-pricing__options alondra-pricing__table">
	<thead>
		<th><?php esc_html_e( 'Quantity', 'alondra' ); ?></th>
		<th><?php esc_html_e( 'Price per unit', 'alondra' ); ?></th>
	</thead>
	<tbody>
		<?php foreach ( $tiers as $tier ) : ?>
			<tr class="alondra-pricing__option <?php echo esc_attr( $is_clickable_class ); ?>" 
			data-price="<?php echo esc_attr( (string) $tier->price ); ?>"
			data-min-units="<?php echo esc_attr( (string) $tier->min_units ); ?>"
			data-max-units="<?php echo esc_attr( (string) $tier->max_units ); ?>">
				<td><?php echo wp_kses_post( $tier->get_quantity() ); ?></td>
				<td><?php echo wp_kses_post( $tier->get_formatted_price() ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
<?php echo $this->subtotal(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, Generic.WhiteSpace.ScopeIndent.Incorrect -- markup escaped by the view that builds it. ?></table>
<?php // phpcs:ignore Generic.WhiteSpace.ScopeIndent.Incorrect -- the markup whitespace is output.
		return (string) ob_get_clean();
	}

	/**
	 * Markup between the table body and its end. Free shows no subtotal.
	 *
	 * @return string
	 */
	protected function subtotal(): string {
		return '';
	}
}
