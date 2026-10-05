<?php
/**
 * The seed filters import Migration
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Infrastructure/Migration
 */

namespace Midrinet\Alondra\Infrastructure\Migration;

use Midrinet\Alondra\Application\Service\PreferencesService;

/**
 * Stores what the two retired seed filters returned into the preferences option, once.
 *
 * The only place either hook name is still used: both filters are retired and called here for their last
 * listeners. The runner runs on `init` priority 11, after the active theme's functions.php and any earlier `init`
 * listener have registered their callbacks, so those listeners are seen. A key already present in the option
 * is never overwritten, which also makes a re-entry after a partial run change nothing.
 *
 * @since      2.0.0
 */
class ImportSeedFiltersMigration implements Migration {

	/**
	 * What the preferences filter could set when it was retired, with the free defaults it was handed then.
	 * Frozen here rather than read from the service, whose readable keys keep growing.
	 *
	 * @var array<string, string>
	 */
	private const FILTER_DEFAULTS = [
		PreferencesService::PREF_LAYOUT_POSITION         => PreferencesService::LAYOUT_POSITION_BEFORE_ADD_TO_CART_BTN,
		PreferencesService::PREF_CLICKABLE_LAYOUT        => '1',
		PreferencesService::PREF_HIGHLIGHT_PRICING       => '1',
		PreferencesService::PREF_OVERWRITE_PRODUCT_PRICE => '1',
	];

	/**
	 * Checkboxes an older settings screen saved by leaving them out of the option, which it read as off.
	 * These default to on in 2.0, so an absent key is written as off.
	 *
	 * @var array<int, string>
	 */
	private const PAID_CHECKBOXES = [
		PreferencesService::PREF_CLICKABLE_LAYOUT,
		PreferencesService::PREF_HIGHLIGHT_PRICING,
		PreferencesService::PREF_OVERWRITE_PRODUCT_PRICE,
		PreferencesService::PREF_ENABLE_CACHE,
	];

	public function execute(): void {
		$stored = get_option( PreferencesService::PREF_OPTION, [] );
		$stored = \is_array( $stored ) ? $stored : [];

		// Only that older screen wrote keys other than this import's own, and there the option outranked
		// the filter, so an absent checkbox stays off whatever the filter returns.
		$own_keys = self::FILTER_DEFAULTS + [ PreferencesService::PREF_UNINSTALL_CLEANUP => '' ];
		$paid_off = [] === array_diff_key( $stored, $own_keys ) ? [] : array_fill_keys( self::PAID_CHECKBOXES, '0' );

		/**
		 * Retired preferences filter, applied once for its last listeners.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $prefs Preferences to import.
		 */
		$filtered = apply_filters( 'alondra_default_prefs', self::FILTER_DEFAULTS );
		$filtered = \is_array( $filtered ) ? array_filter( array_intersect_key( $filtered, self::FILTER_DEFAULTS ), 'is_scalar' ) : [];
		$filtered = array_diff_assoc( $filtered, self::FILTER_DEFAULTS );

		/**
		 * Retired uninstall opt-in, applied once for its last listeners.
		 *
		 * @since 1.0.3
		 *
		 * @param bool $delete Whether uninstalling drops the tables.
		 */
		$filtered[ PreferencesService::PREF_UNINSTALL_CLEANUP ] = apply_filters( 'alondra_delete_data_on_uninstall', false ) ? '1' : '0';

		$merged = $stored + $paid_off + $filtered;
		if ( $merged !== $stored ) {
			update_option( PreferencesService::PREF_OPTION, $merged );
		}
	}
}
