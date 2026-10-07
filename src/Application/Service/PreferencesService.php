<?php
/**
 * The Preferences Service
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Application/Service
 */

namespace Midrinet\Alondra\Application\Service;

/**
 * The Preferences Service
 *
 * @since      1.0.0
 */
class PreferencesService {

	public const PREF_OPTION = 'alondra';

	public const PREF_CLICKABLE_LAYOUT        = 'clickable_layout';
	public const PREF_LAYOUT_POSITION         = 'layout_pos';
	public const PREF_PRICING_LAYOUT          = 'pricing_layout';
	public const PREF_HIGHLIGHT_PRICING       = 'highlight_pricing';
	public const PREF_HIGHLIGHT_BG_COLOR      = 'highlight_bg_color';
	public const PREF_HIGHLIGHT_COLOR         = 'highlight_color';
	public const PREF_HIGHLIGHT_BORDER_COLOR  = 'highlight_border_color';
	public const PREF_BG_COLOR                = 'bg_color';
	public const PREF_COLOR                   = 'color';
	public const PREF_BORDER_COLOR            = 'border_color';
	public const PREF_OVERWRITE_PRODUCT_PRICE = 'overwrite_product_price';
	public const PREF_DISABLE_STYLES          = 'disable_styles';
	public const PREF_ENABLE_CACHE            = 'enable_cache';
	public const PREF_UNINSTALL_CLEANUP       = 'uninstall_cleanup';

	public const PRICING_LAYOUT_TABLE                   = 'table';
	public const LAYOUT_POSITION_HIDE                   = 'hide';
	public const LAYOUT_POSITION_BEFORE_ADD_TO_CART_BTN = 'before_add_to_cart_btn';

	/**
	 * Hardcoded preference values.
	 *
	 * @var array<string, string>
	 */
	private const VALUES = [
		self::PREF_LAYOUT_POSITION         => self::LAYOUT_POSITION_BEFORE_ADD_TO_CART_BTN,
		self::PREF_CLICKABLE_LAYOUT        => '1',

		self::PREF_HIGHLIGHT_PRICING       => '1',

		self::PREF_HIGHLIGHT_COLOR         => '#ffffff',
		self::PREF_HIGHLIGHT_BG_COLOR      => '#007cba',
		self::PREF_HIGHLIGHT_BORDER_COLOR  => '#000000',

		self::PREF_COLOR                   => '#000000',
		self::PREF_BG_COLOR                => '#ffffff',
		self::PREF_BORDER_COLOR            => '#000000',

		self::PREF_OVERWRITE_PRODUCT_PRICE => '1',
		self::PREF_DISABLE_STYLES          => '0',
		self::PREF_ENABLE_CACHE            => '1',
		self::PREF_UNINSTALL_CLEANUP       => '0',
	];

	/**
	 * The preference keys read back from the stored option. Every other key in VALUES is not configurable,
	 * and a stored value for one has no effect. Enforced here rather than by dropping the keys from
	 * VALUES, because the getters still read VALUES for the value and for get_hex_color()'s fallback.
	 *
	 * @var array<int, string>
	 */
	private const CONFIGURABLE = [
		self::PREF_LAYOUT_POSITION,
		self::PREF_CLICKABLE_LAYOUT,
		self::PREF_HIGHLIGHT_PRICING,
		self::PREF_OVERWRITE_PRODUCT_PRICE,
		self::PREF_ENABLE_CACHE,
		self::PREF_UNINSTALL_CLEANUP,
	];

	/**
	 * The resolved preferences, keyed by blog id so a switch_to_blog() reads the other site's option.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $preferences = [];

	/**
	 * Get the preferences: the hardcoded values, with the stored ones winning for the keys in CONFIGURABLE.
	 *
	 * @return array<string, mixed> Array of preferences.
	 */
	private function defaults() {
		$blog_id = get_current_blog_id();
		if ( ! isset( $this->preferences[ $blog_id ] ) ) {
			$stored                        = get_option( self::PREF_OPTION );
			$stored                        = \is_array( $stored ) ? array_intersect_key( $stored, array_flip( self::CONFIGURABLE ) ) : [];
			$this->preferences[ $blog_id ] = array_merge( self::VALUES, $stored );
		}
		return $this->preferences[ $blog_id ];
	}

	/**
	 * Get a preference as a sanitized string. Stored values cannot be trusted to be scalar.
	 *
	 * @param string $key Preference key.
	 * @return string
	 */
	private function get_string( $key ) {
		$value = $this->defaults()[ $key ] ?? '';
		return is_scalar( $value ) ? wp_kses_data( (string) $value ) : '';
	}

	/**
	 * Get a preference as a boolean.
	 *
	 * @param string $key Preference key.
	 * @return bool
	 */
	private function get_boolean( $key ) {
		return filter_var( $this->defaults()[ $key ] ?? false, FILTER_VALIDATE_BOOL );
	}

	/**
	 * Get a preference as a hex color. The value ends up in a CSS declaration, so it is validated
	 * at the point it is read and falls back to the hardcoded default rather than reaching the
	 * stylesheet, whatever supplied it.
	 *
	 * @param string $key Preference key.
	 * @return string
	 */
	private function get_hex_color( $key ) {
		$color = sanitize_hex_color( $this->get_string( $key ) );
		return empty( $color ) ? ( self::VALUES[ $key ] ?? '' ) : $color;
	}

	/**
	 * Get highlight background color to use for tiered pricing
	 *
	 * @return string
	 */
	public function get_highlight_bg_color() {
		return $this->get_hex_color( self::PREF_HIGHLIGHT_BG_COLOR );
	}

	/**
	 * Get highlight color to use for tiered pricing
	 *
	 * @return string
	 */
	public function get_highlight_color() {
		return $this->get_hex_color( self::PREF_HIGHLIGHT_COLOR );
	}

	/**
	 * Get highlight border color to use for tiered pricing
	 *
	 * @return string
	 */
	public function get_highlight_border_color() {
		return $this->get_hex_color( self::PREF_HIGHLIGHT_BORDER_COLOR );
	}

	/**
	 * Get background color to use for tiered pricing
	 *
	 * @return string
	 */
	public function get_bg_color() {
		return $this->get_hex_color( self::PREF_BG_COLOR );
	}

	/**
	 * Get color to use for tiered pricing
	 *
	 * @return string
	 */
	public function get_color() {
		return $this->get_hex_color( self::PREF_COLOR );
	}

	/**
	 * Get border color to use for tiered pricing
	 *
	 * @return string
	 */
	public function get_border_color() {
		return $this->get_hex_color( self::PREF_BORDER_COLOR );
	}

	/**
	 * Highlight prices or not
	 *
	 * @return bool
	 */
	public function highlight_prices() {
		return $this->get_boolean( self::PREF_HIGHLIGHT_PRICING );
	}

	/**
	 * Check if we should disable styles
	 *
	 * @return bool
	 */
	public function disable_styles() {
		return $this->get_boolean( self::PREF_DISABLE_STYLES );
	}

	/**
	 * Check if we should enable click to select tiered pricing
	 *
	 * @return bool
	 */
	public function is_price_clickable() {
		return $this->get_boolean( self::PREF_CLICKABLE_LAYOUT );
	}

	/**
	 * Get the position of the prices table in the product page. Either above the Add to Cart
	 * button or hidden; anything else falls back to the former.
	 *
	 * @return string
	 */
	public function get_layout_pos() {
		$position = $this->get_string( self::PREF_LAYOUT_POSITION );

		return self::LAYOUT_POSITION_HIDE === $position ? $position : self::LAYOUT_POSITION_BEFORE_ADD_TO_CART_BTN;
	}

	/**
	 * Should original product price be overwritten
	 *
	 * @return bool
	 */
	public function overwrite_price() {
		return $this->get_boolean( self::PREF_OVERWRITE_PRODUCT_PRICE );
	}

	public function enable_cache(): bool {
		return $this->get_boolean( self::PREF_ENABLE_CACHE );
	}

	/**
	 * Whether uninstalling drops the plugin's tables
	 *
	 * @return bool
	 */
	public function uninstall_cleanup(): bool {
		return $this->get_boolean( self::PREF_UNINSTALL_CLEANUP );
	}

	/**
	 * The stored storefront pricing layout id, unvalidated: the view factory falls back to the table
	 * for an id nobody registered, so the stored value survives whichever view renders it.
	 *
	 * @return string
	 */
	public function get_pricing_layout(): string {
		$stored = get_option( self::PREF_OPTION );
		$layout = \is_array( $stored ) ? ( $stored[ self::PREF_PRICING_LAYOUT ] ?? '' ) : '';
		return \is_string( $layout ) && '' !== $layout ? $layout : self::PRICING_LAYOUT_TABLE;
	}
}
