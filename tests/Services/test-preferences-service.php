<?php
/**
 * PreferencesService Tests
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Services;

use Midrinet\Alondra\Application\Service\PreferencesService;
use WP_UnitTestCase;

/**
 * Covers the hardcoded preference defaults and the stored values that win over
 * them, including the preferences a stored value cannot change: the colors, the
 * styles and every table position but the default and hide.
 */
class PreferencesServiceTest extends WP_UnitTestCase {

	/**
	 * The hardcoded color values, which nothing outside the plugin can change.
	 *
	 * @var array<string, string>
	 */
	private const COLORS = [
		PreferencesService::PREF_COLOR                  => '#000000',
		PreferencesService::PREF_BG_COLOR               => '#ffffff',
		PreferencesService::PREF_BORDER_COLOR           => '#000000',
		PreferencesService::PREF_HIGHLIGHT_COLOR        => '#ffffff',
		PreferencesService::PREF_HIGHLIGHT_BG_COLOR     => '#007cba',
		PreferencesService::PREF_HIGHLIGHT_BORDER_COLOR => '#000000',
	];

	private function get_instance(): PreferencesService {
		return new PreferencesService();
	}

	public function tear_down() {
		delete_option( PreferencesService::PREF_OPTION );
		parent::tear_down();
	}

	/**
	 * A stored configurable key wins over its default; a stored fixed key is ignored.
	 */
	public function test_stored_configurable_value_wins_and_fixed_one_is_ignored() {
		update_option(
			PreferencesService::PREF_OPTION,
			[
				PreferencesService::PREF_DISABLE_STYLES  => '1',
				PreferencesService::PREF_LAYOUT_POSITION => PreferencesService::LAYOUT_POSITION_HIDE,
			]
		);

		$prefs = $this->get_instance();
		$this->assertFalse( $prefs->disable_styles() );
		$this->assertSame( PreferencesService::LAYOUT_POSITION_HIDE, $prefs->get_layout_pos() );
	}

	/**
	 * The suite is single-site, so $blog_id stands in for switch_to_blog() and the option write for the
	 * other site's own.
	 */
	public function test_the_memo_does_not_survive_a_blog_switch() {
		global $blog_id;
		$prefs = $this->get_instance();
		$this->assertTrue( $prefs->overwrite_price() );

		$home = $blog_id;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Standing in for switch_to_blog(); restored below.
		$blog_id = $home + 1;
		update_option( PreferencesService::PREF_OPTION, [ PreferencesService::PREF_OVERWRITE_PRODUCT_PRICE => '0' ] );
		$other = $prefs->overwrite_price();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring the stand-in.
		$blog_id = $home;

		$this->assertFalse( $other );
		$this->assertTrue( $prefs->overwrite_price(), 'The home site keeps its own memo.' );
	}

	public function test_pricing_layout_defaults_to_the_table() {
		$this->assertSame( PreferencesService::PRICING_LAYOUT_TABLE, $this->get_instance()->get_pricing_layout() );
	}

	/**
	 * Any stored id comes back as is, whether or not a view is registered for it.
	 */
	public function test_pricing_layout_returns_the_stored_id() {
		update_option( PreferencesService::PREF_OPTION, [ PreferencesService::PREF_PRICING_LAYOUT => 'pills' ] );

		$this->assertSame( 'pills', $this->get_instance()->get_pricing_layout() );
	}

	/**
	 * @dataProvider provide_unusable_stored_layouts
	 *
	 * @param mixed $stored Stored option.
	 */
	public function test_unusable_stored_layout_reads_as_the_table( $stored ) {
		update_option( PreferencesService::PREF_OPTION, $stored );

		$this->assertSame( PreferencesService::PRICING_LAYOUT_TABLE, $this->get_instance()->get_pricing_layout() );
	}

	public function provide_unusable_stored_layouts(): array {
		return [
			'not an array' => [ 'pills' ],
			'empty id'     => [ [ PreferencesService::PREF_PRICING_LAYOUT => '' ] ],
			'not a string' => [ [ PreferencesService::PREF_PRICING_LAYOUT => [ 'pills' ] ] ],
		];
	}

	/**
	 * The free defaults: interactive and highlighted prices, styles enabled.
	 */
	public function test_free_defaults() {
		$prefs = $this->get_instance();

		$this->assertTrue( $prefs->is_price_clickable() );
		$this->assertTrue( $prefs->highlight_prices() );
		$this->assertTrue( $prefs->overwrite_price() );
		$this->assertFalse( $prefs->disable_styles() );
		$this->assertSame( PreferencesService::LAYOUT_POSITION_BEFORE_ADD_TO_CART_BTN, $prefs->get_layout_pos() );
		$this->assertFalse( $prefs->uninstall_cleanup() );
		$this->assertTrue( $prefs->enable_cache() );
	}

	/**
	 * The cache is on by default and a stored '0' switches it off.
	 */
	public function test_enable_cache_reads_the_stored_value() {
		$this->store_pref( PreferencesService::PREF_ENABLE_CACHE, '0' );

		$this->assertFalse( $this->get_instance()->enable_cache() );
	}

	/**
	 * A stored value overrides its key and leaves the rest at their defaults.
	 */
	public function test_stored_value_overrides_a_single_preference() {
		$this->store_pref( PreferencesService::PREF_CLICKABLE_LAYOUT, '0' );

		$prefs = $this->get_instance();
		$this->assertFalse( $prefs->is_price_clickable() );
		$this->assertTrue( $prefs->highlight_prices() );
	}

	/**
	 * A stored option that is not an array must not break the getters.
	 */
	public function test_stored_non_array_is_ignored() {
		update_option( PreferencesService::PREF_OPTION, 'nope' );

		$prefs = $this->get_instance();
		$this->assertTrue( $prefs->is_price_clickable() );
		$this->assertSame( '#000000', $prefs->get_color() );
	}

	/**
	 * @dataProvider provide_uninstall_cleanup_values
	 *
	 * @param mixed $stored   Stored value.
	 * @param bool  $expected What the getter reads.
	 */
	public function test_uninstall_cleanup_reads_the_stored_value( $stored, bool $expected ) {
		$this->store_pref( PreferencesService::PREF_UNINSTALL_CLEANUP, $stored );

		$this->assertSame( $expected, $this->get_instance()->uninstall_cleanup() );
	}

	/**
	 * @return array<string, array{mixed, bool}>
	 */
	public function provide_uninstall_cleanup_values() {
		return [
			'string one'  => [ '1', true ],
			'true'        => [ true, true ],
			'string zero' => [ '0', false ],
			'garbage'     => [ 'nope', false ],
			'non scalar'  => [ [ '1' ], false ],
		];
	}

	/**
	 * Hiding the table is the one position a stored value can ask for besides the default.
	 */
	public function test_stored_value_can_hide_the_table() {
		$this->store_pref( PreferencesService::PREF_LAYOUT_POSITION, PreferencesService::LAYOUT_POSITION_HIDE );

		$this->assertSame( PreferencesService::LAYOUT_POSITION_HIDE, $this->get_instance()->get_layout_pos() );
	}

	/**
	 * Every other position falls back to the default, so the table is either above the
	 * Add to Cart button or not rendered.
	 *
	 * @dataProvider provide_unsupported_positions
	 * @param mixed $position Stored position.
	 */
	public function test_stored_value_asking_for_another_position_falls_back_to_the_default( $position ) {
		$this->store_pref( PreferencesService::PREF_LAYOUT_POSITION, $position );

		$this->assertSame( PreferencesService::LAYOUT_POSITION_BEFORE_ADD_TO_CART_BTN, $this->get_instance()->get_layout_pos() );
	}

	/**
	 * @return array<string, array{mixed}>
	 */
	public function provide_unsupported_positions() {
		return [
			'after add to cart button' => [ 'after_add_to_cart_btn' ],
			'before add to cart form'  => [ 'before_add_to_cart_form' ],
			'after add to cart form'   => [ 'after_add_to_cart_form' ],
			'before title'             => [ 'before_title' ],
			'before summary'           => [ 'before_summary' ],
			'after summary'            => [ 'after_summary' ],
			'additional info tab'      => [ 'tab_additional_info' ],
			'unknown'                  => [ 'somewhere_else' ],
			'empty'                    => [ '' ],
			'non scalar'               => [ [ 'unexpected' ] ],
		];
	}

	/**
	 * The plugin CSS always loads; a stored value asking for it to be dropped has no effect.
	 *
	 * @dataProvider provide_truthy_values
	 * @param mixed $value Stored value.
	 */
	public function test_stored_value_cannot_disable_the_styles( $value ) {
		$this->store_pref( PreferencesService::PREF_DISABLE_STYLES, $value );

		$this->assertFalse( $this->get_instance()->disable_styles() );
	}

	/**
	 * @return array<string, array{mixed}>
	 */
	public function provide_truthy_values() {
		return [
			'string one' => [ '1' ],
			'true'       => [ true ],
			'yes'        => [ 'yes' ],
			'integer'    => [ 1 ],
		];
	}

	/**
	 * The colors are fixed, so nothing stored for one reaches the getter --
	 * neither a valid hex color, nor a value crafted to close the CSS declaration and
	 * append rules of its own.
	 *
	 * @dataProvider provide_stored_colors
	 * @param mixed $color Stored color.
	 */
	public function test_stored_value_cannot_change_a_color( $color ) {
		update_option( PreferencesService::PREF_OPTION, array_fill_keys( array_keys( self::COLORS ), $color ) );

		$prefs = $this->get_instance();
		$this->assertSame( self::COLORS[ PreferencesService::PREF_COLOR ], $prefs->get_color() );
		$this->assertSame( self::COLORS[ PreferencesService::PREF_BG_COLOR ], $prefs->get_bg_color() );
		$this->assertSame( self::COLORS[ PreferencesService::PREF_BORDER_COLOR ], $prefs->get_border_color() );
		$this->assertSame( self::COLORS[ PreferencesService::PREF_HIGHLIGHT_COLOR ], $prefs->get_highlight_color() );
		$this->assertSame( self::COLORS[ PreferencesService::PREF_HIGHLIGHT_BG_COLOR ], $prefs->get_highlight_bg_color() );
		$this->assertSame( self::COLORS[ PreferencesService::PREF_HIGHLIGHT_BORDER_COLOR ], $prefs->get_highlight_border_color() );
	}

	/**
	 * Values a stored option could carry. Hex and non-hex alike now resolve to the
	 * hardcoded default.
	 *
	 * @return array<string, array{mixed}>
	 */
	public function provide_stored_colors() {
		return [
			'short hex'    => [ '#fff' ],
			'long hex'     => [ '#a1b2c3' ],
			'upper hex'    => [ '#AABBCC' ],
			'named'        => [ 'red' ],
			'rgb'          => [ 'rgb(255, 0, 0)' ],
			'variable'     => [ 'var(--wp--preset--color--primary)' ],
			'no hash'      => [ 'ff0000' ],
			'empty'        => [ '' ],
			'four digits'  => [ '#ffff' ],
			'css breaking' => [ '#fff; } body{display:none' ],
			'non scalar'   => [ [ 'unexpected' ] ],
		];
	}

	/**
	 * The colors reach the stylesheet through sanitize_hex_color() whatever supplied them,
	 * so every hardcoded default is a hex color.
	 */
	public function test_every_color_default_is_a_hex_color() {
		$prefs = $this->get_instance();

		$this->assertSame( self::COLORS[ PreferencesService::PREF_COLOR ], sanitize_hex_color( $prefs->get_color() ) );
		$this->assertSame( self::COLORS[ PreferencesService::PREF_BG_COLOR ], sanitize_hex_color( $prefs->get_bg_color() ) );
		$this->assertSame( self::COLORS[ PreferencesService::PREF_BORDER_COLOR ], sanitize_hex_color( $prefs->get_border_color() ) );
		$this->assertSame( self::COLORS[ PreferencesService::PREF_HIGHLIGHT_COLOR ], sanitize_hex_color( $prefs->get_highlight_color() ) );
		$this->assertSame( self::COLORS[ PreferencesService::PREF_HIGHLIGHT_BG_COLOR ], sanitize_hex_color( $prefs->get_highlight_bg_color() ) );
		$this->assertSame( self::COLORS[ PreferencesService::PREF_HIGHLIGHT_BORDER_COLOR ], sanitize_hex_color( $prefs->get_highlight_border_color() ) );
	}

	/**
	 * The guard that keeps a value out of the stylesheet sits where the color is read, not
	 * where it comes in, so it still refuses a value that never passed through the option.
	 * Set by reflection because that is the only way left to reach it: a stored value cannot put
	 * anything in a color key, and this is the last thing between a value and
	 * wp_add_inline_style().
	 *
	 * @dataProvider provide_resolved_colors
	 * @param mixed  $value    Value sitting in the resolved preferences.
	 * @param string $expected Color the getter must return.
	 */
	public function test_color_is_validated_where_it_is_read( $value, $expected ) {
		$prefs = $this->get_instance();
		$this->set_resolved_preference( $prefs, PreferencesService::PREF_BG_COLOR, $value );

		$this->assertSame( $expected, $prefs->get_bg_color() );
	}

	/**
	 * Anything that is not a hex color falls back to the hardcoded default; a hex color is
	 * used as is.
	 *
	 * @return array<string, array{mixed, string}>
	 */
	public function provide_resolved_colors() {
		$default = self::COLORS[ PreferencesService::PREF_BG_COLOR ];

		return [
			'closes the declaration' => [ '#fff; } body{display:none', $default ],
			'appends a declaration'  => [ '#fff; background-image: url(//example.com/x.png)', $default ],
			'named'                  => [ 'red', $default ],
			'rgb'                    => [ 'rgb(255, 0, 0)', $default ],
			'variable'               => [ 'var(--wp--preset--color--primary)', $default ],
			'no hash'                => [ 'ff0000', $default ],
			'four digits'            => [ '#ffff', $default ],
			'empty'                  => [ '', $default ],
			'non scalar'             => [ [ 'unexpected' ], $default ],
			'short hex'              => [ '#abc', '#abc' ],
			'long hex'               => [ '#a1b2c3', '#a1b2c3' ],
			'upper hex'              => [ '#AABBCC', '#AABBCC' ],
		];
	}

	/**
	 * Put a value straight into the resolved preferences, bypassing the option.
	 *
	 * @param PreferencesService $prefs Service to write into.
	 * @param string                  $key   Preference key.
	 * @param mixed                   $value Value to resolve for it.
	 */
	private function set_resolved_preference( PreferencesService $prefs, $key, $value ) {
		$property = new \ReflectionProperty( PreferencesService::class, 'preferences' );
		$property->setAccessible( true );
		$property->setValue( $prefs, [ get_current_blog_id() => [ $key => $value ] ] );
	}

	/**
	 * Store a single preference in the option.
	 *
	 * @param string $key   Preference key.
	 * @param mixed  $value Value to store for it.
	 */
	private function store_pref( $key, $value ) {
		update_option( PreferencesService::PREF_OPTION, [ $key => $value ] );
	}
}
