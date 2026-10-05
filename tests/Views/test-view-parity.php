<?php
/**
 * View parity tests
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Views;

use Midrinet\Alondra\Application\Dto\SimpleTierDto;
use Midrinet\Alondra\Infrastructure\View\Admin\NoticeView;
use Midrinet\Alondra\Infrastructure\View\Admin\TieredPricingEditView;
use Midrinet\Alondra\Infrastructure\View\Admin\TieredPricingListTableView;
use Midrinet\Alondra\Infrastructure\View\Admin\TitlebarOptionsEditView;
use Midrinet\Alondra\Infrastructure\View\Admin\TitlebarOptionsListView;
use Midrinet\Alondra\Infrastructure\View\Admin\TitlebarOptionsTrashView;
use Midrinet\Alondra\Infrastructure\View\Front\TableView;
use Midrinet\Alondra\Infrastructure\View\ListTable;
use Midrinet\Alondra\Infrastructure\View\Preferences\PreferencesPageCloseView;
use Midrinet\Alondra\Infrastructure\View\Preferences\PreferencesPageOpenView;
use Midrinet\Alondra\Infrastructure\View\Preferences\PreferencesPageTitlebarView;
use Midrinet\Alondra\Infrastructure\View\Preferences\KofiButtonView;
use Midrinet\Alondra\Infrastructure\View\Preferences\SettingsFooterView;
use Midrinet\Alondra\Infrastructure\View\Preferences\CheckboxFieldView;
use Midrinet\Alondra\Infrastructure\View\Preferences\SelectFieldView;
use Midrinet\Alondra\Infrastructure\View\Preferences\TabsView;
use Midrinet\Alondra\Infrastructure\View\Toolkit\PrefPage;
use WP_UnitTestCase;

/**
 * Each view renders what the template it replaced rendered for the same input, compared on
 * normalized whitespace. The expected markup was captured from the templates before they were removed.
 */
class ViewParityTest extends WP_UnitTestCase {

	private const PAGE_ID = 'alondra_tiered_pricing';

	private const NOTICE_DISMISSIBLE = '<div id="message" class="notice notice-warning is-dismissible"><p>Saved <strong>ok</strong> x</p></div>';

	private const NOTICE_PLAIN = '<div id="message" class="notice notice-&quot;error "><p>Plain</p></div>';

	private const TABLE_CLICKABLE = "\n"
		. "<table class=\"alondra-pricing__options alondra-pricing__table\">\n"
		. "\t<thead>\n"
		. "\t\t<th>Quantity</th>\n"
		. "\t\t<th>Price per unit</th>\n"
		. "\t</thead>\n"
		. "\t<tbody>\n"
		. "\t\t\t\t\t<tr class=\"alondra-pricing__option alondra-clickable\" \n"
		. "\t\t\tdata-price=\"10.5\"\n"
		. "\t\t\tdata-min-units=\"1\"\n"
		. "\t\t\tdata-max-units=\"9\">\n"
		. "\t\t\t\t<td>Up to 9</td>\n"
		. "\t\t\t\t<td><del><span class=\"woocommerce-Price-amount amount\"><span class=\"woocommerce-Price-currencySymbol\">&euro;</span>12.00</span></del> <span class=\"woocommerce-Price-amount amount\"><span class=\"woocommerce-Price-currencySymbol\">&euro;</span>10.50</span> <span>(100% off)</span></td>\n"
		. "\t\t\t</tr>\n"
		. "\t\t\t\t\t<tr class=\"alondra-pricing__option alondra-clickable\" \n"
		. "\t\t\tdata-price=\"8\"\n"
		. "\t\t\tdata-min-units=\"10\"\n"
		. "\t\t\tdata-max-units=\"0\">\n"
		. "\t\t\t\t<td>10 - 0</td>\n"
		. "\t\t\t\t<td><del><span class=\"woocommerce-Price-amount amount\"><span class=\"woocommerce-Price-currencySymbol\">&euro;</span>12.00</span></del> <span class=\"woocommerce-Price-amount amount\"><span class=\"woocommerce-Price-currencySymbol\">&euro;</span>8.00</span> <span>(100% off)</span></td>\n"
		. "\t\t\t</tr>\n"
		. "\t\t\t</tbody>\n"
		. "</table>\n";

	private const TABLE_SINGLE = "\n"
		. "<table class=\"alondra-pricing__options alondra-pricing__table\">\n"
		. "\t<thead>\n"
		. "\t\t<th>Quantity</th>\n"
		. "\t\t<th>Price per unit</th>\n"
		. "\t</thead>\n"
		. "\t<tbody>\n"
		. "\t\t\t\t\t<tr class=\"alondra-pricing__option \" \n"
		. "\t\t\tdata-price=\"10.5\"\n"
		. "\t\t\tdata-min-units=\"1\"\n"
		. "\t\t\tdata-max-units=\"9\">\n"
		. "\t\t\t\t<td>Up to 9</td>\n"
		. "\t\t\t\t<td><del><span class=\"woocommerce-Price-amount amount\"><span class=\"woocommerce-Price-currencySymbol\">&euro;</span>12.00</span></del> <span class=\"woocommerce-Price-amount amount\"><span class=\"woocommerce-Price-currencySymbol\">&euro;</span>10.50</span> <span>(100% off)</span></td>\n"
		. "\t\t\t</tr>\n"
		. "\t\t\t</tbody>\n"
		. "</table>\n";

	private const EDIT_FORM = "<div class=\"alondra-edit-section\">\n"
		. "\t<header class=\"alondra-edit-section__header\">\n"
		. "\t\t<h2 class=\"alondra-edit-section__title\">Details</h2>\n"
		. "\t</header>\n"
		. "\t<div class=\"alondra-edit-section__content alondra-edit-section__content--flex\">\n"
		. "\t\t<input type=\"hidden\" class=\"alondra-input\" name=\"id\">\n"
		. "\t\t<div class=\"alondra-input__container alondra-grow\">\n"
		. "\t\t\t<span class=\"alondra-input__label\">Title <span class=\"alondra-input__required\">*</span></span>\t\n"
		. "\t\t\t<input class=\"alondra-input\" autofocus required type=\"text\" name=\"title\" placeholder=\"Identifies the prices group in the listing\">\n"
		. "\t\t</div>\n"
		. "\t</div>\n"
		. "</div>\n"
		. "\n"
		. "<div class=\"alondra-edit-section alondra-edit-section--open\">\n"
		. "\t<header class=\"alondra-edit-section__header\">\n"
		. "\t\t<h2 class=\"alondra-edit-section__title\">Pricing <span class=\"alondra-input__required\">*</span></h2>\n"
		. "\t\t<button type=\"button\" class=\"alondra-edit-section__toggle\"><span class=\"dashicons\"></span></button>\n"
		. "\t</header>\n"
		. "\t<div class=\"alondra-edit-section__content\">\n"
		. "\t\t<div class=\"alondra-table\" id=\"alondra-table-tiers\">\n"
		. "\t\t\t<header class=\"alondra-table__header\">\n"
		. "\t\t\t\t<input type=\"checkbox\" class=\"alondra-check-all\">\n"
		. "\t\t\t\t<span>Minimum units  <span class=\"alondra-input__required\">*</span></span>\n"
		. "\t\t\t\t<span>Maximum units</span>\n"
		. "\t\t\t\t<span>\n"
		. "\t\t\t\tValue per unit (€)\t\t\t\t<span class=\"alondra-input__required\">*</span></span>\n"
		. "\t\t\t</header>\n"
		. "\t\t\t<div class=\"alondra-table__body\">\n"
		. "\t\t\t\t<template>\n"
		. "\t\t\t\t\t<div class=\"alondra-table__row\">\n"
		. "\t\t\t\t\t\t<input type=\"checkbox\">\n"
		. "\t\t\t\t\t\t<input type=\"number\" value=\"1\" min=\"1\" step=\"1\" name=\"min_quantity\" autocomplete=\"off\" required>\n"
		. "\t\t\t\t\t\t<input type=\"number\" value=\"\" placeholder=\"∞\" min=\"1\" step=\"1\" name=\"max_quantity\" autocomplete=\"off\">\n"
		. "\t\t\t\t\t\t<input type=\"number\" value=\"\" placeholder=\"Equal to or greater than 0\" min=\"0\" step=\"0.001\" name=\"value\" autocomplete=\"off\" required>\n"
		. "\t\t\t\t\t</div>\n"
		. "\t\t\t\t</template>\n"
		. "\t\t\t</div>\n"
		. "\t\t\t<footer class=\"alondra-table__footer\">\n"
		. "\t\t\t\t<button class=\"button alondra-add\" type=\"button\">Add row</button>\n"
		. "\t\t\t\t<button class=\"button alondra-remove\" type=\"button\">Delete selected row(s)</button>\n"
		. "\t\t\t</footer>\n"
		. "\t\t</div>\n"
		. "\t</div>\n"
		. "</div>\n"
		. "\n"
		. "<div class=\"alondra-edit-section alondra-edit-section--open\">\n"
		. "\t<header class=\"alondra-edit-section__header\">\n"
		. "\t\t<h2 class=\"alondra-edit-section__title\">\n"
		. "\t\tApply the above prices when   at least one of the following    rules is met.\t\t<span class=\"alondra-input__required\">*</span>\n"
		. "\t\t</h2>\n"
		. "\t\t<button type=\"button\" class=\"alondra-edit-section__toggle\"><span class=\"dashicons\"></span></button>\n"
		. "\t</header>\n"
		. "\t<div class=\"alondra-edit-section__content\">\n"
		. "\t\t<div class=\"alondra-table alondra-table--rules\"  id=\"alondra-table-rules\">\n"
		. "\t\t\t<header class=\"alondra-table__header\">\n"
		. "\t\t\t\t<input type=\"checkbox\" class=\"alondra-check-all\">\n"
		. "\t\t\t\t<span>\n"
		. "\t\t\t\tRules\t\t\t\t<span class=\"alondra-input__required\">*</span>\n"
		. "\t\t\t\t</span>\n"
		. "\t\t\t</header>\n"
		. "\t\t\t<div class=\"alondra-table__body\">\n"
		. "\t\t\t\t<template>\n"
		. "\t\t\t\t\t<div class=\"alondra-table__row\">\n"
		. "\t\t\t\t\t\t<input type=\"checkbox\">\n"
		. "\t\t\t\t\t\t<!-- content -->\n"
		. "\t\t\t\t\t\t<div class=\"alondra-table__rule\">\n"
		. "\t\t\t\t\t\t\t<div class=\"alondra-table__rule-inner\">\t\t\t\t\t\t\t\n"
		. "\t\t\t\t\t\t\t\t<div class=\"alondra-input__container alondra-grow alondra-align-center\">\n"
		. "\t\t\t\t\t\t\t\t\t<span class=\"alondra-input__label\">Product is:</span>\t\n"
		. "\t\t\t\t\t\t\t\t\t<input class=\"alondra-input\" type=\"text\" name=\"products\" placeholder=\"Type name, SKU or ID...\">\n"
		. "\t\t\t\t\t\t\t\t</div>\n"
		. "\t\t\t\t\t\t\t\t<span class=\"alondra-align-bottom\">\n"
		. "\t\t\t\t\t\t\t\t\t<strong>or</strong>\n"
		. "\t\t\t\t\t\t\t\t</span>\n"
		. "\n"
		. "\t\t\t\t\t\t\t\t<div class=\"alondra-input__container alondra-grow alondra-align-center\">\n"
		. "\t\t\t\t\t\t\t\t\t<span class=\"alondra-input__label\">Is in any Category:</span>\n"
		. "\t\t\t\t\t\t\t\t\t<input class=\"alondra-input\" type=\"text\" name=\"categories\" placeholder=\"Type name...\">\n"
		. "\t\t\t\t\t\t\t\t</div>\n"
		. "\n"
		. "\t\t\t\t\t\t\t\t<span class=\"alondra-align-bottom\"><strong>or</strong></span>\n"
		. "\n"
		. "\t\t\t\t\t\t\t\t<div class=\"alondra-input__container alondra-grow alondra-align-center\">\n"
		. "\t\t\t\t\t\t\t\t\t<span class=\"alondra-input__label\">Is in any Tag:</span>\n"
		. "\t\t\t\t\t\t\t\t\t<input class=\"alondra-input\" type=\"text\" name=\"tags\" placeholder=\"Type name...\">\n"
		. "\t\t\t\t\t\t\t\t</div>\n"
		. "\t\t\t\t\t\t\t</div>\t\t\t\t\t\t\n"
		. "\t\t\t\t\t\t\t<div class=\"alondra-table__rule-inner alondra-table__rule-inner--separator alondra-align-bottom\">\n"
		. "\t\t\t\t\t\t\t\t<span class=\"alondra-line\"></span>\n"
		. "\t\t\t\t\t\t\t\t<strong class=\"alondra-align-center\">or</strong>\n"
		. "\t\t\t\t\t\t\t\t<span class=\"alondra-line\"></span>\t\n"
		. "\t\t\t\t\t\t\t</div>\t\t\t\t\t\t\t\n"
		. "\t\t\t\t\t\t\t<div class=\"alondra-table__rule-inner\">\n"
		. "\t\t\t\t\t\t\t\t<div class=\"alondra-input__container alondra-grow\">\n"
		. "\t\t\t\t\t\t\t\t\t<span class=\"alondra-input__label\">User is:</span>\t\n"
		. "\t\t\t\t\t\t\t\t\t<input class=\"alondra-input\" type=\"text\" name=\"users\" placeholder=\"Type name...\">\n"
		. "\t\t\t\t\t\t\t\t</div>\n"
		. "\t\t\t\t\t\t\t\t<span class=\"alondra-align-bottom\"><strong>or</strong></span>\n"
		. "\n"
		. "\t\t\t\t\t\t\t\t<div class=\"alondra-input__container alondra-grow\">\n"
		. "\t\t\t\t\t\t\t\t\t<span class=\"alondra-input__label\">Has any profile:</span>\n"
		. "\t\t\t\t\t\t\t\t\t<input class=\"alondra-input\" type=\"text\" name=\"profiles\" placeholder=\"Type name...\">\n"
		. "\t\t\t\t\t\t\t\t</div>\n"
		. "\t\t\t\t\t\t\t</div>\n"
		. "\t\t\t\t\t\t</div>\t\t\t\t\t\n"
		. "\t\t\t\t\t</div>\n"
		. "\t\t\t\t</template>\n"
		. "\t\t\t</div>\n"
		. "\t\t\t<footer class=\"alondra-table__footer\">\n"
		. "\t\t\t\t<button class=\"button alondra-add\" type=\"button\">Add row</button>\n"
		. "\t\t\t\t<button class=\"button alondra-remove\" type=\"button\">Delete selected row(s)</button>\n"
		. "\t\t\t</footer>\n"
		. "\t\t</div>\n"
		. "\t</div>\n"
		. "</div>\n"
		. "\n"
		. "\n"
		. "\n";

	private const LIST_TABLE = "[views]\n"
		. "<form method=\"POST\">\n"
		. "\t[prepare][search:Search:search_id][display]</form>\n";

	private const TITLEBAR_EDIT_SAVE = "<button type=\"button\" class=\"button alondra-draft-tiered-pricing\">Save as unpublished</button>\n"
		. "<button type=\"button\" class=\"button button-primary alondra-publish-tiered-pricing\">Save</button>\n"
		. "<button type=\"button\" class=\"button alondra-options-menu__toggle\">&#8942;</button>\n"
		. "<div class=\"alondra-options-menu\">\n"
		. "\t<button type=\"button\" class=\"button alondra-options-menu__item alondra-draft-tiered-pricing\">Save as unpublished</button>\n"
		. "\t<button type=\"button\" class=\"button alondra-options-menu__item alondra-options-menu__item--trash alondra-trash-tiered-pricing\">Trash</button>\n"
		. "</div>\n";

	private const TITLEBAR_EDIT_DEFAULT = "<button type=\"button\" class=\"button alondra-draft-tiered-pricing\">Save as unpublished</button>\n"
		. "<button type=\"button\" class=\"button button-primary alondra-publish-tiered-pricing\">Publish</button>\n"
		. "<button type=\"button\" class=\"button alondra-options-menu__toggle\">&#8942;</button>\n"
		. "<div class=\"alondra-options-menu\">\n"
		. "\t<button type=\"button\" class=\"button alondra-options-menu__item alondra-draft-tiered-pricing\">Save as unpublished</button>\n"
		. "\t<button type=\"button\" class=\"button alondra-options-menu__item alondra-options-menu__item--trash alondra-trash-tiered-pricing\">Trash</button>\n"
		. "</div>\n";

	private const TITLEBAR_LIST = "<a href=\"http://example.org/wp-admin/admin.php?page=alondra-tiered-pricing&#038;action=add\" id=\"alondra-add-tiered-pricing\" class=\"button button-primary\">＋ Add New</a>\n";

	private const TITLEBAR_TRASH = "<button type=\"button\" class=\"button alondra-restore-tiered-pricing\">Restore</button>\n"
		. "<button type=\"button\" class=\"button button-link-delete button-link-delete--fill alondra-delete-tiered-pricing\">Delete Permanently</button>\n";

	private const PREFERENCES_OPEN = "\n"
		. "<div class=\"alondra-preferences\" data-page=\"alondra_tiered_pricing\">\n"
		. "\t<div class=\"alondra-preferences__header\">\n"
		. "\t\t[header:alondra_tiered_pricing]\t</div>\n"
		. "\t<div class=\"alondra-preferences__content\">\n"
		. "\t\t[content:alondra_tiered_pricing]";

	private const PREFERENCES_CLOSE = "\n"
		. "\t</div>\n"
		. "\t<div class=\"alondra-preferences__footer\">\n"
		. "\t\t[footer:alondra_tiered_pricing]\t</div>\n"
		. "</div>\n";

	private const PREFERENCES_TITLEBAR = "<div class=\"alondra-preferences__titlebar\" data-page=\"alondra_tiered_pricing\">\n"
		. "\t" . PreferencesPageTitlebarView::LOGO . "\n"
		. "\t<h1 class=\"alondra-preferences__title\">Tiered &lt;b&gt;Pricing&lt;/b&gt;</h1>\n"
		. "\t<div class=\"alondra-preferences__titlebar-options\">\n"
		. "\t\t[titlebar_options:alondra_tiered_pricing]\t</div>\n"
		. "</div>\n";

	public function set_up() {
		parent::set_up();
		add_filter( 'woocommerce_currency_symbol', [ $this, 'euro' ] );
	}

	public function tear_down() {
		remove_filter( 'woocommerce_currency_symbol', [ $this, 'euro' ] );
		unset( $GLOBALS['title'] );
		parent::tear_down();
	}

	public function euro(): string {
		return '&euro;';
	}

	/**
	 * @return SimpleTierDto[]
	 */
	private function tiers(): array {
		return [ new SimpleTierDto( 1, 9, 10.5, 12 ), new SimpleTierDto( 10, 0, 8, 12 ) ];
	}

	public function test_notice() {
		$view = new NoticeView();

		$this->assertSame( $this->normalize( self::NOTICE_DISMISSIBLE ), $this->normalize( $view->render( 'Saved <strong>ok</strong> <script>x</script>', 'notice-warning', true ) ) );
		$this->assertSame( $this->normalize( self::NOTICE_PLAIN ), $this->normalize( $view->render( 'Plain', 'notice-"error', false ) ) );
	}

	public function test_table() {
		$view = new TableView();

		$this->assertSame( $this->normalize( self::TABLE_CLICKABLE ), $this->normalize( $view->render( $this->tiers(), 'alondra-clickable' ) ) );
		$this->assertSame( $this->normalize( self::TABLE_SINGLE ), $this->normalize( $view->render( [ $this->tiers()[0] ], '' ) ) );
		$this->assertSame( '', $view->render( [], '' ) );
	}

	public function test_normalization_keeps_attribute_and_text_changes() {
		$actual = $this->normalize( ( new TableView() )->render( [ $this->tiers()[0] ], '' ) );

		$this->assertSame( $this->normalize( self::TABLE_SINGLE ), $actual );
		$this->assertNotSame( $this->normalize( str_replace( 'data-price="10.5"', 'data-price="10.6"', self::TABLE_SINGLE ) ), $actual );
		$this->assertNotSame( $this->normalize( str_replace( 'Up to 9', 'Up to 8', self::TABLE_SINGLE ) ), $actual );
	}

	public function test_table_subtotal_goes_after_the_body() {
		$view = new class() extends TableView {
			protected function subtotal(): string {
				return '<tfoot></tfoot>';
			}
		};

		$this->assertStringEndsWith( '</tbody><tfoot></tfoot></table>', $this->normalize( $view->render( $this->tiers(), '' ) ) );
	}

	public function test_edit_form() {
		$this->assertSame( $this->normalize( self::EDIT_FORM ), $this->normalize( ( new TieredPricingEditView() )->render( '&euro;' ) ) );
	}

	public function test_list_table() {
		$table = new class() extends ListTable {
			public function __construct() {
				// Skips the list table setup.
			}

			public function views() {
				echo '[views]';
			}

			public function prepare_items() {
				echo '[prepare]';
			}

			public function search_box( $text, $input_id ) {
				echo esc_html( "[search:$text:$input_id]" );
			}

			public function display() {
				echo '[display]';
			}
		};

		$this->assertSame( $this->normalize( self::LIST_TABLE ), $this->normalize( ( new TieredPricingListTableView() )->render( $table ) ) );
	}

	public function test_titlebar_options() {
		$this->assertSame( $this->normalize( self::TITLEBAR_EDIT_SAVE ), $this->normalize( ( new TitlebarOptionsEditView() )->render( 'Save' ) ) );
		$this->assertSame( $this->normalize( self::TITLEBAR_EDIT_DEFAULT ), $this->normalize( ( new TitlebarOptionsEditView() )->render( '' ) ) );
		$this->assertSame( $this->normalize( self::TITLEBAR_LIST ), $this->normalize( ( new TitlebarOptionsListView() )->render( 'http://example.org/wp-admin/admin.php?page=alondra-tiered-pricing&action=add' ) ) );
		$this->assertSame( $this->normalize( self::TITLEBAR_TRASH ), $this->normalize( ( new TitlebarOptionsTrashView() )->render() ) );
	}

	public function test_settings_footer() {
		$this->assertSame(
			$this->normalize(
				'<p class="alondra-preferences__footer-meta">'
					. '<span class="alondra-preferences__footer-edition">FREE v2.0.0</span>'
					. ' <a class="alondra-preferences__footer-link" href="https://example.org/changelog" target="_blank" rel="noopener">Changelog</a>'
					. ' <a class="alondra-preferences__footer-link" href="https://example.org/support" target="_blank" rel="noopener">Get help</a>'
					. '</p>'
					. '<div class="alondra-preferences__footer-actions">'
					. ( new KofiButtonView() )->render( 'https://example.org/donate' )
					. '<a class="alondra-rate" href="https://example.org/reviews" target="_blank" rel="noopener">'
					. SettingsFooterView::STARS
					. '<span class="alondra-rate__label">Rate us on WordPress.org</span></a>'
					. '</div>'
			),
			$this->normalize(
				( new SettingsFooterView() )->render( 'FREE', '2.0.0', 'https://example.org/changelog', 'https://example.org/support', 'https://example.org/reviews', 'https://example.org/donate' )
			)
		);
		$html = ( new SettingsFooterView() )->render( '<b>', '1<i>', 'https://example.org/"a', 'javascript:alert(1)', 'javascript:alert(2)', 'https://example.org/donate' );
		$this->assertStringContainsString( '<span class="alondra-preferences__footer-edition">&lt;b&gt; v1&lt;i&gt;</span>', $html );
		$this->assertStringContainsString( 'href="https://example.org/a"', $html );
		$this->assertStringNotContainsString( 'javascript:', $html );
		$this->assert_decorative_svg( SettingsFooterView::STARS );
	}

	public function test_kofi_button() {
		$this->assertSame(
			$this->normalize(
				'<a class="alondra-kofi" href="https://example.org/donate" target="_blank" rel="noopener" aria-label="Support Us on Ko-fi">'
					. KofiButtonView::CUP
					. '<span class="alondra-kofi__label">Support Us</span></a>'
			),
			$this->normalize(
				( new KofiButtonView() )->render( 'https://example.org/donate' )
			)
		);
		$this->assert_decorative_svg( KofiButtonView::CUP );
	}

	/**
	 * Inline, decorative and self-contained: no CSS, ids or sizes that could leak into the admin page.
	 *
	 * @param string $svg The SVG markup.
	 */
	private function assert_decorative_svg( string $svg ) {
		$this->assertMatchesRegularExpression( '#^<svg [^>]*aria-hidden="true" focusable="false"[^>]*>.*</svg>$#s', $svg );
		$this->assertDoesNotMatchRegularExpression( '#<style|\sstyle=|\sid=|\swidth=|\sheight=|<title#', $svg );
		$this->assertSame( 1, substr_count( $svg, 'class=' ) );
	}

	public function test_settings_tabs() {
		$this->assertSame(
			$this->normalize(
				'<div class="alondra-tab__container">'
					. '<div class="alondra-tab alondra-active" data-target="#alondra-tab-settings">Settings</div>'
					. '<div class="alondra-tab" data-target="#alondra-tab-license">License &amp; Account</div>'
					. '</div>'
					. '<div id="alondra-tab-settings" class="alondra-tab__content alondra-active"><form></form></div>'
					. '<div id="alondra-tab-license" class="alondra-tab__content"><p>x</p></div>'
			),
			$this->normalize(
				( new TabsView() )->render(
					[
						'settings' => [
							'title'   => 'Settings',
							'content' => '<form></form>',
						],
						'license'  => [
							'title'   => 'License & Account',
							'content' => '<p>x</p>',
						],
					]
				)
			)
		);
	}

	public function test_settings_fields() {
		$this->assertSame(
			$this->normalize(
				'<tr class="form-field alondra-field__wrapper"><th scope="row">Cache</th><td><label for="alondra-enable-cache">'
					. '<input type="checkbox" class="alondra-form-field" id="alondra-enable-cache" name="alondra[enable_cache]" value="1" checked="checked" /> Keep &amp; cache</label></td></tr>'
			),
			$this->normalize(
				( new CheckboxFieldView() )->render( 'alondra[enable_cache]', 'alondra-enable-cache', 'Cache', 'Keep & cache', true )
			)
		);
		$this->assertSame(
			$this->normalize(
				'<tr class="form-field alondra-field__wrapper"><th scope="row"><label for="alondra-layout-pos">Show prices at</label></th><td>'
					. '<select class="alondra-form-field" id="alondra-layout-pos" name="alondra[layout_pos]"><option value="a">A</option><option value="b" selected="selected">B</option></select></td></tr>'
			),
			$this->normalize(
				( new SelectFieldView() )->render(
					'alondra[layout_pos]',
					'alondra-layout-pos',
					'Show prices at',
					[
						'a' => 'A',
						'b' => 'B',
					],
					'b'
				)
			)
		);
	}

	public function test_preferences_page() {
		$this->assertSame(
			$this->normalize(
				self::PREFERENCES_OPEN
			),
			$this->normalize(
				( new PreferencesPageOpenView() )->render( self::PAGE_ID, '[header:' . self::PAGE_ID . ']', '[content:' . self::PAGE_ID . ']' )
			)
		);
		$this->assertSame( $this->normalize( self::PREFERENCES_CLOSE ), $this->normalize( ( new PreferencesPageCloseView() )->render( '[footer:' . self::PAGE_ID . ']' ) ) );
		$this->assertSame(
			$this->normalize(
				self::PREFERENCES_TITLEBAR
			),
			$this->normalize(
				( new PreferencesPageTitlebarView() )->render( self::PAGE_ID, 'Tiered <b>Pricing</b>', '[titlebar_options:' . self::PAGE_ID . ']' )
			)
		);
	}

	/**
	 * The logo is inline, decorative and self-contained: no CSS or ids that could leak into the admin page.
	 */
	public function test_preferences_titlebar_logo_is_decorative_inline_svg() {
		$logo = PreferencesPageTitlebarView::LOGO;
		$this->assert_decorative_svg( $logo );
		$this->assertStringContainsString( 'class="alondra-preferences__logo"', $logo );
		$this->assertStringContainsString( 'viewBox="0 0 1024 1024"', $logo );
	}

	/**
	 * The page prints its areas through the callbacks it was given, and fires no action of its own:
	 * the alondra_preference_* hooks are not part of the plugin's public surface.
	 */
	public function test_pref_page_renders_its_callbacks_into_the_views() {
		$hooks = [ 'alondra_preference_header', 'alondra_preference_content', 'alondra_preference_footer', 'alondra_preference_titlebar_options' ];
		foreach ( $hooks as $hook ) {
			add_action( $hook, [ $this, 'fail_on_hook' ] );
		}
		$GLOBALS['title'] = 'Tiered <b>Pricing</b>'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- get_admin_page_title() reads it.
		$page             = ( new PrefPage( self::PAGE_ID ) )
			->set_header( $this->marker( 'header' ) )
			->set_content( $this->marker( 'content' ) )
			->set_titlebar_options( $this->marker( 'titlebar_options' ) )
			->set_footer( $this->marker( 'footer' ) );

		ob_start();
		$page->output_settings_page();
		$page->embed_page_header();
		$output = ob_get_clean();

		$this->assertSame( $this->normalize( self::PREFERENCES_OPEN . self::PREFERENCES_CLOSE . self::PREFERENCES_TITLEBAR ), $this->normalize( $output ) );
		foreach ( $hooks as $hook ) {
			$this->assertSame( 0, did_action( $hook ), $hook );
		}
	}

	private function normalize( string $html ): string {
		return (string) preg_replace( '/>\s+</', '><', (string) preg_replace( '/\s+/', ' ', trim( $html ) ) );
	}

	private function marker( string $area ): callable {
		return function ( $id ) use ( $area ) {
			echo esc_html( "[$area:$id]" );
		};
	}

	public function fail_on_hook(): void {
		$this->fail( 'PrefPage fired an action.' );
	}
}
