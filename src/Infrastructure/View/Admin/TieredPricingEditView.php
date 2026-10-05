<?php
/**
 * Tiered pricing edit form
 *
 * @package Midrinet\Alondra\Infrastructure\View\Admin
 */

namespace Midrinet\Alondra\Infrastructure\View\Admin;

class TieredPricingEditView {

	/**
	 * @param string $currency_symbol Store currency symbol, may be an HTML entity.
	 * @return string
	 */
	public function render( string $currency_symbol ): string {
		ob_start();
		?>
<div class="alondra-edit-section">
	<header class="alondra-edit-section__header">
		<h2 class="alondra-edit-section__title"><?php esc_html_e( 'Details', 'alondra' ); ?></h2>
	</header>
	<div class="alondra-edit-section__content alondra-edit-section__content--flex">
		<input type="hidden" class="alondra-input" name="id">
		<div class="alondra-input__container alondra-grow">
			<span class="alondra-input__label"><?php esc_html_e( 'Title', 'alondra' ); ?> <span class="alondra-input__required">*</span></span>	
			<input class="alondra-input" autofocus required type="text" name="title" placeholder="<?php esc_attr_e( 'Identifies the prices group in the listing', 'alondra' ); ?>">
		</div>
	</div>
</div>

<div class="alondra-edit-section alondra-edit-section--open">
	<header class="alondra-edit-section__header">
		<h2 class="alondra-edit-section__title"><?php esc_html_e( 'Pricing', 'alondra' ); ?> <span class="alondra-input__required">*</span></h2>
		<button type="button" class="alondra-edit-section__toggle"><span class="dashicons"></span></button>
	</header>
	<div class="alondra-edit-section__content">
		<div class="alondra-table" id="alondra-table-tiers">
			<header class="alondra-table__header">
				<input type="checkbox" class="alondra-check-all">
				<span><?php esc_html_e( 'Minimum units', 'alondra' ); ?>  <span class="alondra-input__required">*</span></span>
				<span><?php esc_html_e( 'Maximum units', 'alondra' ); ?></span>
				<span>
				<?php
				// translators: %s: currency symbol.
				echo esc_html( \sprintf( __( 'Value per unit (%s)', 'alondra' ), html_entity_decode( $currency_symbol ) ) );
				?>
				<span class="alondra-input__required">*</span></span>
			</header>
			<div class="alondra-table__body">
				<template>
					<div class="alondra-table__row">
						<input type="checkbox">
						<input type="number" value="1" min="1" step="1" name="min_quantity" autocomplete="off" required>
						<input type="number" value="" placeholder="∞" min="1" step="1" name="max_quantity" autocomplete="off">
						<input type="number" value="" placeholder="<?php esc_attr_e( 'Equal to or greater than 0', 'alondra' ); ?>" min="0" step="0.001" name="value" autocomplete="off" required>
					</div>
				</template>
			</div>
			<footer class="alondra-table__footer">
				<button class="button alondra-add" type="button"><?php esc_html_e( 'Add row', 'alondra' ); ?></button>
				<button class="button alondra-remove" type="button"><?php esc_html_e( 'Delete selected row(s)', 'alondra' ); ?></button>
			</footer>
		</div>
	</div>
</div>

<div class="alondra-edit-section alondra-edit-section--open">
	<header class="alondra-edit-section__header">
		<h2 class="alondra-edit-section__title">
		<?php
		// translators: %1$1s: opening strong tag, %2$2s: closing strong tag.
		\printf( esc_html__( 'Apply the above prices when %1$1s at least one of the following %2$2s rules is met.', 'alondra' ), '', '' );
		?>
		<span class="alondra-input__required">*</span>
		</h2>
		<button type="button" class="alondra-edit-section__toggle"><span class="dashicons"></span></button>
	</header>
	<div class="alondra-edit-section__content">
		<div class="alondra-table alondra-table--rules"  id="alondra-table-rules">
			<header class="alondra-table__header">
				<input type="checkbox" class="alondra-check-all">
				<span>
				<?php
				esc_html_e( 'Rules', 'alondra' );
				?>
				<span class="alondra-input__required">*</span>
				</span>
			</header>
			<div class="alondra-table__body">
				<template>
					<div class="alondra-table__row">
						<input type="checkbox">
						<!-- content -->
						<div class="alondra-table__rule">
							<div class="alondra-table__rule-inner">							
								<div class="alondra-input__container alondra-grow alondra-align-center">
									<span class="alondra-input__label"><?php esc_html_e( 'Product is:', 'alondra' ); ?></span>	
									<input class="alondra-input" type="text" name="products" placeholder="<?php esc_attr_e( 'Type name, SKU or ID...', 'alondra' ); ?>">
								</div>
								<span class="alondra-align-bottom">
									<strong><?php esc_html_e( 'or', 'alondra' ); ?></strong>
								</span>

								<div class="alondra-input__container alondra-grow alondra-align-center">
									<span class="alondra-input__label"><?php esc_html_e( 'Is in any Category:', 'alondra' ); ?></span>
									<input class="alondra-input" type="text" name="categories" placeholder="<?php esc_attr_e( 'Type name...', 'alondra' ); ?>">
								</div>

								<span class="alondra-align-bottom"><strong><?php esc_html_e( 'or', 'alondra' ); ?></strong></span>

								<div class="alondra-input__container alondra-grow alondra-align-center">
									<span class="alondra-input__label"><?php esc_html_e( 'Is in any Tag:', 'alondra' ); ?></span>
									<input class="alondra-input" type="text" name="tags" placeholder="<?php esc_attr_e( 'Type name...', 'alondra' ); ?>">
								</div>
							</div>						
							<div class="alondra-table__rule-inner alondra-table__rule-inner--separator alondra-align-bottom">
								<span class="alondra-line"></span>
								<strong class="alondra-align-center"><?php esc_html_e( 'or', 'alondra' ); ?></strong>
								<span class="alondra-line"></span>	
							</div>							
							<div class="alondra-table__rule-inner">
								<div class="alondra-input__container alondra-grow">
									<span class="alondra-input__label"><?php esc_html_e( 'User is:', 'alondra' ); ?></span>	
									<input class="alondra-input" type="text" name="users" placeholder="<?php esc_attr_e( 'Type name...', 'alondra' ); ?>">
								</div>
								<span class="alondra-align-bottom"><strong><?php esc_html_e( 'or', 'alondra' ); ?></strong></span>

								<div class="alondra-input__container alondra-grow">
									<span class="alondra-input__label"><?php esc_html_e( 'Has any profile:', 'alondra' ); ?></span>
									<input class="alondra-input" type="text" name="profiles" placeholder="<?php esc_attr_e( 'Type name...', 'alondra' ); ?>">
								</div>
							</div>
						</div>					
					</div>
				</template>
			</div>
			<footer class="alondra-table__footer">
				<button class="button alondra-add" type="button"><?php esc_html_e( 'Add row', 'alondra' ); ?></button>
				<button class="button alondra-remove" type="button"><?php esc_html_e( 'Delete selected row(s)', 'alondra' ); ?></button>
			</footer>
		</div>
	</div>
</div>



<?php // phpcs:ignore Generic.WhiteSpace.ScopeIndent.Incorrect -- the markup whitespace is output.
		return (string) ob_get_clean();
	}
}
