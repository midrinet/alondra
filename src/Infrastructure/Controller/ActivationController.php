<?php
/**
 * Activation, requirements and schema upkeep
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Infrastructure/Controller
 */

namespace Midrinet\Alondra\Infrastructure\Controller;

use Midrinet\Alondra\Application\Service\TieredPricingService;
use Midrinet\Alondra\Infrastructure\Config\PluginInfo;
use Midrinet\Alondra\Infrastructure\DI\Container;
use Midrinet\Alondra\Infrastructure\Migration\MigrationRunner;

/**
 * Handles plugin activation
 *
 * @since      0.1.0
 */
class ActivationController extends Controller {

	public const PREF_VERSION = 'alondra_version';

	/**
	 * Minimum supported WooCommerce version.
	 */
	private const MIN_WC_VERSION = '10.4.0';

	/**
	 * Register the schema hooks. Only reached once the requirements are met.
	 *
	 * The plugin stays active and inert when they are not, so migrating from here keeps a build that has
	 * declared it is doing nothing from changing the schema, and keeps the failure notice from
	 * contradicting the requirements notice.
	 *
	 * The activation hook does not fire on in-place updates, so `init` is what covers those. Priority 11
	 * puts it after WooCommerce's own `init` work, which it registers at priority 0: the migration failure
	 * path logs through wc_get_logger(), which needs WooCommerce initialised. Nothing this plugin runs
	 * earlier on `init` reads the tables.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'migrate' ], 11 );
		add_action( 'admin_notices', [ $this, 'show_migration_failure_notice' ] );
	}

	/**
	 * Whether the running WooCommerce version is supported. The plugin header
	 * declares the PHP, WordPress and WooCommerce requirements, but WordPress
	 * only enforces the WooCommerce plugin's presence, not its version.
	 *
	 * @return bool
	 */
	public function requirements_met() {
		return \defined( 'WC_VERSION' ) && version_compare( (string) \constant( 'WC_VERSION' ), self::MIN_WC_VERSION, '>=' );
	}

	/**
	 * Print an admin notice explaining why the plugin is doing nothing.
	 *
	 * @return void
	 */
	public function print_requirements_notice() {
		$this->show_notice(
			\sprintf(
				// translators: %s: minimum WooCommerce version.
				esc_html__( 'Alondra needs WooCommerce %s or greater to apply tiered prices. Update WooCommerce to enable it.', 'alondra' ),
				esc_html( self::MIN_WC_VERSION )
			),
			true,
			'warning'
		);
	}

	/**
	 * Fired during plugin activation
	 *
	 * @since    0.1.0
	 * @return void
	 */
	public function activate() {
		if ( ! $this->requirements_met() ) {
			return;
		}

		// A deliberate second door onto the same schema work migration CREATE_TABLES does, and the reason
		// it is worth keeping is the wp_die(): a host without CREATE rights fails loudly here, during
		// activation, instead of installing silently and only breaking once a customer loads a product
		// page. It advances no cursor, so migration 1 still runs on the first `init` and the two doors
		// cannot end up disagreeing about the schema.
		try {
			Container::instance()->get( TieredPricingService::class )->setup();
		} catch ( \Throwable $e ) {
			// setup_database()'s deliberate failures are Exceptions, but a TypeError out of dbDelta or the
			// datamappers is an Error and would otherwise escape as a raw fatal instead of this page.
			wp_die( esc_html( $e->getMessage() ), '', [ 'back_link' => true ] );
		}
	}

	/**
	 * Apply anything this version owes the database, and record the version it did it for
	 *
	 * Hooked on `init`, so it also runs after an in-place update.
	 *
	 * Two independent concerns, each gated on its own state: schema work on the migration cursor, inside the
	 * service's lock; the version marker on the stored version, outside it. Neither may take the other down,
	 * and nothing may escape: this is reached from `init`, where there is no wp_die() and a throwable fatals
	 * every request on the site, storefront included. Step one contains its own failures -- run() is
	 * documented not to throw -- and step two carries the catch below.
	 *
	 * @return void
	 */
	public function migrate() {
		Container::instance()->get( MigrationRunner::class )->run();
		$this->maybe_seed_preferences();
	}

	/**
	 * Record that this release's one-time upgrade steps have been taken
	 *
	 * Gated on the stored plugin version, not on the migration cursor: a release that adds a preference
	 * default but no migration still has to run here, and a downgrade must not write over newer state.
	 *
	 * Deliberately not written by activate(): a marker set at activation without the work behind it having
	 * run is how reactivating after an update comes to suppress that update's steps for good.
	 *
	 * @return void
	 */
	private function maybe_seed_preferences() {
		try {
			$version = Container::instance()->get( PluginInfo::class )->get_plugin_version();

			if ( version_compare( $this->get_version(), $version, '>=' ) ) {
				return;
			}

			// Anything a future release owes its preferences belongs here, before the marker moves.
			// Its return value is not a success flag: it is false when the value is unchanged.
			$this->set_version( $version );
		} catch ( \Throwable $e ) {
			// Reached from `init`, where there is no wp_die() to turn a fatal into a recoverable error
			// page: an escaping throw takes down every request on the site and keeps doing it, because
			// nothing here advances state to stop it recurring. \Throwable rather than \Exception
			// because a TypeError out of the option layer is not an Exception.
			//
			// Nothing is recorded and nothing is announced: this is not a migration, and reusing
			// PREF_MIGRATION_FAILURE would make the admin notice name the wrong thing. The marker is
			// left un-advanced, so the next request retries.
			unset( $e );
		}
	}

	/**
	 * The plugin version whose upgrade steps last ran.
	 *
	 * @return string Empty string when nothing has been stored yet, which compares as older than any release.
	 */
	public function get_version(): string {
		$version = get_option( self::PREF_VERSION );

		return empty( $version ) ? '' : (string) $version; // @phpstan-ignore cast.string
	}

	/**
	 * Store the plugin version once its upgrade steps have run.
	 *
	 * @param string $version Version to store.
	 * @return bool False also when the value is unchanged.
	 */
	public function set_version( string $version ): bool {
		return update_option( self::PREF_VERSION, $version );
	}

	/**
	 * Tell administrators on the plugin's own screens that the tables are unavailable
	 *
	 * Scoped to those screens because explaining them is the one thing the WooCommerce log cannot do: the
	 * schema gate makes the rules listing render empty, and with no explanation there a failure is
	 * indistinguishable from data loss. Anywhere else in wp-admin it is noise. The detail -- migration id,
	 * exception class, raw message -- belongs in the log under `MigrationRunner::LOG_SOURCE`, not in
	 * front of a shop owner who cannot act on it.
	 *
	 * The screen test reads `$_GET['page']`, the same identification the toolkit's `PrefPage` already uses.
	 * Every page slug this plugin registers is prefixed with the plugin slug, and unlike a
	 * `get_current_screen()` id that prefix survives a page being moved to another parent menu.
	 *
	 * Not dismissible: the record describes a live broken schema that is retried until it succeeds, so it
	 * would be rewritten as fast as it could be dismissed. It clears itself the moment a retry succeeds,
	 * which is the only dismissal that means anything.
	 *
	 * @return void
	 */
	public function show_migration_failure_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// A cast would raise "Array to string conversion" on ?page[]=x before sanitize_key() ever saw it.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reads which admin page is rendering, changes nothing.
		$page = isset( $_GET['page'] ) && \is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 0 !== strpos( $page, Container::instance()->get( PluginInfo::class )->get_plugin_slug() ) ) {
			return;
		}

		if ( ! \is_array( get_option( MigrationRunner::PREF_MIGRATION_FAILURE ) ) ) {
			return;
		}

		$this->show_notice(
			esc_html__( 'Alondra\'s database tables are not available yet and it is retrying automatically. Tiered pricing is paused until then. See WooCommerce → Status → Logs for the details.', 'alondra' ),
			true,
			'warning',
			false
		);
	}
}
